<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Closure;
use Relaticle\Flowforge\Column;

trait HasColumnControls
{
    /** @var array<int, mixed>|Closure */
    protected array | Closure $collapsedColumns = [];

    protected bool | Closure $collapseAllColumns = false;

    /**
     * Collapse the listed columns into a rail. The host owns the list.
     *
     * @param  array<int, mixed>|Closure  $columnIds
     */
    public function collapsedColumns(array | Closure $columnIds): static
    {
        $this->collapsedColumns = $columnIds;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCollapsedColumns(): array
    {
        $requested = array_map(
            fn (mixed $columnId): string => (string) $columnId,
            (array) $this->evaluate($this->collapsedColumns),
        );

        $visible = array_map(fn (Column $column): string => $column->getName(), $this->getColumns());

        return array_values(array_intersect($visible, $requested));
    }

    public function collapseAllColumns(bool | Closure $condition = true): static
    {
        $this->collapseAllColumns = $condition;

        return $this;
    }

    public function collapsesAllColumns(): bool
    {
        return (bool) $this->evaluate($this->collapseAllColumns);
    }
}
