<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use BackedEnum;
use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Relaticle\Flowforge\Column;

trait HasColumnControls
{
    /** @var array<int, mixed>|Closure */
    protected array | Closure $collapsedColumns = [];

    protected bool | Closure $collapseAllColumns = false;

    protected ?Closure $toggleColumnCollapse = null;

    protected ?Closure $columnHeaderMenu = null;

    protected ?Closure $addColumn = null;

    protected string | Closure | null $addColumnLabel = null;

    protected string | Closure | null $addColumnPlaceholder = null;

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

    public function columnHeaderMenu(?Closure $using): static
    {
        $this->columnHeaderMenu = $using;

        return $this;
    }

    public function hasColumnHeaderMenu(): bool
    {
        return $this->columnHeaderMenu instanceof Closure;
    }

    /**
     * @param  array<string, mixed>  $column
     */
    public function getColumnHeaderMenuBlock(array $column): ?Htmlable
    {
        if (! $this->columnHeaderMenu instanceof Closure) {
            return null;
        }

        return $this->evaluate($this->columnHeaderMenu, ['column' => $column]);
    }

    public function addColumnUsing(?Closure $using): static
    {
        $this->addColumn = $using;

        return $this;
    }

    public function addColumnLabel(string | Closure | null $label): static
    {
        $this->addColumnLabel = $label;

        return $this;
    }

    public function addColumnPlaceholder(string | Closure | null $placeholder): static
    {
        $this->addColumnPlaceholder = $placeholder;

        return $this;
    }

    public function canAddColumns(): bool
    {
        return $this->addColumn instanceof Closure;
    }

    public function getAddColumnLabel(): string
    {
        $label = $this->evaluate($this->addColumnLabel);

        return filled($label) ? (string) $label : __('flowforge::flowforge.add_column');
    }

    public function getAddColumnPlaceholder(): string
    {
        $placeholder = $this->evaluate($this->addColumnPlaceholder);

        return filled($placeholder) ? (string) $placeholder : __('flowforge::flowforge.column_name');
    }

    public function addColumn(string $name): void
    {
        if (! $this->addColumn instanceof Closure) {
            return;
        }

        $this->evaluate($this->addColumn, ['name' => $name]);
    }
}
