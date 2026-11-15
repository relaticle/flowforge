<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Services\DecimalPosition;
use Relaticle\Flowforge\Tests\Fixtures\Task;
use Relaticle\Flowforge\Tests\Fixtures\TestBoard;

describe('board rendering', function () {
    test('renders board with all columns', function () {
        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertSee('To Do')
            ->assertSee('In Progress')
            ->assertSee('Completed')
            ->assertDontSee('Hidden Column');
    });

    test('compiles every column component instead of echoing its tag', function () {
        Task::factory()->todo()->create();

        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertDontSeeHtml('<x-flowforge')
            ->assertDontSeeHtml('<x-filament');
    });

    test('renders board with unhidden column', function () {
        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertDontSee('Hidden Column')
            ->set('showAllColumns', true)
            ->assertSee('Hidden Column');
    });

    test('re-hides column when visibility condition flips back', function () {
        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertDontSee('Hidden Column')
            ->set('showAllColumns', true)
            ->assertSee('Hidden Column')
            ->set('showAllColumns', false)
            ->assertDontSee('Hidden Column');
    });

    test('column configured with hidden(true) literal never renders', function () {
        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertDontSee('Archived Column')
            ->set('showAllColumns', true)
            ->assertDontSee('Archived Column');
    });

    test('getColumnIdentifiers excludes hidden columns', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard();

        expect($board->getColumnIdentifiers())
            ->not->toContain('hidden_column')
            ->not->toContain('archived')
            ->toContain('todo', 'in_progress', 'completed');
    });

    test('getColumnLabels excludes hidden columns', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard();

        expect(array_keys($board->getColumnLabels()))
            ->not->toContain('hidden_column')
            ->not->toContain('archived')
            ->toContain('todo', 'in_progress', 'completed');
    });

    test('getColumnColors excludes hidden columns', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard();

        expect(array_keys($board->getColumnColors()))
            ->not->toContain('hidden_column')
            ->not->toContain('archived');
    });

    test('getColumns returns a sequentially-indexed array after filtering', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard();

        $columns = $board->getColumns();

        expect(array_keys($columns))->toBe(range(0, count($columns) - 1));
    });

    test('getColumnIdentifiers returns empty when every column is hidden', function () {
        $board = Livewire::test(TestBoard::class)
            ->set('hideEverything', true)
            ->instance()
            ->getBoard();

        expect($board->getColumns())->toBe([])
            ->and($board->getColumnIdentifiers())->toBe([])
            ->and($board->getColumnLabels())->toBe([])
            ->and($board->getColumnColors())->toBe([]);
    });

    test('displays cards in correct columns', function () {
        Task::factory()->todo()->create(['title' => 'Task in Todo']);
        Task::factory()->inProgress()->create(['title' => 'Task in Progress']);
        Task::factory()->completed()->create(['title' => 'Task Completed']);

        Livewire::test(TestBoard::class)
            ->assertSee('Task in Todo')
            ->assertSee('Task in Progress')
            ->assertSee('Task Completed');
    });

    test('shows empty state for empty columns', function () {
        // Create task only in one column
        Task::factory()->todo()->create(['title' => 'Only Task']);

        $component = Livewire::test(TestBoard::class);

        // Should see the task in todo
        $component->assertSee('Only Task');

        // Board should still render all columns
        $component->assertSee('To Do')
            ->assertSee('In Progress')
            ->assertSee('Completed');
    });
});

describe('card movement', function () {
    test('moves card to different column', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'in_progress', null, null)
            ->assertDispatched('kanban-card-moved');

        expect($task->fresh()->status)->toBe('in_progress');
    });

    test('moves card to top of column', function () {
        $existingTask = Task::factory()->inProgress()->withPosition('65535.0000000000')->create();
        $taskToMove = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $taskToMove->id, 'in_progress', null, (string) $existingTask->id);

        $movedTask = $taskToMove->fresh();
        expect($movedTask->status)->toBe('in_progress')
            ->and((float) $movedTask->order_position)->toBeLessThan(65535);
    });

    test('dragging last card to top places it above first card (regression for #110)', function () {
        $first = Task::factory()->inProgress()->withPosition('65535.0000000000')->create(['title' => 'First']);
        $second = Task::factory()->inProgress()->withPosition('131070.0000000000')->create(['title' => 'Second']);
        $third = Task::factory()->inProgress()->withPosition('196605.0000000000')->create(['title' => 'Third']);
        $last = Task::factory()->inProgress()->withPosition('262140.0000000000')->create(['title' => 'Last']);

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $last->id, 'in_progress', null, (string) $first->id);

        $movedTask = $last->fresh();
        expect((float) $movedTask->order_position)->toBeLessThan((float) $first->order_position);

        $orderedIds = Task::where('status', 'in_progress')
            ->orderBy('order_position')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        expect($orderedIds[0])->toBe((string) $last->id)
            ->and($orderedIds[1])->toBe((string) $first->id)
            ->and($orderedIds[2])->toBe((string) $second->id)
            ->and($orderedIds[3])->toBe((string) $third->id);
    });

    test('moves card to bottom of column', function () {
        $existingTask = Task::factory()->inProgress()->withPosition('65535.0000000000')->create();
        $taskToMove = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $taskToMove->id, 'in_progress', (string) $existingTask->id, null);

        $movedTask = $taskToMove->fresh();
        expect($movedTask->status)->toBe('in_progress')
            ->and((float) $movedTask->order_position)->toBeGreaterThan(65535);
    });

    test('moves card between two existing cards', function () {
        $task1 = Task::factory()->inProgress()->withPosition('65535.0000000000')->create();
        $task2 = Task::factory()->inProgress()->withPosition('131070.0000000000')->create();
        $taskToMove = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $taskToMove->id, 'in_progress', (string) $task1->id, (string) $task2->id);

        $movedTask = $taskToMove->fresh();
        expect($movedTask->status)->toBe('in_progress')
            ->and((float) $movedTask->order_position)->toBeGreaterThan(65535)
            ->and((float) $movedTask->order_position)->toBeLessThan(131070);
    });

    test('dispatches kanban-card-moved event after move', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'completed', null, null)
            ->assertDispatched('kanban-card-moved');

        // Verify the move actually happened
        expect($task->fresh()->status)->toBe('completed');
    });

    test('updates position in database after move', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();
        $originalPosition = $task->order_position;

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'in_progress', null, null);

        $movedTask = $task->fresh();
        expect($movedTask->status)->toBe('in_progress')
            ->and($movedTask->order_position)->not->toBe($originalPosition);
    });
});

describe('move to empty column', function () {
    test('handles card moved to empty column', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        // Move to completed column which is empty
        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'completed', null, null)
            ->assertDispatched('kanban-card-moved');

        $movedTask = $task->fresh();
        expect($movedTask->status)->toBe('completed')
            ->and((float) $movedTask->order_position)->toBe((float) DecimalPosition::DEFAULT_GAP);
    });

    test('first card in empty column gets default position', function () {
        $task = Task::factory()->inProgress()->withPosition('100.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'todo', null, null);

        expect((float) $task->fresh()->order_position)->toBe((float) DecimalPosition::DEFAULT_GAP);
    });
});

describe('concurrent card movement', function () {
    test('handles very close positions by inserting between', function () {
        // Create two tasks with very close positions (but different due to unique constraint)
        $task1 = Task::factory()->inProgress()->withPosition('65535.0000000000')->create();
        $task2 = Task::factory()->inProgress()->withPosition('65536.0000000000')->create();
        $taskToMove = Task::factory()->todo()->withPosition('10000.0000000000')->create();

        // Move between two cards with close positions - should work
        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $taskToMove->id, 'in_progress', (string) $task1->id, (string) $task2->id)
            ->assertDispatched('kanban-card-moved');

        // Task should be moved and positioned between the two
        $movedTask = $taskToMove->fresh();
        expect($movedTask->status)->toBe('in_progress')
            ->and((float) $movedTask->order_position)->toBeGreaterThan(65535)
            ->and((float) $movedTask->order_position)->toBeLessThan(65536);
    });

    test('triggers auto-rebalancing when gap too small', function () {
        // Create cards with very small gap (below MIN_GAP threshold)
        $task1 = Task::factory()->inProgress()->withPosition('1000.0000000001')->create();
        $task2 = Task::factory()->inProgress()->withPosition('1000.0000000002')->create();
        $taskToMove = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $taskToMove->id, 'in_progress', (string) $task1->id, (string) $task2->id);

        // After rebalancing, positions should be evenly distributed with large gaps
        $positions = Task::where('status', 'in_progress')
            ->orderBy('order_position')
            ->pluck('order_position')
            ->map(fn ($p) => (float) $p)
            ->values();

        // Check that gaps are now reasonable (after rebalancing)
        if ($positions->count() >= 2) {
            $gap = $positions[1] - $positions[0];
            expect($gap)->toBeGreaterThan(1000); // Rebalanced with large gaps
        }
    });
});

describe('pagination', function () {
    test('loads more items on demand', function () {
        // Create many tasks in one column
        Task::factory(30)->todo()->create();

        $component = Livewire::test(TestBoard::class);

        // Call loadMoreItems
        $component->call('loadMoreItems', 'todo', 20)
            ->assertDispatched('kanban-items-loaded');
    });

    test('dispatches kanban-items-loaded event after loading more', function () {
        Task::factory(30)->todo()->create();

        Livewire::test(TestBoard::class)
            ->call('loadMoreItems', 'todo', 10)
            ->assertDispatched('kanban-items-loaded');
    });

    test('loads all items when requested', function () {
        Task::factory(50)->todo()->create();

        Livewire::test(TestBoard::class)
            ->call('loadAllItems', 'todo')
            ->assertDispatched('kanban-all-items-loaded');
    });

    test('tracks fully loaded state for small columns', function () {
        // Create only 5 tasks - should be fully loaded by default
        Task::factory(5)->todo()->create();

        $component = Livewire::test(TestBoard::class);

        // With only 5 tasks and default limit of 20, column should be fully loaded
        expect($component->instance()->isColumnFullyLoaded('todo'))->toBeTrue();
    });
});

describe('edge cases', function () {
    test('handles multiple consecutive moves', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        $component = Livewire::test(TestBoard::class);

        // Move card multiple times
        $component->call('moveCard', (string) $task->id, 'in_progress', null, null);
        expect($task->fresh()->status)->toBe('in_progress');

        $component->call('moveCard', (string) $task->id, 'completed', null, null);
        expect($task->fresh()->status)->toBe('completed');

        $component->call('moveCard', (string) $task->id, 'todo', null, null);
        expect($task->fresh()->status)->toBe('todo');
    });

    test('maintains correct order after multiple moves', function () {
        // Create 3 tasks in order
        $task1 = Task::factory()->todo()->withPosition('65535.0000000000')->create(['title' => 'First']);
        $task2 = Task::factory()->todo()->withPosition('131070.0000000000')->create(['title' => 'Second']);
        $task3 = Task::factory()->todo()->withPosition('196605.0000000000')->create(['title' => 'Third']);

        $component = Livewire::test(TestBoard::class);

        // Move task3 to top (before task1)
        $component->call('moveCard', (string) $task3->id, 'todo', null, (string) $task1->id);

        // Verify order
        $orderedTasks = Task::where('status', 'todo')
            ->orderBy('order_position')
            ->pluck('title')
            ->toArray();

        expect($orderedTasks[0])->toBe('Third'); // Now first
    });

    test('handles moving last card from column', function () {
        $task = Task::factory()->todo()->withPosition('65535.0000000000')->create();

        Livewire::test(TestBoard::class)
            ->call('moveCard', (string) $task->id, 'completed', null, null);

        // Todo column should now be empty
        expect(Task::where('status', 'todo')->count())->toBe(0);
        expect(Task::where('status', 'completed')->count())->toBe(1);
    });
});
