<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Relaticle\Flowforge\Column;

trait HasColumnControls
{
    /** @var array<int, mixed>|Closure */
    protected array | Closure $collapsedColumns = [];

    protected bool | Closure $collapseAllColumns = false;

    protected ?Closure $toggleColumnCollapse = null;

    /**
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
        $evaluated = $this->evaluate($this->collapsedColumns);
        $requested = array_map(
            fn (mixed $columnId): string => (string) ($columnId instanceof BackedEnum ? $columnId->value : $columnId),
            array_filter(
                $evaluated instanceof Arrayable ? $evaluated->toArray() : (array) $evaluated,
                fn (mixed $columnId): bool => is_scalar($columnId) || $columnId instanceof BackedEnum,
            ),
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

    public function collapsibleColumns(?Closure $using): static
    {
        $this->toggleColumnCollapse = $using;

        return $this;
    }

    public function isColumnCollapsible(): bool
    {
        return $this->toggleColumnCollapse instanceof Closure;
    }

    public function toggleColumnCollapse(string $columnId): void
    {
        $state = $this->getColumnCollapseState($columnId);

        if (! $state['toggleable']) {
            return;
        }

        $this->evaluate($this->toggleColumnCollapse, [
            'columnId' => $columnId,
            'collapsed' => ! $state['collapsed'],
        ]);
    }
}
