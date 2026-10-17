---
title: "Troubleshooting"
description: "Common issues and solutions for Flowforge implementation."
canonical_url: "https://relaticle.github.io/essentials/troubleshooting"
---
# Troubleshooting

> Common issues and solutions for Flowforge implementation.

## Common Issues

### Cards not draggable

**Problem**: Cards appear but cannot be dragged between columns.

**Causes & Solutions**:

- Missing `positionIdentifier`: Add `->positionIdentifier('position')` to board config
- Missing position column: Run migration with `$table->flowforgePositionColumn('position')`
- Null position values: Run `php artisan flowforge:repair-positions`

```php
// Correct configuration
public function board(Board $board): Board
{
    return $board
        ->query(Task::query())
        ->columnIdentifier('status')
        ->positionIdentifier('position')  // Required for drag-drop
        ->columns([...]);
}
```

### Empty board showing

**Problem**: Board renders but no cards appear in any columns.

**Debugging steps**:

1. Verify data exists:

```php
// Add to board method temporarily
dd($this->getEloquentQuery()->get());
```

1. Check status values match column identifiers:

```php
// If your columns are 'todo', 'in_progress', 'done'
// Your data status field must contain exactly these values
```

1. Ensure query returns records:

```php
// Check for global scopes, soft deletes, etc.
->query(Task::withoutGlobalScopes()->get())
```

### Actions not working

**Problem**: Card or column actions don't trigger or throw errors.

**Solutions**:

- Missing traits: Ensure component uses `InteractsWithActions`, `InteractsWithForms`
- Action configuration: Verify actions are properly configured with `->model()` parameter
- Permission issues: Check Filament policies and authorization

```php
class TaskBoard extends BoardPage
{
    use InteractsWithActions;  // Required
    use InteractsWithForms;   // Required
    
    public function board(Board $board): Board
    {
        return $board
            ->cardActions([
                EditAction::make()->model(Task::class),  // Model required
            ]);
    }
}
```

### New cards appear randomly

**Problem**: Cards created through column actions appear in random positions.

**Solution**: Add position generation in create action:

```php
CreateAction::make()
    ->mutateDataUsing(function (array $data, array $arguments): array {
        if (isset($arguments['column'])) {
            $data['status'] = $arguments['column'];
            $data['position'] = $this->getBoardPositionInColumn($arguments['column']);
        }
        return $data;
    })
```

### Drag-drop disabled with pagination

**Problem**: Cards show "Load All Cards" button and dragging is disabled.

**Explanation**: This is intentional behavior to prevent data corruption. Flowforge automatically disables drag-drop when pagination is active.

**Solution**: Users must click "Load All Cards" to enable safe reordering:

```php
// Check if column is fully loaded
$this->isColumnFullyLoaded('todo'); // Returns false with pagination

// Load all cards programmatically
$this->loadAllItems('todo');

// Now drag-drop is enabled
$this->isColumnFullyLoaded('todo'); // Returns true
```

### Styling issues

**Problem**: Board appears unstyled or has broken layout.

**Solutions**:

- Missing CSS: Ensure Flowforge views are included in your theme CSS
- Tailwind purging: Verify Tailwind isn't removing required classes
- Theme conflicts: Check for CSS conflicts with custom themes

```css
/* Required in your theme CSS file */
@source "../../../../vendor/relaticle/flowforge/resources/views/**/*.blade.php";
```

A Modern board needs no `@source` line. If it renders unstyled, run `php artisan filament:assets`. A standalone board also needs `@filamentStyles` in its layout. To restyle a Modern board, see the [Design System](https://relaticle.github.io/essentials/design-system).

### Alpine component errors

**Problem**: Console shows `flowforge is not defined` or `filamentActionModals is not defined`.

**Causes & Solutions**:

- **Missing Filament assets**: Your layout must include `@filamentStyles` in `<head>` and `@filamentScripts` before `</body>`
- **Using standalone Livewire without Filament panel**: See [Layout Requirements](https://relaticle.github.io/essentials/integration-patterns#layout-requirements)
- **Asset compilation issues**: Run `npm run build` to recompile assets

```blade
{{-- Required in your layout --}}
<head>
    @filamentStyles
</head>
<body>
    {{ $slot }}
    @filamentScripts
</body>
```

<alert type="warning">

FlowForge uses Filament's `x-load` directive and action modal system. These are only available when Filament's JavaScript is loaded via `@filamentScripts`.

</alert>

### Performance issues

**Problem**: Board loads slowly with many cards.

**Solutions**:

- Enable pagination: Remove `loadAllItems()` calls to use default pagination
- Optimize queries: Add database indexes on status and position columns
- Eager load relationships: Use `->with()` in your query
- Reduce card content: Minimize data displayed in card schema

```php
// Optimized query example
->query(
    Task::query()
        ->with(['assignee', 'priority'])  // Eager load relationships
        ->select(['id', 'title', 'status', 'position'])  // Select only needed fields
)
```

## Debug Commands

### Check position data integrity

```bash
php artisan flowforge:repair-positions
```

### Verify database schema

```bash
php artisan migrate:status
```

### Clear view cache

```bash
php artisan view:clear
```

### Test Livewire functionality

```php
// Add to board component temporarily
public function testDragDrop()
{
    $task = Task::first();
    $this->moveCard($task->id, 'in_progress');
    dd($task->fresh()->status);
}
```

## Getting Help

If you encounter issues not covered here:

1. **Check the GitHub issues**: [github.com/relaticle/flowforge/issues](https://github.com/relaticle/flowforge/issues)
2. **Review test examples**: Look at the test suite for working examples
3. **Enable debug mode**: Add `dd()` statements to understand data flow
4. **Create minimal reproduction**: Isolate the issue in a fresh Laravel install


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
