<?php

declare(strict_types=1);

namespace Relaticle\Flowforge;

use Filament\Support\Components\ViewComponent;
use Relaticle\Flowforge\Concerns\BelongsToLivewire;
use Relaticle\Flowforge\Concerns\CanSearchBoardRecords;
use Relaticle\Flowforge\Concerns\HasBoardActions;
use Relaticle\Flowforge\Concerns\HasBoardColumns;
use Relaticle\Flowforge\Concerns\HasBoardFilters;
use Relaticle\Flowforge\Concerns\HasBoardGroupings;
use Relaticle\Flowforge\Concerns\HasBoardRecords;
use Relaticle\Flowforge\Concerns\HasCardLabels;
use Relaticle\Flowforge\Concerns\HasCardSchema;
use Relaticle\Flowforge\Concerns\HasColumnSummaries;
use Relaticle\Flowforge\Concerns\InteractsWithKanbanQuery;
use Relaticle\Flowforge\Contracts\HasBoard;

class Board extends ViewComponent
{
    use BelongsToLivewire;
    use CanSearchBoardRecords;
    use HasBoardActions;
    use HasBoardColumns;
    use HasBoardFilters;
    use HasBoardGroupings;
    use HasBoardRecords;
    use HasCardLabels;
    use HasCardSchema;
    use HasColumnSummaries;
    use InteractsWithKanbanQuery;

    protected string $view = 'flowforge::index';

    protected string $viewIdentifier = 'board';

    protected string $evaluationIdentifier = 'board';

    protected bool $headerToolbar = false;

    protected bool $collapseEmptyColumns = false;

    final public function __construct(HasBoard $livewire)
    {
        $this->livewire($livewire);
    }

    public static function make(HasBoard $livewire): static
    {
        $static = app(static::class, ['livewire' => $livewire]);
        $static->configure();

        return $static;
    }

    /**
     * Move the filter/search toolbar into the page header,
     * rendering it inline with the page title.
     */
    public function headerToolbar(bool $condition = true): static
    {
        $this->headerToolbar = $condition;

        return $this;
    }

    public function hasHeaderToolbar(): bool
    {
        return $this->headerToolbar;
    }

    /**
     * Collapse columns holding no cards into a slim vertical rail, so a long
     * pipeline spends its width on the stages that hold work.
     */
    public function collapseEmptyColumns(bool $condition = true): static
    {
        $this->collapseEmptyColumns = $condition;

        return $this;
    }

    public function collapsesEmptyColumns(): bool
    {
        return $this->collapseEmptyColumns;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Get view data for the board template.
     * Delegates to Livewire component like Filament's Table does.
     */
    public function getViewData(): array
    {
        $livewire = $this->getLivewire();
        $grouping = $this->getActiveGrouping();
        $columns = $this->getColumnViewData(withItems: $grouping === null);

        $laneData = $grouping === null
            ? ['lanes' => [], 'hasMoreLanes' => false]
            : $this->getLaneViewData(
                $grouping,
                $livewire->visibleLaneCount ?? $this->getLanesPerPage(),
                $livewire->cellCardLimits ?? [],
            );

        return [
            'columns' => $columns,
            'lanes' => $laneData['lanes'],
            'hasMoreLanes' => $laneData['hasMoreLanes'],
            'groupings' => array_map(fn (Grouping $option): string => (string) $option->getLabel(), $this->getGroupings()),
            'config' => [
                'recordTitleAttribute' => $this->getRecordTitleAttribute(),
                'columnIdentifierAttribute' => $this->getColumnIdentifierAttribute(),
                'cardLabel' => $this->getCardLabel(),
                'pluralCardLabel' => $this->getPluralCardLabel(),
                'headerToolbar' => $this->hasHeaderToolbar(),
                'collapseEmptyColumns' => $grouping === null
                    && $this->collapsesEmptyColumns()
                    && array_sum(array_column($columns, 'total')) > 0,
                'grouping' => $grouping?->getName(),
                'lanesWritable' => (bool) $grouping?->isWritable(),
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getColumnViewData(bool $withItems): array
    {
        $allCounts = $this->getBatchedBoardRecordCounts();
        $recordsByColumn = $withItems
            ? $this->getBoardRecordsForColumns(array_map(fn (Column $column): string => $column->getName(), $this->getColumns()))
            : [];
        $columns = [];

        foreach ($this->getColumns() as $column) {
            $columnId = $column->getName();
            $limit = $column->getLimit();
            $limitTotal = $limit === null ? null : $this->getUnfilteredColumnRecordCount($columnId);

            $columns[$columnId] = [
                'id' => $columnId,
                'label' => $column->getLabel(),
                'color' => $column->getColor(),
                'icon' => $column->getIcon(),
                'items' => isset($recordsByColumn[$columnId])
                    ? $recordsByColumn[$columnId]->map(fn ($record) => $this->formatBoardRecord($record))->toArray()
                    : [],
                'total' => $allCounts[$columnId] ?? 0,
                'summary' => $this->getColumnSummary($column),
                'limit' => $limit,
                'limitTotal' => $limitTotal,
                'isFull' => $limit !== null && $limitTotal >= $limit,
            ];
        }

        return $columns;
    }

    public function isColumnFull(string $columnId): bool
    {
        $limit = $this->getColumn($columnId)?->getLimit();

        if ($limit === null) {
            return false;
        }

        return $this->getUnfilteredColumnRecordCount($columnId) >= $limit;
    }

    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'livewire' => [$this->getLivewire()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
