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
use Relaticle\Flowforge\Concerns\HasCardLabels;
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
    use HasCardLabels;
    use HasCardSchema;
    use InteractsWithKanbanQuery;

    protected string $view = 'flowforge::index';

    protected string $viewIdentifier = 'board';

    protected string $evaluationIdentifier = 'board';

    protected bool $headerToolbar = false;

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
        // Batch all column counts in a single query
        $allCounts = $this->getBatchedBoardRecordCounts();

        // Build columns data using new concerns
        $columns = [];
        foreach ($this->getColumns() as $column) {
            $columnId = $column->getName();

            // Get formatted records
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
            'config' => [
                'recordTitleAttribute' => $this->getRecordTitleAttribute(),
                'columnIdentifierAttribute' => $this->getColumnIdentifierAttribute(),
                'cardLabel' => $this->getCardLabel(),
                'pluralCardLabel' => $this->getPluralCardLabel(),
                'headerToolbar' => $this->hasHeaderToolbar(),
            ],
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
