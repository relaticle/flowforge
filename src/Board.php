<?php

declare(strict_types=1);

namespace Relaticle\Flowforge;

use Filament\Support\Components\ViewComponent;
use Relaticle\Flowforge\Concerns\BelongsToLivewire;
use Relaticle\Flowforge\Concerns\CanSearchBoardRecords;
use Relaticle\Flowforge\Concerns\HasBoardActions;
use Relaticle\Flowforge\Concerns\HasBoardColumns;
use Relaticle\Flowforge\Concerns\HasBoardFilters;
use Relaticle\Flowforge\Concerns\HasBoardRecords;
use Relaticle\Flowforge\Concerns\HasBoardSwimlanes;
use Relaticle\Flowforge\Concerns\HasCardSchema;
use Relaticle\Flowforge\Concerns\InteractsWithKanbanQuery;
use Relaticle\Flowforge\Contracts\HasBoard;

class Board extends ViewComponent
{
    use BelongsToLivewire;
    use CanSearchBoardRecords;
    use HasBoardActions;
    use HasBoardColumns;
    use HasBoardFilters;
    use HasBoardRecords;
    use HasBoardSwimlanes;
    use HasCardSchema;
    use InteractsWithKanbanQuery;

    protected string $view = 'flowforge::index';

    protected string $viewIdentifier = 'board';

    protected string $evaluationIdentifier = 'board';

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

    protected function setUp(): void
    {
        parent::setUp();

        // Any board-specific setup can go here
    }

    /**
     * Get view data for the board template.
     * Delegates to Livewire component like Filament's Table does.
     *
     * When swimlanes are configured, returns a 2D structure:
     *   columns = header definitions (no items), swimlanes = rows with cells.
     * When no swimlanes, returns the flat column structure (unchanged).
     */
    public function getViewData(): array
    {
        $config = [
            'recordTitleAttribute' => $this->getRecordTitleAttribute(),
            'columnIdentifierAttribute' => $this->getColumnIdentifierAttribute(),
            'cardLabel' => __('flowforge::flowforge.card_label'),
            'pluralCardLabel' => __('flowforge::flowforge.plural_card_label'),
        ];

        if ($this->hasSwimlanes()) {
            return $this->getViewDataWithSwimlanes($config);
        }

        return $this->getViewDataFlat($config);
    }

    /**
     * Build the flat (no-swimlane) view data — original behavior.
     */
    protected function getViewDataFlat(array $config): array
    {
        $allCounts = $this->getBatchedBoardRecordCounts();

        $columns = [];
        foreach ($this->getColumns() as $column) {
            $columnId = $column->getName();

            $records = $this->getBoardRecords($columnId);
            $formattedRecords = $records->map(fn ($record) => $this->formatBoardRecord($record))->toArray();

            $columns[$columnId] = [
                'id' => $columnId,
                'label' => $column->getLabel(),
                'color' => $column->getColor(),
                'icon' => $column->getIcon(),
                'items' => $formattedRecords,
                'total' => $allCounts[$columnId] ?? 0,
            ];
        }

        return [
            'columns' => $columns,
            'swimlanes' => null,
            'config' => $config,
        ];
    }

    /**
     * Build the 2D swimlane view data: columns (headers only) × swimlanes (rows with cells).
     */
    protected function getViewDataWithSwimlanes(array $config): array
    {
        $config['swimlaneIdentifierAttribute'] = $this->getSwimlaneIdentifierAttribute();

        // Column header definitions (no items — those live in cells)
        $columnHeaders = [];
        foreach ($this->getColumns() as $column) {
            $columnHeaders[$column->getName()] = [
                'id' => $column->getName(),
                'label' => $column->getLabel(),
                'color' => $column->getColor(),
                'icon' => $column->getIcon(),
            ];
        }

        // Batch all cell counts in one query
        $cellCounts = $this->getBatchedSwimlaneRecordCounts();

        // Batch the cell RECORDS in one query too.
        //
        // The per-cell fetch below used to call getBoardRecordsForCell() once for
        // every column x swimlane intersection, including cells whose count it had
        // just read as zero on the line above. On a board with 39 columns and 4
        // swimlanes that is 156 queries to render 31 cards, 150 of them against
        // cells already known to be empty.
        //
        // It is worse than the count suggests. Consumers commonly build the board
        // query over a derived table whose grouping columns are computed aliases,
        // so the per-cell WHERE cannot use an index and each query re-runs the whole
        // projection. Measured on one such board: 210 queries and 653ms of database
        // time before, 50 queries and 22ms after, for byte-identical output.
        $statusField = $this->getColumnIdentifierAttribute();
        $swimlaneField = $this->getSwimlaneIdentifierAttribute();
        $livewire = $this->getLivewire();

        $baseQuery = ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch())
            ? clone $livewire->getFilteredTableQuery()
            : clone $this->getQuery();

        // Mirrors the ordering getBoardRecordsForCell() applies, so grouping the
        // single result set yields the same order the per-cell queries did.
        $positionField = $this->getPositionIdentifierAttribute();
        if ($positionField && $this->modelHasColumn($baseQuery->getModel(), $positionField)) {
            $baseQuery->orderBy($positionField, 'asc')
                ->orderBy($baseQuery->getModel()->getKeyName(), 'asc');
        }

        $recordsByCell = $baseQuery->get()->groupBy(
            fn ($record) => $record->{$statusField} . '|' . $record->{$swimlaneField}
        );

        // Build swimlane rows with cells
        $swimlaneData = [];
        $columnIds = array_keys($columnHeaders);

        foreach ($this->getSwimlanes() as $swimlane) {
            $swimlaneId = $swimlane->getName();
            $laneTotal = 0;
            $cells = [];

            foreach ($columnIds as $columnId) {
                $cellKey = $columnId . '|' . $swimlaneId;
                $cellCount = $cellCounts[$cellKey] ?? 0;
                $laneTotal += $cellCount;

                // Same per-cell limit getBoardRecordsForCell() would have applied,
                // including the load-more override, but taken from the batch.
                $cellLimit = property_exists($livewire, 'columnCardLimits')
                    ? ($livewire->columnCardLimits[$cellKey] ?? $this->getCardsPerColumn())
                    : $this->getCardsPerColumn();

                $records = ($recordsByCell[$cellKey] ?? collect())->take($cellLimit);
                $formattedRecords = $records->map(fn ($record) => $this->formatBoardRecord($record))->values()->toArray();

                $cells[$columnId] = [
                    'items' => $formattedRecords,
                    'total' => $cellCount,
                ];
            }

            $swimlaneData[$swimlaneId] = [
                'id' => $swimlaneId,
                'label' => $swimlane->getLabel(),
                'color' => $swimlane->getColor(),
                'icon' => $swimlane->getIcon(),
                'total' => $laneTotal,
                'cells' => $cells,
            ];
        }

        // Check for uncategorized records (cards whose swimlane value doesn't match any defined swimlane)
        $definedSwimlaneIds = $this->getSwimlaneIdentifiers();
        $uncategorizedTotal = 0;
        $uncategorizedCells = [];

        foreach ($columnIds as $columnId) {
            $cellKey = $columnId . '|__uncategorized__';
            $cellCount = $cellCounts[$cellKey] ?? 0;

            // Also check for values that don't match any defined swimlane
            foreach ($cellCounts as $key => $count) {
                if (! str_starts_with($key, $columnId . '|')) {
                    continue;
                }
                $laneId = substr($key, strlen($columnId) + 1);
                if ($laneId !== '__uncategorized__' && ! in_array($laneId, $definedSwimlaneIds, true)) {
                    $cellCount += $count;
                }
            }

            if ($cellCount > 0) {
                $uncategorizedTotal += $cellCount;
                $uncategorizedCells[$columnId] = [
                    'items' => [], // Uncategorized cards loaded on-demand if needed
                    'total' => $cellCount,
                ];
            }
        }

        if ($uncategorizedTotal > 0) {
            $swimlaneData['__uncategorized__'] = [
                'id' => '__uncategorized__',
                'label' => __('flowforge::flowforge.uncategorized'),
                'color' => 'gray',
                'icon' => null,
                'total' => $uncategorizedTotal,
                'cells' => $uncategorizedCells,
            ];
        }

        return [
            'columns' => $columnHeaders,
            'swimlanes' => $swimlaneData,
            'config' => $config,
        ];
    }

    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'livewire' => [$this->getLivewire()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
