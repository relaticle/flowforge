<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Exceptions\MaxRetriesExceededException;
use Relaticle\Flowforge\Services\DecimalPosition;
use Relaticle\Flowforge\Services\PositionRebalancer;
use Throwable;

trait InteractsWithBoard
{
    use InteractsWithBoardTable;

    protected Board $board;

    /**
     * Cards per column pagination state.
     */
    public array $columnCardLimits = [];

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
     * Rebuild the board after Livewire applies a table state update.
     *
     * booted() runs before incoming property updates are applied, so a board
     * whose column set, query or swimlanes depend on $tableFilters or
     * $tableSearch is built from the previous request's state and renders one
     * round trip stale. Toggling a filter that adds or removes columns returns
     * the old column set, and it stays wrong until something unrelated triggers
     * another render.
     *
     * booted() still builds the board, because cacheBoardActions() has to run
     * before Filament processes the request; this only rebuilds it once the new
     * state is actually in place.
     *
     * Only table state is rebuilt on. Rebuilding on every property update would
     * also fire for $columnCardLimits, which load-more-on-scroll writes to, and
     * discard the pagination it just set.
     */
    public function updatedInteractsWithBoard(string $property): void
    {
        if (! str_starts_with($property, 'tableFilters') && ! str_starts_with($property, 'tableSearch')) {
            return;
        }

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
    }

    protected function makeBoard(): Board
    {
        return Board::make($this)
            ->query(fn (): ?Builder => $this->getBoardQuery());
    }

    /**
     * Move card to new position using decimal-based positioning.
     *
     * @throws Throwable
     */
    public function moveCard(
        string $cardId,
        string $targetColumnId,
        ?string $afterCardId = null,
        ?string $beforeCardId = null
    ): void {
        $board = $this->getBoard();
        $query = $board->getQuery();

        if (! $query) {
            throw new InvalidArgumentException('Board query not available');
        }

        $card = (clone $query)->find($cardId);
        if (! $card) {
            throw new InvalidArgumentException("Card not found: {$cardId}");
        }

        // Calculate and update position with automatic retry on conflicts
        $newPosition = $this->calculateAndUpdatePositionWithRetry($card, $targetColumnId, $afterCardId, $beforeCardId);

        // Emit success event after successful transaction
        $this->dispatch('kanban-card-moved', [
            'cardId' => $cardId,
            'columnId' => $targetColumnId,
            'position' => $newPosition,
        ]);
    }

    /**
     * Calculate position and update card within transaction with pessimistic locking.
     * This prevents race conditions when multiple users drag cards simultaneously.
     */
    protected function calculateAndUpdatePosition(
        Model $card,
        string $targetColumnId,
        ?string $afterCardId,
        ?string $beforeCardId
    ): string {
        $newPosition = '';

        DB::transaction(function () use ($card, $targetColumnId, $afterCardId, $beforeCardId, &$newPosition) {
            $board = $this->getBoard();
            $query = $board->getQuery();
            $positionField = $board->getPositionIdentifierAttribute();
            $columnField = $board->getColumnIdentifierAttribute();

            // LOCK reference cards for reading to prevent stale data
            $afterCard = $afterCardId
                ? (clone $query)->whereKey($afterCardId)->lockForUpdate()->first()
                : null;

            $beforeCard = $beforeCardId
                ? (clone $query)->whereKey($beforeCardId)->lockForUpdate()->first()
                : null;

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
                    $afterCard = $afterCardId
                        ? (clone $query)->whereKey($afterCardId)->lockForUpdate()->first()
                        : null;
                    $beforeCard = $beforeCardId
                        ? (clone $query)->whereKey($beforeCardId)->lockForUpdate()->first()
                        : null;

                    $afterPos = $afterCard?->getAttribute($positionField);
                    $beforePos = $beforeCard?->getAttribute($positionField);
                    $newPosition = $this->calculateDecimalPosition($afterPos, $beforePos, $targetColumnId);
                }
            }

            // Update card position
            $columnValue = $this->resolveStatusValue($card, $columnField, $targetColumnId);

            $card->update([
                $columnField => $columnValue,
                $positionField => $newPosition,
            ]);
        });

        return $newPosition;
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
        int $maxAttempts = 3
    ): string {
        $baseDelay = 50; // milliseconds
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->calculateAndUpdatePosition(
                    $card,
                    $targetColumnId,
                    $afterCardId,
                    $beforeCardId
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

    /**
     * Load more items for a column or cell.
     *
     * When swimlanes are active, the $cellKey uses the format "columnId|swimlaneId"
     * to identify a specific cell. Without swimlanes, it's just the columnId.
     */
    public function loadMoreItems(string $cellKey, ?int $count = null): void
    {
        $count = $count ?? $this->getBoard()->getCardsPerColumn();

        // Set loading state
        $this->loadingStates[$cellKey] = true;

        try {
            $board = $this->getBoard();
            $currentLimit = $this->columnCardLimits[$cellKey] ?? $board->getCardsPerColumn();
            $newLimit = $currentLimit + $count;

            // Determine total count based on whether this is a cell or column key
            $totalCount = $this->getCellOrColumnCount($cellKey);
            $actualNewLimit = min($newLimit, $totalCount);

            $this->columnCardLimits[$cellKey] = $actualNewLimit;

            // Calculate how many items were actually loaded
            $actualLoadedCount = $actualNewLimit - $currentLimit;

            // Emit event for frontend update
            $this->dispatch('kanban-items-loaded', [
                'columnId' => $cellKey,
                'loadedCount' => $actualLoadedCount,
                'totalCount' => $totalCount,
                'isFullyLoaded' => $actualNewLimit >= $totalCount,
            ]);

        } finally {
            // Clear loading state
            $this->loadingStates[$cellKey] = false;
        }
    }

    /**
     * Get total record count for a cell key ("columnId|swimlaneId") or plain column.
     */
    protected function getCellOrColumnCount(string $cellKey): int
    {
        $board = $this->getBoard();

        if (str_contains($cellKey, '|') && $board->hasSwimlanes()) {
            [$columnId, $swimlaneId] = explode('|', $cellKey, 2);
            $counts = $board->getBatchedSwimlaneRecordCounts();

            return $counts[$cellKey] ?? 0;
        }

        return $board->getBoardRecordCount($cellKey);
    }

    /**
     * Load all items in a column or cell (disables pagination for that column/cell).
     */
    public function loadAllItems(string $cellKey): void
    {
        $this->loadingStates[$cellKey] = true;

        try {
            $totalCount = $this->getCellOrColumnCount($cellKey);

            // Set limit to total count to load everything
            $this->columnCardLimits[$cellKey] = $totalCount;

            $this->dispatch('kanban-all-items-loaded', [
                'columnId' => $cellKey,
                'totalCount' => $totalCount,
            ]);

        } finally {
            $this->loadingStates[$cellKey] = false;
        }
    }

    /**
     * Check if a column or cell is fully loaded.
     */
    public function isColumnFullyLoaded(string $cellKey): bool
    {
        $board = $this->getBoard();
        $totalCount = $this->getCellOrColumnCount($cellKey);
        $loadedCount = $this->columnCardLimits[$cellKey] ?? $board->getCardsPerColumn();

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
