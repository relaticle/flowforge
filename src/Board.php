<?php

declare(strict_types=1);

namespace Relaticle\Flowforge;

use Closure;
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
use Relaticle\Flowforge\Concerns\HasColumnControls;
use Relaticle\Flowforge\Concerns\HasColumnSummaries;
use Relaticle\Flowforge\Concerns\InteractsWithKanbanQuery;
use Relaticle\Flowforge\Contracts\HasBoard;
use Relaticle\Flowforge\Support\ColorResolver;

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
    use HasColumnControls;
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
                array_values(array_map(
                    fn (array $column): string => (string) $column['id'],
                    array_filter($columns, fn (array $column): bool => $column['isCollapsed']),
                )),
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
                'collapsible' => $this->isColumnCollapsible(),
                'canAddColumns' => $this->canAddColumns(),
                'addColumnLabel' => $this->getAddColumnLabel(),
                'addColumnPlaceholder' => $this->getAddColumnPlaceholder(),
                'addColumnColorLabel' => $this->getAddColumnColorLabel(),
                'addColumnColors' => array_map(
                    fn (string $value, string $label): array => ['value' => $value, 'label' => $label, 'style' => ColorResolver::dotStyle($value)],
                    array_keys($this->getAddColumnColors()),
                    $this->getAddColumnColors(),
                ),
                'addColumnDefaultColor' => $this->getAddColumnDefaultColor(),
                'collapseEmptyColumns' => $grouping === null
                    && $this->collapsesEmptyColumns()
                    && array_sum(array_column($columns, 'total')) > 0,
                'grouping' => $grouping?->getName(),
                'lanesWritable' => (bool) $grouping?->isWritable(),
            ],
        ];
    }

    /**
     * @return array{collapsed: bool, toggleable: bool, byAll: bool}
     */
    public function getColumnCollapseState(string $columnId): array
    {
        return $this->makeCollapseResolver($this->getBatchedBoardRecordCounts())($columnId);
    }

    /**
     * @param  array<string, int>  $allCounts
     * @return Closure(string): array{collapsed: bool, toggleable: bool, byAll: bool}
     */
    protected function makeCollapseResolver(array $allCounts): Closure
    {
        $listed = $this->getCollapsedColumns();
        $visible = $this->getColumnIdentifiers();
        $collapseAll = $this->collapsesAllColumns();
        $collapseEmpty = $this->collapsesEmptyColumns()
            && $this->getActiveGrouping() === null
            && array_sum(array_map(fn (Column $column): int => $allCounts[$column->getName()] ?? 0, $this->getColumns())) > 0;

        return function (string $columnId) use ($listed, $visible, $collapseAll, $collapseEmpty, $allCounts): array {
            $byEmptyRule = ! $collapseAll && $collapseEmpty && ($allCounts[$columnId] ?? 0) === 0;
            $isListed = in_array($columnId, $listed, true);
            $collapsed = $collapseAll || $byEmptyRule || $isListed;

            return [
                'collapsed' => $collapsed,
                'toggleable' => $this->isColumnCollapsible()
                    && in_array($columnId, $visible, true)
                    && ! $byEmptyRule,
                'byAll' => $collapseAll,
            ];
        };
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getColumnViewData(bool $withItems): array
    {
        $allCounts = $this->getBatchedBoardRecordCounts();
        $isCollapsed = $this->makeCollapseResolver($allCounts);
        $recordsByColumn = $withItems
            ? $this->getBoardRecordsForColumns(array_values(array_filter(
                array_map(fn (Column $column): string => $column->getName(), $this->getColumns()),
                fn (string $columnId): bool => ! $isCollapsed($columnId)['collapsed'],
            )))
            : [];
        $columns = [];

        foreach ($this->getColumns() as $column) {
            $columnId = $column->getName();
            $limit = $column->getLimit();
            $limitTotal = $limit === null ? null : $this->getUnfilteredColumnRecordCount($columnId);
            $total = $allCounts[$columnId] ?? 0;
            $state = $isCollapsed($columnId);

            $columns[$columnId] = [
                'id' => $columnId,
                'label' => $column->getLabel(),
                'color' => $column->getColor(),
                'icon' => $column->getIcon(),
                'items' => isset($recordsByColumn[$columnId])
                    ? $recordsByColumn[$columnId]->map(fn ($record) => $this->formatBoardRecord($record))->toArray()
                    : [],
                'total' => $total,
                'isCollapsed' => $state['collapsed'],
                'canToggleCollapse' => $state['toggleable'],
                'isCollapsedByAll' => $state['byAll'],
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
