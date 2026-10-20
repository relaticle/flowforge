<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Table;

/**
 * Bridges Board filter configuration to Filament's Table.
 *
 * The Board stores filter configuration via HasBoardFilters (which uses Filament's HasFilters trait).
 * This trait passes all that configuration to the actual Table component.
 */
trait InteractsWithBoardTable
{
    use InteractsWithTable;

    public function table(Table $table): Table
    {
        $board = $this->getBoard();

        $searchableColumns = collect($board->getSearchableFields())
            ->map(fn ($field) => Column::make($field)->searchable())
            ->toArray();

        $table = $table
            ->queryStringIdentifier('board')
            ->query($board->getQuery())
            ->columns($searchableColumns)
            ->filters($board->getBoardFilters())
            ->filtersFormWidth($board->getFiltersFormWidth())
            ->filtersFormColumns($board->getFiltersFormColumns())
            ->filtersFormMaxHeight($board->getFiltersFormMaxHeight())
            ->filtersLayout($board->getFiltersLayout())
            ->filtersResetActionPosition($board->getFiltersResetActionPosition())
            ->deferFilters($board->hasDeferredFilters())
            ->persistFiltersInSession($board->persistsFiltersInSession())
            ->deselectAllRecordsWhenFiltered($board->shouldDeselectAllRecordsWhenFiltered());

        if ($triggerModifier = $board->getFiltersTriggerActionModifier()) {
            $table->filtersTriggerAction($triggerModifier);
        }

        if ($applyModifier = $board->getFiltersApplyActionModifier()) {
            $table->filtersApplyAction($applyModifier);
        }

        if ($resetModifier = $board->getFiltersResetActionModifier()) {
            $table->filtersResetAction($resetModifier);
        }

        return $table;
    }

    protected function getTableQueryStringIdentifier(): ?string
    {
        return 'board';
    }
}
