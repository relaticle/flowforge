<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Closure;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\BaseFilter;
use Filament\Tables\Table\Concerns\HasFilters;

/**
 * Provides filter configuration for boards by extending Filament's HasFilters trait.
 *
 * We override only the methods that assume $this is a Table (since Board is a ViewComponent).
 * All other configuration methods (filtersFormColumns, filtersLayout, etc.) work directly
 * from the parent trait.
 */
trait HasBoardFilters
{
    use HasFilters {
        filters as filamentFilters;
    }

    /**
     * Override filters() to not call $filter->table($this) since Board is not a Table.
     * The actual table binding happens in InteractsWithBoardTable when filters are passed to the Table.
     *
     * @param  array<BaseFilter>|Closure  $filters
     */
    public function filters(array | Closure $filters, FiltersLayout | string | Closure | null $layout = null): static
    {
        $this->filters = [];

        $evaluatedFilters = $this->evaluate($filters);

        foreach ($evaluatedFilters as $filter) {
            $this->filters[$filter->getName()] = $filter;
        }

        if ($layout !== null) {
            $this->filtersLayout($layout);
        }

        return $this;
    }

    /**
     * Get filters for board configuration.
     * Alias for consistency with existing board API.
     *
     * @return array<string, BaseFilter>
     */
    public function getBoardFilters(): array
    {
        return $this->filters;
    }

    /**
     * Check if the board has filters defined.
     */
    public function hasBoardFilters(): bool
    {
        return ! empty($this->filters);
    }

    /**
     * Get the callback to modify the filters trigger action.
     */
    public function getFiltersTriggerActionModifier(): ?Closure
    {
        return $this->modifyFiltersTriggerActionUsing;
    }

    /**
     * Get the callback to modify the filters apply action.
     */
    public function getFiltersApplyActionModifier(): ?Closure
    {
        return $this->modifyFiltersApplyActionUsing;
    }

    public function getFiltersResetActionModifier(): ?Closure
    {
        return $this->modifyFiltersResetActionUsing;
    }
}
