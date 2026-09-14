<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Enterprise-grade record management for Board with cursor-based pagination.
 * Implements position-based ordering for optimal performance at scale.
 */
trait HasBoardRecords
{
    protected string $recordTitleAttribute = 'title';

    protected int $cardsPerColumn = 20;

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

    /**
     * Get the number of cards per column.
     */
    public function getCardsPerColumn(): int
    {
        return $this->cardsPerColumn;
    }

    /**
     * Get records for a specific column with cursor-based pagination.
     * Uses position field for optimal performance with large datasets.
     */
    public function getBoardRecords(string $columnId): Collection
    {
        $query = $this->getQuery();

        if (! $query) {
            return new Collection;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $livewire = $this->getLivewire();

        $limit = property_exists($livewire, 'columnCardLimits')
            ? ($livewire->columnCardLimits[$columnId] ?? $this->getCardsPerColumn())
            : $this->getCardsPerColumn();

        $queryClone = (clone $query)->where($statusField, $columnId);

        // Apply table filters and search using Filament's native system
        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            $baseQuery = $livewire->getFilteredTableQuery();
            $queryClone = (clone $baseQuery)->where($statusField, $columnId);
        }

        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $queryClone->getModel()->getKeyName();

        if ($positionField && $this->modelHasColumn($queryClone->getModel(), $positionField)) {
            $queryClone->orderBy($positionField, 'asc')
                ->orderBy($keyName, 'asc'); // Tie-breaker for deterministic order
        }

        return $queryClone->limit($limit)->get();
    }

    /**
     * Get record count for a column (direct query with filters).
     */
    public function getBoardRecordCount(string $columnId): int
    {
        $query = $this->getQuery();

        if (! $query) {
            return 0;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $queryClone = (clone $query)->where($statusField, $columnId);

        // Apply table filters and search using Filament's native system
        $livewire = $this->getLivewire();
        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            $baseQuery = $livewire->getFilteredTableQuery();
            $queryClone = (clone $baseQuery)->where($statusField, $columnId);
        }

        return $queryClone->count();
    }

    /**
     * Get record counts for all columns in a single query using GROUP BY.
     */
    public function getBatchedBoardRecordCounts(): array
    {
        $query = $this->getQuery();

        if (! $query) {
            return [];
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $livewire = $this->getLivewire();

        $queryClone = clone $query;

        // Apply table filters and search using Filament's native system
        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            $baseQuery = $livewire->getFilteredTableQuery();
            $queryClone = clone $baseQuery;
        }

        return $queryClone
            ->select(DB::raw("{$statusField} as column_value, COUNT(*) as total"))
            ->groupBy($statusField)
            ->pluck('total', 'column_value')
            ->toArray();
    }

    /**
     * Get records for a specific cell (column × swimlane intersection) with cursor-based pagination.
     */
    public function getBoardRecordsForCell(string $columnId, string $swimlaneId): Collection
    {
        $query = $this->getQuery();

        if (! $query) {
            return new Collection;
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $swimlaneField = $this->getSwimlaneIdentifierAttribute();
        $livewire = $this->getLivewire();

        $cellKey = $columnId . '|' . $swimlaneId;
        $limit = property_exists($livewire, 'columnCardLimits')
            ? ($livewire->columnCardLimits[$cellKey] ?? $this->getCardsPerColumn())
            : $this->getCardsPerColumn();

        $queryClone = (clone $query)
            ->where($statusField, $columnId)
            ->where($swimlaneField, $swimlaneId);

        // Apply table filters and search using Filament's native system
        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            $baseQuery = $livewire->getFilteredTableQuery();
            $queryClone = (clone $baseQuery)
                ->where($statusField, $columnId)
                ->where($swimlaneField, $swimlaneId);
        }

        $positionField = $this->getPositionIdentifierAttribute();
        $keyName = $queryClone->getModel()->getKeyName();

        if ($positionField && $this->modelHasColumn($queryClone->getModel(), $positionField)) {
            $queryClone->orderBy($positionField, 'asc')
                ->orderBy($keyName, 'asc');
        }

        return $queryClone->limit($limit)->get();
    }

    /**
     * Get record counts for all column × swimlane cells in a single query using GROUP BY.
     *
     * @return array<string, int> Keyed by "columnId|swimlaneId" => count
     */
    public function getBatchedSwimlaneRecordCounts(): array
    {
        $query = $this->getQuery();

        if (! $query) {
            return [];
        }

        $statusField = $this->getColumnIdentifierAttribute();
        $swimlaneField = $this->getSwimlaneIdentifierAttribute();
        $livewire = $this->getLivewire();

        $queryClone = clone $query;

        // Apply table filters and search using Filament's native system
        if ($livewire->getTable()->isFilterable() || $livewire->hasTableSearch()) {
            $baseQuery = $livewire->getFilteredTableQuery();
            $queryClone = clone $baseQuery;
        }

        $rows = $queryClone
            ->select(DB::raw("{$statusField} as column_value, {$swimlaneField} as swimlane_value, COUNT(*) as total"))
            ->groupBy($statusField, $swimlaneField)
            ->get();

        $counts = [];
        foreach ($rows as $row) {
            $key = $row->column_value . '|' . ($row->swimlane_value ?? '__uncategorized__');
            $counts[$key] = (int) $row->total;
        }

        return $counts;
    }

    /**
     * Format a record for display with Infolist entries.
     */
    public function formatBoardRecord(Model $record): array
    {
        $formatted = [
            'id' => (string) $record->getKey(),
            'title' => data_get($record, $this->getRecordTitleAttribute()),
            'column' => data_get($record, $this->getColumnIdentifierAttribute()),
            'position' => data_get($record, $this->getPositionIdentifierAttribute()),
            'model' => $record,
        ];

        // Process card header schema if available
        $formatted['headerSchema'] = null;
        $headerSchema = $this->getCardHeaderSchema($record);

        if ($headerSchema !== null) {
            $headerSchema->model($record);
            $formatted['headerSchema'] = $headerSchema;
        }

        // Process card schema if available
        $formatted['schema'] = null;
        $schema = $this->getCardSchema($record);

        if ($schema !== null) {
            // The schema is already built and configured
            $schema->model($record);

            // Store the schema object with record context for proper Livewire rendering
            $formatted['schema'] = $schema;
        }

        // Process card footer schema if available
        $formatted['footerSchema'] = null;
        $footerSchema = $this->getCardFooterSchema($record);

        if ($footerSchema !== null) {
            $footerSchema->model($record);
            $formatted['footerSchema'] = $footerSchema;
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
