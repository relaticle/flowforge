---
title: "Database Schema"
description: "Set up your database for drag-and-drop Kanban functionality."
canonical_url: "https://relaticle.github.io/essentials/database-schema"
---
# Database Schema

> Set up your database for drag-and-drop Kanban functionality.

## Required Fields

Your model needs these essential fields for Kanban functionality:

```php
Schema::create('tasks', function (Blueprint $table) {
    $table->id();
    $table->string('title');                         // Card title
    $table->string('status');                        // Column identifier
    $table->flowforgePositionColumn();               // Drag-and-drop ordering
    $table->timestamps();
});
```

## Enum Support

Flowforge automatically handles PHP `BackedEnum` for status fields. No manual conversion needed:

```php
// Define your enum
enum TaskStatus: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';
}

// Cast in your model
class Task extends Model
{
    protected $casts = [
        'status' => TaskStatus::class,
    ];
}

// Flowforge automatically converts column IDs to enum values
Column::make('todo')      // → TaskStatus::Todo
Column::make('in_progress') // → TaskStatus::InProgress
Column::make('done')      // → TaskStatus::Done
```

<callout type="info">

When a card moves between columns, Flowforge detects the `BackedEnum` cast and converts the column identifier to the appropriate enum value automatically.

</callout>

## Position Column

The `flowforgePositionColumn()` method creates a `DECIMAL(20,10)` column for drag-and-drop functionality:

```php
// Default column name 'position'
$table->flowforgePositionColumn();

// Custom column name
$table->flowforgePositionColumn('sort_order');

// Equivalent to:
$table->decimal('position', 20, 10)->nullable();
```

### Position Storage Details

Flowforge v3.x uses `DECIMAL(20,10)` for position storage:

<table>
<thead>
  <tr>
    <th>
      Property
    </th>
    
    <th>
      Value
    </th>
    
    <th>
      Purpose
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <strong>
        Total Digits
      </strong>
    </td>
    
    <td>
      20
    </td>
    
    <td>
      Maximum precision
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Decimal Places
      </strong>
    </td>
    
    <td>
      10
    </td>
    
    <td>
      Supports ~33 bisections before precision loss
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Default Gap
      </strong>
    </td>
    
    <td>
      65535
    </td>
    
    <td>
      Initial spacing between positions
    </td>
  </tr>
  
  <tr>
    <td>
      <strong>
        Min Gap
      </strong>
    </td>
    
    <td>
      0.0001
    </td>
    
    <td>
      Triggers automatic rebalancing
    </td>
  </tr>
</tbody>
</table>

The system uses **BCMath** for arbitrary precision arithmetic, ensuring consistent calculations across all database systems.

<callout type="info">

**v2.x Note:** Previous versions used VARCHAR with binary collations. v3.x uses DECIMAL for mathematical precision and better concurrent handling.

</callout>

## Example Migration

Here's a complete example for adding Flowforge support to an existing table:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->flowforgePositionColumn('position');

            // Recommended: Add unique constraint for concurrent safety
            $table->unique(['status', 'position'], 'unique_position_per_column');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique('unique_position_per_column');
            $table->dropColumn('position');
        });
    }
};
```

<callout type="warning">

**Unique Constraint:** Adding a unique constraint on `[column_field, position]` enables Flowforge's retry mechanism for concurrent operations.

</callout>

## Factory Integration

When using factories, ensure position values are generated correctly using the `DecimalPosition` service:

```php
use Relaticle\Flowforge\Services\DecimalPosition;

class TaskFactory extends Factory
{
    /** @var array<string, string> Track last position per status */
    private static array $lastPositions = [];

    public function definition(): array
    {
        $status = $this->faker->randomElement(['todo', 'in_progress', 'done']);

        return [
            'title' => $this->faker->sentence(3),
            'status' => $status,
            'position' => $this->generatePositionForStatus($status),
        ];
    }

    private function generatePositionForStatus(string $status): string
    {
        if (!isset(self::$lastPositions[$status])) {
            $position = DecimalPosition::forEmptyColumn();
        } else {
            $position = DecimalPosition::after(self::$lastPositions[$status]);
        }

        self::$lastPositions[$status] = $position;

        return $position;
    }
}
```

### DecimalPosition Methods

<table>
<thead>
  <tr>
    <th>
      Method
    </th>
    
    <th>
      Purpose
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        forEmptyColumn()
      </code>
    </td>
    
    <td>
      Get initial position for empty column (65535)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        after($position)
      </code>
    </td>
    
    <td>
      Get position after given position
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        before($position)
      </code>
    </td>
    
    <td>
      Get position before given position
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        between($after, $before)
      </code>
    </td>
    
    <td>
      Get position between two positions (with jitter)
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        betweenExact($after, $before)
      </code>
    </td>
    
    <td>
      Get exact midpoint (deterministic)
    </td>
  </tr>
</tbody>
</table>

## Position Management Commands

Flowforge provides three artisan commands for managing positions:

### Diagnose Positions

Check for position issues (gaps, inversions, duplicates):

```bash
php artisan flowforge:diagnose-positions \
    --model=App\\Models\\Task \
    --column=status \
    --position=position
```

### Rebalance Positions

Redistribute positions evenly when gaps become small:

```bash
php artisan flowforge:rebalance-positions \
    --model=App\\Models\\Task \
    --column=status \
    --position=position
```

### Repair Positions

Interactive command to fix corrupted position data:

```bash
php artisan flowforge:repair-positions
```

Strategies available:

- **regenerate** - Fresh start for all positions
- **fix_missing** - Only fix null positions
- **fix_duplicates** - Fix duplicate positions only
- **fix_all** - Both missing + duplicates (recommended)

## Concurrent Safety

### Jitter Mechanism

Each position calculation adds ±5% random jitter, ensuring concurrent users never generate identical positions. This is handled automatically by the `DecimalPosition::between()` method.

### Auto-Rebalancing

When the gap between adjacent cards falls below `0.0001`, positions are automatically redistributed with `65535` spacing. This happens transparently during card moves.

### Retry Mechanism

If a unique constraint violation occurs (rare with jitter), Flowforge automatically retries:

- **Max attempts:** 3
- **Backoff:** 50ms, 100ms, 200ms (exponential)
- **Supported databases:** SQLite, MySQL, PostgreSQL


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
