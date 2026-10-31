<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use BackedEnum;
use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Enterprise-grade record management for Board with cursor-based pagination.
 * Implements position-based ordering for optimal performance at scale.
 */
trait HasBoardRecords
{
    protected string $recordTitleAttribute = 'title';

    protected int $cardsPerColumn = 20;

    protected bool $stringColumnIds = false;

    protected array $columnExistenceCache = [];

    /**
     * Set the record title attribute.
     */
    public function recordTitleAttribute(string $attribute): static
    {
        $this->recordTitleAttribute = $attribute;

        return $this;
    }

    /**
     * Get the record title attribute.
     */
    public function getRecordTitleAttribute(): string
    {
        return $this->recordTitleAttribute;
    }

    /**
     * Set the number of cards per column.
     */
    public function cardsPerColumn(int $count): static
    {
        $this->cardsPerColumn = $count;

        return $this;
    }

    public function stringColumnIds(bool $condition = true): static
    {
        $this->stringColumnIds = $condition;

        return $this;
    }

    /**
     * Get the number of cards per column.
     */
    public function getCardsPerColumn(): int
    {
        return $this->cardsPerColumn;
    }

    public function getFilteredBoardQuery(): ?Builder
    {
        $query = $this->getQuery();

        if (! $query) {
            return null;
        }

        $livewire = $this->getLivewire();

        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            return clone $livewire->getFilteredTableQuery();
        }

        return clone $query;
    }

    public function getFilteredColumnQuery(string $columnId): ?Builder
    {
        return $this->getFilteredBoardQuery()?->where($this->getColumnIdentifierAttribute(), $columnId);
    }

    public function getUnfilteredColumnRecordCount(string $columnId): int
    {
        $query = $this->getQuery();

        if (! $query) {
            return 0;
        }

        return (clone $query)->reorder()->where($this->getColumnIdentifierAttribute(), $columnId)->count();
    }

    public function getRecordColumnId(Model $record): string
    {
        $value = data_get($record, $this->getColumnIdentifierAttribute());

        return match (true) {
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof UnitEnum => $value->name,
            default => (string) $value,
        };
    }

    /**
     * Get records for a specific column with cursor-based pagination.
     * Uses position field for optimal performance with large datasets.
     */
    public function getBoardRecords(string $columnId): Collection
    {
        return $this->getBoardRecordsQuery($columnId)?->get() ?? new Collection;
    }

    /**
     * Get the records of several columns, running the query's eager loads once across all
     * of them instead of once per column.
     *
     * @param  array<int, string>  $columnIds
     * @return array<string, Collection>
     */
    public function getBoardRecordsForColumns(array $columnIds): array
    {
        $eagerLoads = [];
        $recordsByColumn = [];

        foreach ($columnIds as $columnId) {
            $query = $this->getBoardRecordsQuery($columnId);

            if (! $query) {
                $recordsByColumn[$columnId] = new Collection;

                continue;
            }

            $eagerLoads = $query->getEagerLoads();
            $recordsByColumn[$columnId] = $query->setEagerLoads([])->get();
        }

        if ($eagerLoads !== []) {
            (new Collection(array_merge(...array_map(
                fn (Collection $records): array => $records->all(),
                array_values($recordsByColumn),
            ))))->load($eagerLoads);
        }

        return $recordsByColumn;
    }

    protected function getBoardRecordsQuery(string $columnId): ?Builder
    {
        $queryClone = $this->getFilteredColumnQuery($columnId);

        if (! $queryClone) {
            return null;
        }

        $livewire = $this->getLivewire();

        $limit = property_exists($livewire, 'columnCardLimits')
            ? ($livewire->columnCardLimits[$columnId] ?? $this->getCardsPerColumn())
            : $this->getCardsPerColumn();

        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $queryClone->getModel()->getKeyName();

        if ($positionField && $this->modelHasColumn($queryClone->getModel(), $positionField)) {
            $queryClone->orderBy($positionField, 'asc')
                ->orderBy($keyName, 'asc'); // Tie-breaker for deterministic order
        }

        return $queryClone->limit($limit);
    }

    /**
     * Get record count for a column (direct query with filters).
     */
    public function getBoardRecordCount(string $columnId): int
    {
        return $this->getFilteredColumnQuery($columnId)?->reorder()->count() ?? 0;
    }

    /**
     * Get record counts for all columns in a single query using GROUP BY.
     */
    public function getBatchedBoardRecordCounts(): array
    {
        $queryClone = $this->getFilteredBoardQuery();

        if (! $queryClone) {
            return [];
        }

        $statusField = $this->getColumnIdentifierAttribute();

        return $queryClone
            ->reorder()
            ->select(DB::raw("{$statusField} as column_value, COUNT(*) as total"))
            ->groupBy($statusField)
            ->pluck('total', 'column_value')
            ->toArray();
    }

    /**
     * Format a record for display with Infolist entries.
     */
    public function formatBoardRecord(Model $record): array
    {
        $formatted = [
            'id' => (string) $record->getKey(),
            'title' => data_get($record, $this->getRecordTitleAttribute()),
            'column' => $this->stringColumnIds
                ? $this->getRecordColumnId($record)
                : data_get($record, $this->getColumnIdentifierAttribute()),
            'position' => data_get($record, $this->getPositionIdentifierAttribute()),
            'model' => $record,
        ];

        // Process card schema if available
        $formatted['schema'] = null;
        $schema = $this->getCardSchema($record);

        if ($schema !== null) {
            // The schema is already built and configured
            $schema->model($record);

            // Store the schema object with record context for proper Livewire rendering
            $formatted['schema'] = $schema;
        }

        return $formatted;
    }

    /**
     * Check if model has the specified column.
     */
    protected function modelHasColumn($model, string $columnName): bool
    {
        $cacheKey = get_class($model) . '::' . $model->getTable() . '::' . $columnName;

        if (! isset($this->columnExistenceCache[$cacheKey])) {
            try {
                $table = $model->getTable();
                $schema = $model->getConnection()->getSchemaBuilder();
                $this->columnExistenceCache[$cacheKey] = $schema->hasColumn($table, $columnName);
            } catch (Exception) {
                $this->columnExistenceCache[$cacheKey] = false;
            }
        }

        return $this->columnExistenceCache[$cacheKey];
    }

    /**
     * Get records before a specific position (for inserting cards).
     */
    public function getRecordsBeforePosition(string $columnId, string $position, int $limit = 5): Collection
    {
        $query = $this->getQuery();

        if (! $query) {
            return new Collection;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $query->getModel()->getKeyName();

        return (clone $query)
            ->where($statusField, $columnId)
            ->where($positionField, '<', $position)
            ->orderBy($positionField, 'desc')
            ->orderBy($keyName, 'desc') // Tie-breaker for deterministic order
            ->limit($limit)
            ->get();
    }

    /**
     * Get records after a specific position (for inserting cards).
     */
    public function getRecordsAfterPosition(string $columnId, string $position, int $limit = 5): Collection
    {
        $query = $this->getQuery();

        if (! $query) {
            return new Collection;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $query->getModel()->getKeyName();

        return (clone $query)
            ->where($statusField, $columnId)
            ->where($positionField, '>', $position)
            ->orderBy($positionField, 'asc')
            ->orderBy($keyName, 'asc') // Tie-breaker for deterministic order
            ->limit($limit)
            ->get();
    }

    /**
     * Get the last position in a column.
     */
    public function getLastPositionInColumn(string $columnId): ?string
    {
        $query = $this->getQuery();

        if (! $query) {
            return null;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $query->getModel()->getKeyName();

        $record = (clone $query)
            ->where($statusField, $columnId)
            ->orderBy($positionField, 'desc')
            ->orderBy($keyName, 'desc') // Tie-breaker for deterministic order
            ->first();

        return $record?->getAttribute($positionField);
    }
}
