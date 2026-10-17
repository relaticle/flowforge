---
title: "Upgrading"
description: "Upgrade guide for Flowforge"
canonical_url: "https://relaticle.github.io/getting-started/upgrading"
---
# Upgrading

> Upgrade guide for Flowforge

## From v4.1 to v4.2

4.2 adds the Modern board, an opt-in board with its own styling contract. Existing boards keep working unchanged. It needs no migration, no config key and no env var.

### Requirements

- Filament 5, PHP 8.3 and Laravel 12, as in 4.1.
- Groupings use window functions: MySQL 8, MariaDB 10.2, PostgreSQL, SQLite 3.25 or SQL Server. Boards without groupings have no new requirement.
- Hard column limits take a cache lock, so use a store that every server shares, such as redis, memcached, database or dynamodb. The `array` store locks per process and the `null` store never blocks. Under either, two simultaneous drops can both pass a limit.

### Upgrade steps

1. `composer update relaticle/flowforge`
2. `php artisan filament:assets`, which publishes the Modern script and the Modern stylesheet. The page head links the stylesheet, so a missing publish leaves Modern boards unstyled.

Nothing else changes for a board that keeps its current `BoardPage`, `BoardResourcePage` or `InteractsWithBoard` import.

### Opting in

Change one import on the page you want to move:

```php
use Relaticle\Flowforge\Modern\BoardResourcePage;
```

`Relaticle\Flowforge\Modern\BoardPage` replaces `BoardPage`, and `Relaticle\Flowforge\Modern\Concerns\InteractsWithBoard` replaces the standalone trait. Opt in per page. A classic and a Modern board can live in one app.

### What changes on a Modern board

- Overrides of `moveCard()` move to hooks. `moveCard()` returns `bool` and takes `?string $fromLaneId` and `?string $toLaneId`, so on a subclass of `Modern\BoardPage` or `Modern\BoardResourcePage` an override with the classic `void` signature is a fatal error. A standalone class that uses the Modern trait and declares its own `moveCard(): void` gets no error, and the override silently replaces the Modern method, so refused moves never revert the card. Remove the override. Put checks in `authorizeCardMove()` and custom writes in `persistCardMove()`. See [Custom Card Movement Logic](https://relaticle.github.io/essentials/integration-patterns#custom-card-movement-logic).
- `calculateAndUpdatePosition()` and `calculateAndUpdatePositionWithRetry()` take a trailing `?array $laneMove = null`. An override with the classic signature is a fatal error on a Modern page subclass. A standalone class that uses the Modern trait gets no error, and its method silently replaces the Modern one. Prefer the two hooks.
- A move to a column the board does not show, or to a card its query no longer returns, is refused with a notification and the card returns.
- `createCardAction()`, column limits, column summaries and groupings work on Modern boards only. A classic board ignores them.
- The card components (`CardTitle`, `CardTag`, `CardMeta`, `CardAvatar`) are designed for Modern boards. On a classic board their look is not guaranteed.
- Modern markup carries `ff-*` classes. CSS written for `flowforge-*` classes does not reach a Modern board. Restyle through the [design system](https://relaticle.github.io/essentials/design-system).
- Dragging uses SortableJS fallback mode, so the dragged card is a real element. CSS or code that targeted native drag states stops working.
- The paging properties (`$columnCardLimits`, `$visibleLaneCount`, `$cellCardLimits`) are `#[Locked]`. Call `loadMoreItems()` or `loadAllItems()` instead of writing them from the browser.
- Modern views live under `flowforge::modern.*`. A classic view you published to `resources/views/vendor/flowforge/livewire` keeps serving classic boards and never reaches a Modern board.

### Other changes

- New lang keys exist in the en, de, es and nl files. The classic `no_cards_in_column` key stays.
- A board query that joins another table must select the base table's columns explicitly. See [Joined board queries](https://relaticle.github.io/essentials/integration-patterns#joined-board-queries).
- A classic move no longer fails on PostgreSQL when the board query outer-joins its column value.

### Known limitation

The first `wire:navigate` visit to a Modern board from any page that has not loaded the Modern stylesheet can show the board unstyled for a moment, because Livewire does not wait for new head stylesheets. Full page loads and repeat visits are styled from the first frame.

---

## From v3.x to v4.x

<callout type="info">

v4.x upgrades Filament support from 4.x to 5.x. No database or code changes required.

</callout>

### Requirements

- Filament 5.x (was 4.x)
- PHP 8.3+
- Laravel 12+

### Upgrade Steps

```bash
composer require relaticle/flowforge:^4.0
```

Review the [Filament 5 Upgrade Guide](https://filamentphp.com/docs/5.x/upgrade-guide) if you customized Filament components.

### No Changes Required

- Board configuration API unchanged
- Position column type unchanged
- All existing board code works without modification

---

## From v2.x to v4.x

<callout type="warning">

This covers all breaking changes from v2.x directly to v4.x. Includes database migration.

</callout>

### Requirements

- PHP 8.3+ (was 8.2+)
- Laravel 12+ (was 10+)
- Filament 5.x (was 3.x)
- **New:** `ext-bcmath` PHP extension

### High Impact Changes

#### Position Column Type Changed

The `flowforgePositionColumn()` macro now creates `DECIMAL(20,10)` instead of `VARCHAR`.

**Quick upgrade (resets card order):**

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropColumn('position');
    });

    Schema::table('tasks', function (Blueprint $table) {
        $table->flowforgePositionColumn();
    });
}
```

Then run:

```bash
php artisan flowforge:repair-positions
```

**Preserve card order:** See v2.x to v3.x section below for detailed migration.

### Medium Impact Changes

#### BCMath Extension Required

```bash
# Ubuntu/Debian
sudo apt-get install php-bcmath
```

### Factory Code Updates

If you used position service directly:

```php
// Before (v2.x)
use Relaticle\Flowforge\Services\Rank;
$position = Rank::forEmptySequence()->get();

// After (v4.x)
use Relaticle\Flowforge\Services\DecimalPosition;
$position = DecimalPosition::forEmptyColumn();
```

### No Changes Required

- Board configuration API unchanged
- Card/column actions unchanged
- Customization options unchanged

---

## From v2.x to v3.x

<callout type="warning">

v3.x changes how positions are stored in the database. A migration is required.

</callout>

### Requirements

- PHP 8.3+ (was 8.2+)
- Laravel 11+ (was 10+)
- **New:** `ext-bcmath` PHP extension

### High Impact Changes

#### Position Column Type Changed

The `flowforgePositionColumn()` macro now creates a `DECIMAL(20,10)` column instead of `VARCHAR`. You must migrate existing position columns.

**Quick upgrade (resets card order):**

```php
public function up(): void
{
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropColumn('position');
    });

    Schema::table('tasks', function (Blueprint $table) {
        $table->flowforgePositionColumn();
    });
}
```

Then run:

```bash
php artisan flowforge:repair-positions
```

**Preserve card order:**

```php
public function up(): void
{
    // Add temporary column
    Schema::table('tasks', function (Blueprint $table) {
        $table->decimal('position_new', 20, 10)->nullable();
    });

    // Copy order (positions will be regenerated by repair command)
    DB::statement('UPDATE tasks SET position_new = CAST(position AS DECIMAL(20,10))');

    // Swap columns
    Schema::table('tasks', function (Blueprint $table) {
        $table->dropColumn('position');
    });
    Schema::table('tasks', function (Blueprint $table) {
        $table->renameColumn('position_new', 'position');
    });
}
```

Then run:

```bash
php artisan flowforge:repair-positions
# Select "regenerate" strategy
```

### Medium Impact Changes

#### BCMath Extension Required

Install the BCMath PHP extension:

```bash
# Ubuntu/Debian
sudo apt-get install php-bcmath

# Verify
php -m | grep bcmath
```

### Low Impact Changes

#### New Artisan Commands

v3.x adds diagnostic commands:

```bash
php artisan flowforge:diagnose-positions   # Check for issues
php artisan flowforge:rebalance-positions  # Redistribute positions
```

### No Changes Required

- Board configuration API unchanged
- Card/column actions unchanged
- Customization options unchanged
- All existing board code works without modification

---

### Advanced: Factory Code Updates

<callout type="info">

Only relevant if you directly used the `Rank` service in your code.

</callout>

If you used Flowforge's position service in factories or custom code:

```php
// Before (v2.x)
use Relaticle\Flowforge\Services\Rank;
$position = Rank::forEmptySequence()->get();
$position = Rank::after($lastRank)->get();

// After (v3.x)
use Relaticle\Flowforge\Services\DecimalPosition;
$position = DecimalPosition::forEmptyColumn();
$position = DecimalPosition::after($lastPosition);
```

Most users don't need this - the package handles positions automatically during drag-and-drop.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
