---
title: "Integration Patterns"
description: "Three ways to integrate Flowforge into your application."
canonical_url: "https://relaticle.github.io/essentials/integration-patterns"
---
# Integration Patterns

> Three ways to integrate Flowforge into your application.

Flowforge provides three flexible integration patterns to fit different use cases and application architectures.

## Standalone Livewire

Use in any Laravel application - complete flexibility for custom interfaces.

```php
<?php

namespace App\Livewire;

use App\Models\Task;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Livewire\Component;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Concerns\InteractsWithBoard;
use Relaticle\Flowforge\Contracts\HasBoard;

class TaskBoard extends Component implements HasBoard, HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithBoard {
        InteractsWithBoard::getDefaultActionRecord insteadof InteractsWithActions;
    }
    use InteractsWithForms;

    public function board(Board $board): Board
    {
        return $board
            ->query(Task::query())
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->columns([
                Column::make('todo')->label('To Do')->color('gray'),
                Column::make('in_progress')->label('In Progress')->color('blue'),
                Column::make('completed')->label('Completed')->color('green'),
            ]);
    }

    public function render()
    {
        return view('livewire.task-board');
    }
}
```

```blade
{{-- resources/views/livewire/task-board.blade.php --}}
<div>
    <h1 class="text-2xl font-bold mb-6">Task Board</h1>
    {{ $this->board }}
</div>
```

**Use when:** Building customer-facing apps or custom dashboards that already use Filament packages
**Requires:** `filament/forms` and `filament/actions` packages, plus Filament assets in your layout
**Benefits:** Maximum flexibility, custom styling, independent routing

### Layout Requirements

For standalone Livewire components outside Filament panels, your Blade layout must include Filament's assets:

```blade [resources/views/components/layouts/app.blade.php]
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @filamentStyles
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    {{ $slot }}

    @filamentScripts
</body>
</html>
```

Your Livewire component should use this layout:

```php
public function render()
{
    return view('livewire.task-board')
        ->layout('components.layouts.app');
}
```

<alert type="info">

The `@filamentStyles` and `@filamentScripts` directives load Filament's CSS and JavaScript, including the Alpine.js components FlowForge depends on.

</alert>

## Filament Page

Perfect for dedicated Kanban pages in your Filament admin panel.

```php
<?php

namespace App\Filament\Pages;

use App\Models\Task;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;

class TaskBoard extends BoardPage
{
    protected static ?string $navigationIcon = 'heroicon-o-view-columns';
    
    public function board(Board $board): Board
    {
        return $board
            ->query(Task::query())
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->columns([
                Column::make('todo')->label('To Do')->color('gray'),
                Column::make('in_progress')->label('In Progress')->color('blue'),
                Column::make('completed')->label('Completed')->color('green'),
            ]);
    }
}
```

**Use when:** You want a standalone Kanban page in your admin panel<br />

**Benefits:** Full Filament integration, automatic registration, built-in actions

## Resource Integration

Integrate with your existing Filament resources. Perfect for campaign management where teams track tasks within campaigns.

```php
<?php

namespace App\Filament\Resources\CampaignResource\Pages;

use App\Filament\Resources\CampaignResource;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardResourcePage;
use Relaticle\Flowforge\Column;

class CampaignTaskBoard extends BoardResourcePage
{
    protected static string $resource = CampaignResource::class;
    
    public function board(Board $board): Board
    {
        return $board
            ->query(
                // Get tasks for this specific campaign and current user's team
                $this->getRecord()
                    ->tasks()
                    ->whereHas('team', fn($q) => $q->where('id', auth()->user()->current_team_id))
                    ->getQuery()
            )
            ->columnIdentifier('status')
            ->positionIdentifier('position')
            ->columns([
                Column::make('backlog')->label('Backlog')->color('gray'),
                Column::make('in_progress')->label('In Progress')->color('blue'),
                Column::make('review')->label('Review')->color('amber'),
                Column::make('completed')->label('Completed')->color('green'),
            ]);
    }
}

// Register in your CampaignResource
public static function getPages(): array
{
    return [
        'index' => Pages\ListCampaigns::route('/'),
        'create' => Pages\CreateCampaign::route('/create'),
        'edit' => Pages\EditCampaign::route('/{record}/edit'),
        'tasks' => Pages\CampaignTaskBoard::route('/{record}/tasks'), // Add this line
    ];
}
```

**Use when:** Adding Kanban views to existing Filament resources
**Benefits:** Inherits resource permissions, policies, and global scopes automatically

## Modern board

Every pattern above has a Modern counterpart. A Modern board adds column limits, column summaries, card components, a create-card action, swimlanes and refused moves. It styles itself through a [documented design system](https://relaticle.github.io/essentials/design-system).

Opt in per page by changing one import:

```php
use Relaticle\Flowforge\Modern\BoardPage;          // was Relaticle\Flowforge\BoardPage
use Relaticle\Flowforge\Modern\BoardResourcePage;  // was Relaticle\Flowforge\BoardResourcePage
```

A standalone Livewire component uses the Modern trait:

```php
use Relaticle\Flowforge\Modern\Concerns\InteractsWithBoard;
```

Classic and Modern boards can live in one app. Nothing changes for a page that keeps the classic import. A Modern board needs no `@source` line in your Filament theme.

Modern settings such as `groupings()`, `columnSummary()`, `createCardAction()` and `Column::limit()` have no effect on a classic board.

## Extending Board Behavior

Override these methods to customize board behavior for your specific needs.

### Custom Card Movement Logic

On a classic board, override `moveCard()` to add custom logic when cards are moved:

#### Simple Updates

For basic updates on the exact same model, you can call the parent method and append your logic:

```php
public function moveCard(
    string $cardId,
    string $targetColumnId,
    ?string $afterCardId = null,
    ?string $beforeCardId = null
): void {
    // Call parent to handle the actual move
    parent::moveCard($cardId, $targetColumnId, $afterCardId, $beforeCardId);

    // Add custom logic
    $task = Task::find($cardId);

    if ($targetColumnId === 'completed') {
        $task->update(['completed_at' => now()]);
        $task->assignee->notify(new TaskCompletedNotification($task));
    }
}
```

#### Data Integrity with Transactions (Recommended)

If your custom logic involves complex state machines or updating secondary tables (like activity logs), it is highly recommended to wrap the override in a database transaction. Because parent::moveCard() executes a database update immediately, wrapping the entire method ensures that if your custom logic fails, the database safely rolls back. The frontend's optimistic UI will then gracefully snap the card back to its original column.

```php
use Illuminate\Support\Facades\DB;

public function moveCard(
    string $cardId,
    string $targetColumnId,
    ?string $afterCardId = null,
    ?string $beforeCardId = null
): void {
    DB::transaction(function () use ($cardId, $targetColumnId, $afterCardId, $beforeCardId) {
        // Let Flowforge handle the stage and position math
        parent::moveCard($cardId, $targetColumnId, $afterCardId, $beforeCardId);

        // Execute custom atomic logic safely
        $task = Task::find($cardId);

        if ($targetColumnId === 'completed') {
            $task->update(['completed_at' => now()]);
            
            // Safe multi-table insert - rolls back the card move if it fails!
            ActivityLog::create([
                'task_id' => $task->id, 
                'action' => 'Task marked as completed'
            ]);
            
            $task->assignee->notify(new TaskCompletedNotification($task));
        }
    });
}
```

#### Modern boards

On a Modern board, override the hooks, not `moveCard()`. Flowforge keeps the column check, limits, lanes and the browser revert.

```php
use Illuminate\Database\Eloquent\Model;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;

protected function authorizeCardMove(Model $card, string $targetColumnId, ?string $toLaneId = null): void
{
    if (! auth()->user()->can('update', $card)) {
        throw CardMoveRejectedException::because('You cannot move this card.');
    }
}

protected function persistCardMove(Model $card, mixed $columnValue, string $position): void
{
    parent::persistCardMove($card, $columnValue, $position);

    if ($columnValue === 'completed') {
        $card->update(['completed_at' => now()]);

        ActivityLog::create(['task_id' => $card->id, 'action' => 'completed']);
    }
}
```

`authorizeCardMove()` runs before anything is written. Throw `CardMoveRejectedException::because('...')` to refuse with a message: the user sees a toast and the card returns to where it started. An `abort(403)` also reverts the card, but Livewire shows its error UI instead of a toast.

`persistCardMove()` runs inside the move's database transaction, so an exception there rolls back your writes and the card move together. Throw `CardMoveRejectedException::because()` from it to refuse with a message. Calling the parent keeps the default column and position writes. When the column attribute is cast to a backed enum, `$columnValue` is the enum case.

`parent::` works on a board page. A standalone component that uses the trait directly aliases the method instead: `use InteractsWithBoard { persistCardMove as protected persistDefaultCardMove; }`, then calls `persistDefaultCardMove()`.

`moveCard()` on a Modern board returns `bool`: `false` means the move was refused and the browser puts the card back. It also takes the lane arguments `?string $fromLaneId` and `?string $toLaneId`. On a subclass of `Modern\BoardPage` or `Modern\BoardResourcePage`, an override that keeps the classic `void` signature is a fatal error. A standalone class that uses the Modern trait and declares its own `moveCard(): void` gets no error. The override silently replaces the Modern method, so refused moves never revert the card. Remove the override and use `authorizeCardMove()` and `persistCardMove()`.

#### Side effects that outlive a refused move

Model `updated` and `saved` observers fire when `persistCardMove()` and a lane callback write, before a later step can refuse the move. A lane callback that throws, or a transaction that fails after the observer ran, rolls the writes back but cannot recall what the observer already did. A queued job, a broadcast or a mail sent from the observer then reports a move that never happened.

Dispatch such side effects after commit. Make the job or event implement `ShouldDispatchAfterCommit`, mark a listener `public bool $afterCommit = true;`, or call `->afterCommit()` on the dispatch. Laravel then holds the dispatch until the move's transaction commits and drops it when the transaction rolls back.

### Custom Record Fetching

Override `getBoardRecords()` for complex filtering logic:

```php
public function getBoardRecords(string $columnId): \Illuminate\Support\Collection
{
    return $this->getEloquentQuery()
        ->where($this->getBoard()->getColumnIdentifier(), $columnId)
        ->where('team_id', auth()->user()->current_team_id)  // Custom filter
        ->with(['assignee', 'priority', 'labels'])           // Eager loading
        ->orderBy($this->getBoard()->getPositionIdentifier())
        ->get();
}
```

### Custom Count Logic

Override `getBoardRecordCount()` if you need custom counting:

```php
public function getBoardRecordCount(string $columnId): int
{
    return $this->getEloquentQuery()
        ->where($this->getBoard()->getColumnIdentifier(), $columnId)
        ->where('is_archived', false)  // Exclude archived from count
        ->count();
}
```

### Joined board queries

A board query that joins another table must select the base table's columns explicitly:

```php
Task::query()
    ->join('projects', 'projects.id', '=', 'tasks.project_id')
    ->select('tasks.*', 'projects.name as project_name')
```

With `select *`, the joined table's `id` overwrites the card's key on hydration. The joined table must also not share a column name with the board's column or position attribute. Flowforge filters on the unqualified name, and PostgreSQL then reports it as ambiguous.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
