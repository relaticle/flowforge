---
title: "Installation"
description: "Get started with Flowforge in 90 seconds."
canonical_url: "https://relaticle.github.io/getting-started/installation"
---
# Installation

> Get started with Flowforge in 90 seconds.

## Requirements

- **PHP:** 8.3+
- **Laravel:** 12+
- **Filament:** 5.x
- **Database:** MySQL, PostgreSQL, SQLite, SQL Server, MariaDB

## Quick Setup

<steps>

### Install Package

```bash [Terminal]
composer require relaticle/flowforge
```

### Include CSS Assets

Prerequisite: You need a custom Filament theme to include the FlowForge styles.

<alert type="warning">

If you haven't set up a custom theme for Filament, follow the [Filament Docs](https://filamentphp.com/docs/5.x/styling/overview#creating-a-custom-theme) first.

</alert>

Once you have a custom Filament theme set up, add the plugin's views to your theme CSS file:

```css [resources/css/filament/admin/theme.css]
@source "../../../../vendor/relaticle/flowforge/resources/views/**/*.blade.php";
```

<alert type="info">

Modern boards ship their own stylesheet and need no `@source` line. See [Modern board](https://relaticle.github.io/essentials/integration-patterns#modern-board).

</alert>

### Add Position Column

Create a migration to add the position column:

```bash [Terminal]
php artisan make:migration add_position_to_tasks_table
```

```php [migration]
Schema::table('tasks', function (Blueprint $table) {
    $table->flowforgePositionColumn('position'); // Handles database-specific collations automatically
});
```

### Generate Board

```bash [Terminal]
php artisan flowforge:make-board TaskBoard --model=Task
```

### Register Page

```php [AdminPanelProvider.php]
->pages([
    App\Filament\Pages\TaskBoard::class,
])
```

</steps>

**Done!** Visit your Filament panel to see your Kanban board in action.

## Optional Commands

<table>
<thead>
  <tr>
    <th>
      Command
    </th>
    
    <th>
      Action
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        php artisan flowforge:repair-positions
      </code>
    </td>
    
    <td>
      Fix corrupted or missing position data
    </td>
  </tr>
</tbody>
</table>


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
