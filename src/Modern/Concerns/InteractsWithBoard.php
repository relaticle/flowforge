<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Modern\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Concerns\InteractsWithBoardTable;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;
use Relaticle\Flowforge\Exceptions\MaxRetriesExceededException;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;
use Relaticle\Flowforge\Services\DecimalPosition;
use Relaticle\Flowforge\Services\PositionRebalancer;

trait InteractsWithBoard
{
    use InteractsWithBoardTable;

    protected Board $board;

    /**
     * Cards per column pagination state.
     */
    #[Locked]
    public array $columnCardLimits = [];

    #[Url(as: 'groupBy')]
    public ?string $groupBy = null;

    #[Locked]
    public ?int $visibleLaneCount = null;

    /**
     * @var array<string, array<string, int>>
     */
    #[Locked]
    public array $cellCardLimits = [];

    public function updatedGroupBy(): void
    {
        $this->visibleLaneCount = null;
        $this->cellCardLimits = [];
    }

    public function loadMoreLanes(): void
    {
        $perPage = $this->getBoard()->getLanesPerPage();

        $this->visibleLaneCount = ($this->visibleLaneCount ?? $perPage) + $perPage;
    }

    /**
     * Loading states for columns.
     */
    public array $loadingStates = [];

    /**
     * Get the board configuration.
     */
    public function getBoard(): Board
    {
        return $this->board ??= $this->board($this->makeBoard());
    }

    /**
     * Boot the InteractsWithBoard trait.
     */
    public function bootedInteractsWithBoard(): void
    {
        $this->board = $this->board($this->makeBoard());
        $this->cacheBoardActions();
    }

    /**
     * Cache board actions for Filament's action system.
     */
    protected function cacheBoardActions(): void
    {
        $board = $this->getBoard();

        // Cache all actions for Filament's action system
        foreach ([...$board->getActions(), ...$board->getRecordActions(), ...$board->getColumnActions()] as $action) {
            if ($action instanceof ActionGroup) {
                foreach ($action->getFlatActions() as $flatAction) {
                    $this->cacheAction($flatAction);
                }
            } elseif ($action instanceof Action) {
                $this->cacheAction($action);
            }
        }

        $createCardAction = $board->getCreateCardAction();

        if ($createCardAction instanceof Action) {
            $this->cacheAction(
                $createCardAction->disabled(fn (array $arguments): bool => ! $this->canCreateCardIn($arguments['column'] ?? null)),
            );
        }
    }

    public function getBoardCreateCardAction(string $columnId): ?Action
    {
        $action = $this->getBoard()->getCreateCardAction();

        return $action ? $action(['column' => $columnId])->livewire($this) : null;
    }

    public function toggleBoardColumnCollapse(string $columnId): void
    {
        $this->getBoard()->toggleColumnCollapse($columnId);
    }

    protected function canCreateCardIn(mixed $columnId): bool
    {
        if (! is_string($columnId)) {
            return false;
        }

        if (! in_array($columnId, $this->getBoard()->getColumnIdentifiers(), true)) {
            return false;
        }

        return ! $this->getBoard()->isColumnFull($columnId);
    }

    protected function makeBoard(): Board
    {
        /** @var view-string $view */
        $view = 'flowforge::modern.index';

        return Board::make($this)
            ->view($view)
            ->stringColumnIds()
            ->query(fn (): ?Builder => $this->getBoardQuery());
    }

    public function moveCard(
        string $cardId,
        string $targetColumnId,
        ?string $afterCardId = null,
        ?string $beforeCardId = null,
        ?string $fromLaneId = null,
        ?string $toLaneId = null,
    ): bool {
        $query = $this->getBoard()->getQuery();

        if (! $query) {
            throw new InvalidArgumentException('Board query not available');
        }

        try {
            $card = (clone $query)->find($cardId) ?? throw CardMoveRejectedException::unknownCard();

            $this->ensureBoardShowsColumn($targetColumnId);
            $laneMove = $this->resolveLaneMove($card, $fromLaneId, $toLaneId);
            $this->authorizeCardMove($card, $targetColumnId, $laneMove !== null ? $laneMove[2]->getName() : null);
            $newPosition = $this->moveCardWithinLimit($card, $targetColumnId, $afterCardId, $beforeCardId, $laneMove);
        } catch (CardMoveRejectedException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();
            $this->skipRender();

            return false;
        }

        $this->dispatch('kanban-card-moved', [
            'cardId' => $cardId,
            'columnId' => $targetColumnId,
            'position' => $newPosition,
        ]);

        return true;
    }

    protected function ensureBoardShowsColumn(string $columnId): void
    {
        if (! in_array($columnId, $this->getBoard()->getColumnIdentifiers(), true)) {
            throw CardMoveRejectedException::unknownColumn();
        }
    }

    protected function authorizeCardMove(Model $card, string $targetColumnId, ?string $toLaneId = null): void {}

    /**
     * @return array{0: Grouping, 1: Lane, 2: Lane}|null
     */
    protected function resolveLaneMove(Model $card, ?string $fromLaneId, ?string $toLaneId): ?array
    {
        $grouping = $this->getBoard()->getActiveGrouping();

        if ($grouping === null || ($fromLaneId === null && $toLaneId === null)) {
            return null;
        }

        $sameLane = $fromLaneId === $toLaneId;

        if (! $sameLane && ! $grouping->isWritable()) {
            throw CardMoveRejectedException::lanesReadOnly((string) $grouping->getLabel());
        }

        $query = $this->getBoard()->getQuery();
        $lanes = collect($grouping->getLanes(clone $query))->keyBy(fn (Lane $lane): string => $lane->getName());
        $from = $lanes->get($fromLaneId);
        $to = $lanes->get($toLaneId);

        if ($from === null || $to === null) {
            throw CardMoveRejectedException::unknownLane();
        }

        if (! $from->applyQuery(clone $query)->reorder()->whereKey($card->getKey())->exists()) {
            throw CardMoveRejectedException::unknownLane();
        }

        return $sameLane ? null : [$grouping, $from, $to];
    }

    protected function persistCardMove(Model $card, mixed $columnValue, string $position): void
    {
        $board = $this->getBoard();

        $card->update([
            $board->getColumnIdentifierAttribute() => $columnValue,
            $board->getPositionIdentifierAttribute() => $position,
        ]);
    }

    protected function moveCardWithinLimit(
        Model $card,
        string $targetColumnId,
        ?string $afterCardId,
        ?string $beforeCardId,
        ?array $laneMove = null,
    ): string {
        $board = $this->getBoard();
        $column = $board->getColumn($targetColumnId);
        $limit = $column?->getLimit();

        if ($column === null || $limit === null) {
            return $this->calculateAndUpdatePositionWithRetry($card, $targetColumnId, $afterCardId, $beforeCardId, laneMove: $laneMove);
        }

        $columnLabel = (string) ($column->getLabel() ?? $targetColumnId);
        $lock = Cache::lock($this->getColumnLockKey($targetColumnId), $this->getColumnLockSeconds());

        try {
            $lock->block($this->getColumnLockWaitSeconds());
        } catch (LockTimeoutException) {
            throw CardMoveRejectedException::boardBusy($columnLabel);
        }

        try {
            $freshCard = (clone $board->getQuery())->whereKey($card->getKey())->first()
                ?? throw CardMoveRejectedException::unknownCard();

            if ($board->getRecordColumnId($freshCard) !== $targetColumnId && $board->isColumnFull($targetColumnId)) {
                throw CardMoveRejectedException::columnFull($columnLabel, $limit);
            }

            return $this->calculateAndUpdatePositionWithRetry($freshCard, $targetColumnId, $afterCardId, $beforeCardId, laneMove: $laneMove);
        } finally {
            $lock->release();
        }
    }

    protected function getColumnLockKey(string $columnId): string
    {
        $tenantKey = class_exists(Filament::class) && Filament::getCurrentPanel() !== null
            ? Filament::getTenant()?->getKey()
            : null;

        return implode(':', ['flowforge', static::class, $tenantKey ?? 'global', $columnId]);
    }

    protected function getColumnLockSeconds(): int
    {
        return 10;
    }

    protected function getColumnLockWaitSeconds(): int
    {
        return 5;
    }

    /**
     * Calculate position and update card within transaction with pessimistic locking.
     * This prevents race conditions when multiple users drag cards simultaneously.
     */
    protected function calculateAndUpdatePosition(
        Model $card,
        string $targetColumnId,
        ?string $afterCardId,
        ?string $beforeCardId,
        ?array $laneMove = null,
    ): string {
        $newPosition = '';

        DB::transaction(function () use ($card, $targetColumnId, $afterCardId, $beforeCardId, $laneMove, &$newPosition) {
            $board = $this->getBoard();
            $query = $board->getQuery();
            $positionField = $board->getPositionIdentifierAttribute();
            $columnField = $board->getColumnIdentifierAttribute();

            // LOCK reference cards for reading to prevent stale data
            $afterCard = $this->lockBoardCard($query, $afterCardId);
            $beforeCard = $this->lockBoardCard($query, $beforeCardId);

            // Get positions from locked cards
            $afterPos = $afterCard?->getAttribute($positionField);
            $beforePos = $beforeCard?->getAttribute($positionField);

            // Calculate position INSIDE transaction with locked data
            $newPosition = $this->calculateDecimalPosition($afterPos, $beforePos, $targetColumnId);

            // Check if rebalancing is needed after this insert
            if ($afterPos !== null && $beforePos !== null) {
                $afterPosStr = DecimalPosition::normalize($afterPos);
                $beforePosStr = DecimalPosition::normalize($beforePos);

                if (DecimalPosition::needsRebalancing($afterPosStr, $beforePosStr)) {
                    // Rebalance the column - this redistributes positions evenly
                    $this->rebalanceColumn($targetColumnId);

                    // Recalculate position after rebalancing
                    $afterCard = $this->lockBoardCard($query, $afterCardId);
                    $beforeCard = $this->lockBoardCard($query, $beforeCardId);

                    $afterPos = $afterCard?->getAttribute($positionField);
                    $beforePos = $beforeCard?->getAttribute($positionField);
                    $newPosition = $this->calculateDecimalPosition($afterPos, $beforePos, $targetColumnId);
                }
            }

            // Update card position
            $columnValue = $this->resolveStatusValue($card, $columnField, $targetColumnId);

            $this->persistCardMove($card, $columnValue, $newPosition);

            if ($laneMove !== null) {
                $this->moveCardAcrossLanes($card, $laneMove);
            }
        });

        return $newPosition;
    }

    protected function lockBoardCard(Builder $boardQuery, ?string $cardId): ?Model
    {
        if (blank($cardId)) {
            return null;
        }

        $boardCard = (clone $boardQuery)->whereKey($cardId)->first();

        if ($boardCard === null) {
            return null;
        }

        // Postgres refuses FOR UPDATE on the nullable side of an outer join, so the
        // board query only proves membership and the lock lands on the base table.
        return $boardQuery->getModel()->newModelQuery()
            ->whereKey($cardId)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @param  array{0: Grouping, 1: Lane, 2: Lane}  $laneMove
     */
    protected function moveCardAcrossLanes(Model $card, array $laneMove): void
    {
        [$grouping, $from, $to] = $laneMove;

        try {
            $grouping->moveCard($card, $from, $to);
        } catch (QueryException $exception) {
            report($exception);

            throw CardMoveRejectedException::because(__('flowforge::flowforge.lane_write_failed'));
        }
    }

    /**
     * Calculate position using DecimalPosition service.
     *
     * @param  mixed  $afterPos  Position of card above (null for top)
     * @param  mixed  $beforePos  Position of card below (null for bottom)
     * @param  string  $columnId  Target column ID
     */
    protected function calculateDecimalPosition(mixed $afterPos, mixed $beforePos, string $columnId): string
    {
        // Handle empty column case
        if ($afterPos === null && $beforePos === null) {
            return $this->getBoardPositionInColumn($columnId, 'bottom');
        }

        // Normalize positions to strings for BCMath
        $afterPosStr = $afterPos !== null ? DecimalPosition::normalize($afterPos) : null;
        $beforePosStr = $beforePos !== null ? DecimalPosition::normalize($beforePos) : null;

        return DecimalPosition::calculate($afterPosStr, $beforePosStr);
    }

    /**
     * Rebalance all positions in a column, redistributing them evenly.
     * Called automatically when gap between positions falls below MIN_GAP.
     */
    protected function rebalanceColumn(string $columnId): void
    {
        $board = $this->getBoard();
        $query = $board->getQuery();

        if (! $query) {
            return;
        }

        $rebalancer = new PositionRebalancer;
        $count = $rebalancer->rebalanceColumn(
            $query,
            $board->getColumnIdentifierAttribute(),
            $columnId,
            $board->getPositionIdentifierAttribute()
        );

        Log::info('Flowforge: Auto-rebalanced column due to small gap', [
            'column' => $columnId,
            'records' => $count,
        ]);
    }

    /**
     * Calculate and update position with automatic retry on conflicts.
     * Wraps calculateAndUpdatePosition() with retry logic to handle rare duplicate position conflicts.
     */
    protected function calculateAndUpdatePositionWithRetry(
        Model $card,
        string $targetColumnId,
        ?string $afterCardId,
        ?string $beforeCardId,
        int $maxAttempts = 3,
        ?array $laneMove = null,
    ): string {
        $baseDelay = 50; // milliseconds
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            if ($attempt > 1) {
                $card = (clone $this->getBoard()->getQuery())->whereKey($card->getKey())->first()
                    ?? throw CardMoveRejectedException::unknownCard();
            }

            try {
                return $this->calculateAndUpdatePosition(
                    $card,
                    $targetColumnId,
                    $afterCardId,
                    $beforeCardId,
                    $laneMove,
                );
            } catch (QueryException $e) {
                // Check if this is a unique constraint violation
                if (! $this->isDuplicatePositionError($e)) {
                    throw $e; // Not a duplicate, rethrow
                }

                $lastException = $e;

                // Log the conflict for monitoring
                Log::info('Position conflict detected, retrying', [
                    'card_id' => $card->getKey(),
                    'target_column' => $targetColumnId,
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                // Max retries reached?
                if ($attempt >= $maxAttempts) {
                    throw new MaxRetriesExceededException(
                        "Failed to move card after {$maxAttempts} attempts due to position conflicts",
                        previous: $e
                    );
                }

                // Exponential backoff: 50ms, 100ms, 200ms
                $delay = $baseDelay * pow(2, $attempt - 1);
                usleep($delay * 1000);

                // Refresh reference cards before retry (they may have moved)
                continue;
            }
        }

        // Should never reach here
        throw $lastException ?? new \RuntimeException('Unexpected retry loop exit');
    }

    /**
     * Check if a QueryException is due to unique constraint violation on positions.
     */
    protected function isDuplicatePositionError(QueryException $e): bool
    {
        $errorCode = $e->errorInfo[1] ?? null;

        // SQLite: SQLITE_CONSTRAINT (19)
        // MySQL: ER_DUP_ENTRY (1062)
        // PostgreSQL: unique_violation (23505)

        return in_array($errorCode, [19, 1062, 23505]) ||
               str_contains($e->getMessage(), 'unique_position_per_column') ||
               str_contains($e->getMessage(), 'UNIQUE constraint failed');
    }

    public function loadMoreItems(string $columnId, ?int $count = null, ?string $laneId = null): void
    {
        $perPage = $this->getBoard()->getCardsPerColumn();
        $count = max($count ?? $perPage, 1);

        if ($laneId !== null) {
            $this->cellCardLimits[$laneId][$columnId] = ($this->cellCardLimits[$laneId][$columnId] ?? $perPage) + min($count, $perPage);

            return;
        }

        // Set loading state
        $this->loadingStates[$columnId] = true;

        try {
            $board = $this->getBoard();
            $currentLimit = $this->columnCardLimits[$columnId] ?? $board->getCardsPerColumn();
            $newLimit = $currentLimit + $count;

            // Check if we have more items to load
            $totalCount = $board->getBoardRecordCount($columnId);
            $actualNewLimit = min($newLimit, $totalCount);

            $this->columnCardLimits[$columnId] = $actualNewLimit;

            // Calculate how many items were actually loaded
            $actualLoadedCount = $actualNewLimit - $currentLimit;

            // Emit event for frontend update
            $this->dispatch('kanban-items-loaded', [
                'columnId' => $columnId,
                'loadedCount' => $actualLoadedCount,
                'totalCount' => $totalCount,
                'isFullyLoaded' => $actualNewLimit >= $totalCount,
            ]);

        } finally {
            // Clear loading state
            $this->loadingStates[$columnId] = false;
        }
    }

    /**
     * Load all items in a column (disables pagination for that column).
     */
    public function loadAllItems(string $columnId): void
    {
        $this->loadingStates[$columnId] = true;

        try {
            $board = $this->getBoard();
            $totalCount = $board->getBoardRecordCount($columnId);

            // Set limit to total count to load everything
            $this->columnCardLimits[$columnId] = $totalCount;

            $this->dispatch('kanban-all-items-loaded', [
                'columnId' => $columnId,
                'totalCount' => $totalCount,
            ]);

        } finally {
            $this->loadingStates[$columnId] = false;
        }
    }

    /**
     * Check if a column is fully loaded.
     */
    public function isColumnFullyLoaded(string $columnId): bool
    {
        $board = $this->getBoard();
        $totalCount = $board->getBoardRecordCount($columnId);
        $loadedCount = $this->columnCardLimits[$columnId] ?? $board->getCardsPerColumn();

        return $loadedCount >= $totalCount;
    }

    /**
     * Calculate position between specific cards (for drag-drop).
     */
    protected function calculatePositionBetweenCards(
        ?string $afterCardId = null,
        ?string $beforeCardId = null,
        ?string $columnId = null
    ): string {
        if (! $afterCardId && ! $beforeCardId && $columnId) {
            return $this->getBoardPositionInColumn($columnId, 'bottom');
        }

        $query = $this->getBoard()->getQuery();
        if (! $query) {
            return DecimalPosition::forEmptyColumn();
        }

        $positionField = $this->getBoard()->getPositionIdentifierAttribute();
        $model = $query->getModel();
        $keyName = $model->getKeyName();

        // Batch fetch both cards in single query with only needed columns
        $cardIds = array_filter([$beforeCardId, $afterCardId]);
        $cards = $cardIds
            ? (clone $query)
                ->withoutEagerLoads()
                ->select([$model->qualifyColumn($keyName), $model->qualifyColumn($positionField)])
                ->whereIn($model->qualifyColumn($keyName), $cardIds)
                ->get()
                ->keyBy($keyName)
            : collect();

        $beforeCard = $beforeCardId ? $cards->get($beforeCardId) : null;
        $beforePos = $beforeCard?->getAttribute($positionField);

        $afterCard = $afterCardId ? $cards->get($afterCardId) : null;
        $afterPos = $afterCard?->getAttribute($positionField);

        // Normalize positions
        $afterPosStr = $afterPos !== null ? DecimalPosition::normalize($afterPos) : null;
        $beforePosStr = $beforePos !== null ? DecimalPosition::normalize($beforePos) : null;

        return DecimalPosition::calculate($afterPosStr, $beforePosStr);
    }

    /**
     * Resolve status value, handling enums properly.
     */
    protected function resolveStatusValue(Model $card, string $statusField, string $targetColumnId): mixed
    {
        $castType = $card->getCasts()[$statusField] ?? null;

        if ($castType && enum_exists($castType) && is_subclass_of($castType, \BackedEnum::class)) {
            /** @var class-string<\BackedEnum> $castType */
            return $castType::from($targetColumnId);
        }

        return $targetColumnId;
    }

    /**
     * Get the default record for an action (Filament's record injection system).
     */
    public function getDefaultActionRecord(Action $action): ?Model
    {
        // Get the current mounted action context
        $mountedActions = $this->mountedActions ?? [];

        if (empty($mountedActions)) {
            return null;
        }

        // Get the current mounted action
        $currentMountedAction = end($mountedActions);

        // Extract recordKey from context or arguments
        $recordKey = $currentMountedAction['context']['recordKey'] ??
            $currentMountedAction['arguments']['recordKey'] ?? null;

        if (! $recordKey) {
            return null;
        }

        // Resolve the record using board query
        $board = $this->getBoard();
        $query = $board->getQuery();

        if ($query) {
            return (clone $query)->find($recordKey);
        }

        return null;
    }

    /**
     * Get board query.
     */
    public function getBoardQuery(): ?Builder
    {
        return $this->getBoard()->getQuery();
    }

    /**
     * Resolve a board action (similar to resolveTableAction).
     */
    protected function resolveBoardAction(array $action, array $parentActions): ?Action
    {
        $resolvedAction = null;

        if (count($parentActions)) {
            $parentAction = end($parentActions);
            $resolvedAction = $parentAction->getModalAction($action['name']);
        } else {
            $resolvedAction = $this->cachedActions[$action['name']] ?? null;
        }

        if (! $resolvedAction) {
            return null;
        }

        $recordKey = $action['context']['recordKey'] ?? $action['arguments']['recordKey'] ?? null;

        if (filled($recordKey)) {
            $board = $this->getBoard();
            $query = $board->getQuery();

            if ($query) {
                $record = (clone $query)->find($recordKey);
                $resolvedAction->record($record);
            }
        }

        return $resolvedAction;
    }

    /**
     * Get board record actions with proper context.
     */
    public function getBoardRecordActions(array $record): array
    {
        $board = $this->getBoard();
        $actions = [];

        foreach ($board->getRecordActions() as $action) {
            $actionClone = $action->getClone();
            $actionClone->livewire($this);
            $actionClone->record($record['model']);
            $actions[] = $actionClone;
        }

        return $actions;
    }

    /**
     * Get board column actions with proper context.
     *
     * Uses __invoke() to set column arguments so they are serialized
     * into the wire:click handler via getInvokedArguments().
     *
     * @return array<Action>
     */
    public function getBoardColumnActions(string $columnId): array
    {
        $board = $this->getBoard();
        $actions = [];

        foreach ($board->getColumnActions() as $action) {
            $actions[] = $action(['column' => $columnId])->livewire($this);
        }

        return $actions;
    }

    /**
     * Get next board position for a column with direction control.
     * Handles null positions gracefully and ensures valid position assignment.
     */
    public function getBoardPositionInColumn(string $columnId, string $position = 'top'): string
    {
        $query = $this->getBoard()->getQuery();
        if (! $query) {
            return DecimalPosition::forEmptyColumn();
        }

        $board = $this->getBoard();
        $statusField = $board->getColumnIdentifierAttribute();
        $positionField = $board->getPositionIdentifierAttribute();
        $keyName = $query->getModel()->getKeyName();
        $queryClone = (clone $query)->where($statusField, $columnId);

        if ($position === 'top') {
            // Get first valid position (ignore null positions)
            $firstRecord = $queryClone
                ->whereNotNull($positionField)
                ->orderBy($positionField, 'asc')
                ->orderBy($keyName, 'asc') // Tie-breaker for deterministic order
                ->first();

            if ($firstRecord) {
                $firstPosition = $firstRecord->getAttribute($positionField);
                if ($firstPosition !== null) {
                    return DecimalPosition::before(DecimalPosition::normalize($firstPosition));
                }
            }

            return DecimalPosition::forEmptyColumn();
        }

        // Get last valid position (ignore null positions)
        $lastRecord = $queryClone
            ->whereNotNull($positionField)
            ->orderBy($positionField, 'desc')
            ->orderBy($keyName, 'desc') // Tie-breaker for deterministic order
            ->first();

        if ($lastRecord) {
            $lastPosition = $lastRecord->getAttribute($positionField);
            if ($lastPosition !== null) {
                return DecimalPosition::after(DecimalPosition::normalize($lastPosition));
            }
        }

        return DecimalPosition::forEmptyColumn();
    }
}
