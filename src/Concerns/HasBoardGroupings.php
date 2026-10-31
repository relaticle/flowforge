<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;

trait HasBoardGroupings
{
    /**
     * @var array<string, Grouping>
     */
    protected array $groupings = [];

    protected int $lanesPerPage = 10;

    public function groupings(array | Closure $groupings): static
    {
        $this->groupings = [];

        foreach ($this->evaluate($groupings) as $grouping) {
            $this->groupings[$grouping->getName()] = $grouping;
        }

        return $this;
    }

    /**
     * @return array<string, Grouping>
     */
    public function getGroupings(): array
    {
        $table = $this->getQuery()?->getModel()->getTable();

        foreach ($this->groupings as $grouping) {
            $grouping->boardTable($table);
        }

        return $this->groupings;
    }

    public function getGrouping(?string $name): ?Grouping
    {
        if (blank($name)) {
            return null;
        }

        return $this->getGroupings()[$name] ?? null;
    }

    public function getActiveGrouping(): ?Grouping
    {
        return $this->getGrouping($this->getLivewire()->groupBy ?? null);
    }

    public function lanesPerPage(int $count): static
    {
        $this->lanesPerPage = $count;

        return $this;
    }

    public function getLanesPerPage(): int
    {
        return $this->lanesPerPage;
    }

    /**
     * @return array<int, Lane>
     */
    public function getLanes(Grouping $grouping): array
    {
        $boardQuery = $this->getFilteredBoardQuery();

        return $boardQuery ? $grouping->getLanes($boardQuery) : [];
    }

    public function getLaneRecords(Lane $lane, int $perCellLimit): EloquentCollection
    {
        return $this->getLaneRecordsQuery($lane, $perCellLimit)?->get() ?? new EloquentCollection;
    }

    protected function getLaneRecordsQuery(Lane $lane, int $perCellLimit): ?Builder
    {
        $boardQuery = $this->getFilteredBoardQuery();

        if ($boardQuery === null) {
            return null;
        }

        $scoped = $lane->applyQuery($boardQuery)->applyScopes();
        $model = $scoped->getModel();
        $inner = $scoped->getQuery();

        $inner->reorder();
        $inner->limit = null;
        $inner->offset = null;

        if ($inner->columns === null) {
            $inner->select($model->qualifyColumn('*'));

            if (! $this->identifierBelongsToModel($model)) {
                $inner->addSelect($this->getColumnIdentifierAttribute());
            }
        }

        if ($inner->distinct) {
            $inner = $model->newModelQuery()->fromSub($inner, $model->getTable())->getQuery();
        }

        $grammar = $inner->getGrammar();
        $partition = $grammar->wrap($this->getColumnIdentifierAttribute());
        $order = collect([$this->getPositionIdentifierAttribute(), $model->getKeyName()])
            ->filter()
            ->map(fn (string $column): string => $grammar->wrap($model->qualifyColumn($column)))
            ->implode(', ');

        if ($inner->columns === null) {
            $inner->select($model->qualifyColumn('*'));
        }

        $inner->addSelect([
            DB::raw("row_number() over (partition by {$partition} order by {$order}) as flowforge_cell_row"),
            DB::raw("count(*) over (partition by {$partition}) as flowforge_cell_total"),
        ]);

        $outer = $model->newModelQuery()
            ->fromSub($inner, $model->getTable())
            ->where('flowforge_cell_row', '<=', $perCellLimit)
            ->orderBy('flowforge_cell_row');

        return $outer->setEagerLoads($scoped->getEagerLoads());
    }

    protected function identifierBelongsToModel(Model $model): bool
    {
        $identifier = (string) $this->getColumnIdentifierAttribute();

        if (str_contains($identifier, '.')) {
            return Str::beforeLast($identifier, '.') === $model->getTable();
        }

        return $this->modelHasColumn($model, $identifier);
    }

    protected function laneHasCards(Lane $lane): bool
    {
        $boardQuery = $this->getFilteredBoardQuery();

        return $boardQuery !== null
            && $lane->applyQuery($boardQuery)
                ->reorder()
                ->whereIn($this->getColumnIdentifierAttribute(), $this->getColumnIdentifiers())
                ->exists();
    }

    /**
     * @param  array<string, array<string, int>>  $cellCardLimits
     * @return array{lanes: array<int, array<string, mixed>>, hasMoreLanes: bool}
     */
    public function getLaneViewData(Grouping $grouping, int $visibleLaneCount, array $cellCardLimits): array
    {
        $columnIds = $this->getColumnIdentifiers();
        $eagerLoads = [];
        $visibleLanes = [];
        $hasMoreLanes = false;

        foreach ($this->getLanes($grouping) as $lane) {
            if (count($visibleLanes) >= $visibleLaneCount) {
                if ($this->laneHasCards($lane)) {
                    $hasMoreLanes = true;

                    break;
                }

                continue;
            }

            $cellLimits = $cellCardLimits[$lane->getName()] ?? [];
            $query = $this->getLaneRecordsQuery($lane, max([$this->getCardsPerColumn(), ...array_values($cellLimits)]));

            if ($query === null) {
                continue;
            }

            $eagerLoads = $query->getEagerLoads();
            $records = $query->setEagerLoads([])->get();

            if ($records->contains(fn (Model $record): bool => in_array($this->getRecordColumnId($record), $columnIds, true))) {
                $visibleLanes[] = [$lane, $records, $cellLimits];
            }
        }

        if ($eagerLoads !== []) {
            (new EloquentCollection(array_merge(...array_map(
                fn (array $visibleLane): array => $visibleLane[1]->all(),
                $visibleLanes,
            ))))->load($eagerLoads);
        }

        return [
            'lanes' => array_map(fn (array $visibleLane): array => $this->formatLane(...$visibleLane), $visibleLanes),
            'hasMoreLanes' => $hasMoreLanes,
        ];
    }

    /**
     * @param  EloquentCollection<int, Model>  $records
     * @param  array<string, int>  $cellLimits
     * @return array<string, mixed>
     */
    protected function formatLane(Lane $lane, EloquentCollection $records, array $cellLimits): array
    {
        $cells = [];

        foreach ($this->getColumnIdentifiers() as $columnId) {
            $cellRecords = $records
                ->filter(fn (Model $record): bool => $this->getRecordColumnId($record) === $columnId)
                ->values();

            $cells[$columnId] = [
                'items' => $cellRecords
                    ->take($cellLimits[$columnId] ?? $this->getCardsPerColumn())
                    ->map(fn (Model $record): array => $this->formatBoardRecord($record))
                    ->all(),
                'total' => (int) ($cellRecords->first()?->getAttribute('flowforge_cell_total') ?? 0),
            ];
        }

        return [
            'id' => $lane->getName(),
            'label' => $lane->getLabel(),
            'avatar' => $lane->getAvatar(),
            'color' => $lane->getColor(),
            'icon' => $lane->getIcon(),
            'total' => array_sum(array_column($cells, 'total')),
            'cells' => $cells,
        ];
    }
}
