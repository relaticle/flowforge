<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestCollapsibleBoard;

describe('collapsed columns', function () {
    test('renders a listed column as a collapsed rail', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIds' => ['in_progress']])
            ->assertStatus(200)
            ->html();

        expect(substr_count($html, 'data-collapsed'))->toBe(1)
            ->and($html)->toMatch('/<div\s[^>]*data-column-root="in_progress"[^>]*data-collapsed/');
    });

    test('collapses every column when collapse all is on', function () {
        Task::factory()->todo()->create(['title' => 'Only Task']);

        $html = Livewire::test(TestBoard::class, ['collapseAll' => true])
            ->assertStatus(200)
            ->html();

        expect(substr_count($html, 'data-collapsed'))->toBe(3);
    });

    test('ignores collapsed ids for missing and hidden columns', function () {
        Livewire::test(TestBoard::class, ['collapsedIds' => ['nope', 'archived']])
            ->assertStatus(200)
            ->assertDontSeeHtml('data-collapsed');
    });

    test('a collapsed column keeps its drop binding', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIds' => ['todo']])->html();

        expect($html)->toMatch('/<div\s[^>]*data-column-root="todo"[^>]*data-collapsed[^>]*x-bind:data-drop="columnDrop\(\'todo\'\)"/');
    });

    test('keeps the empty column rule beside listed ids', function () {
        Task::factory()->todo()->create(['title' => 'Only Task']);

        $html = Livewire::test(TestBoard::class, ['collapseEmpty' => true, 'collapsedIds' => ['todo']])->html();

        expect(substr_count($html, 'data-collapsed'))->toBe(3)
            ->and($html)->toMatch('/<div\s[^>]*data-column-root="todo"[^>]*data-collapsed/');
    });

    test('a collapsed column renders no cards', function () {
        Task::factory()->todo()->create(['title' => 'Hidden Card']);

        Livewire::test(TestBoard::class, ['collapsedIds' => ['todo']])
            ->assertDontSee('Hidden Card')
            ->assertDontSeeHtml('ff-cards-more')
            ->assertSeeHtml('data-column-total="1"');
    });

    test('moving a card into a collapsed column persists it there', function () {
        $card = Task::factory()->todo()->create(['title' => 'Mover']);
        Task::factory()->create(['status' => 'in_progress', 'title' => 'Resident']);

        Livewire::test(TestBoard::class, ['collapsedIds' => ['in_progress']])
            ->call('moveCard', (string) $card->id, 'in_progress')
            ->assertDontSee('Mover');

        expect($card->refresh()->status)->toBe('in_progress');
    });

    test('accepts a collection of ids', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIdsAs' => 'collection', 'collapsedIds' => ['todo']])->html();

        expect(substr_count($html, 'data-collapsed'))->toBe(1);
    });

    test('accepts backed enum ids and ignores values that are not ids', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIdsAs' => 'enum', 'collapsedIds' => ['todo', ['x'], null]])->html();

        expect(substr_count($html, 'data-collapsed'))->toBe(1);
    });
});

describe('collapsed column anatomy', function () {
    test('a collapsed column renders no add card button and no column actions', function () {
        $expanded = Livewire::test(TestCollapsibleBoard::class, ['collapsedIds' => []])->html();
        $collapsed = Livewire::test(TestCollapsibleBoard::class, ['collapseAll' => true])->html();

        expect($expanded)->toContain('ff-column-actions')
            ->and($collapsed)->not->toContain('ff-column-actions')
            ->and($collapsed)->not->toContain('ff-column-header')
            ->and($collapsed)->not->toContain('ff-add-slot');
    });

    test('a collapsed column draws its label inside the tray', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIds' => ['in_progress']])->html();

        $own = '(?:(?!data-column-root).)*?';

        expect($html)->toMatch('/data-column-root="in_progress"[^>]*data-collapsed' . $own . 'class="ff-tray"' . $own . 'ff-column-heading' . $own . 'ff-column-title[^>]*>\s*In Progress' . $own . 'class="ff-cards"/s');
    });
});

describe('toggling collapse', function () {
    test('toggling an expanded column asks the host to collapse it', function () {
        $component = Livewire::test(TestBoard::class, ['collapsible' => true])
            ->call('toggleBoardColumnCollapse', 'todo');

        expect($component->get('toggled'))->toBe([['todo', true]])
            ->and($component->html())->toMatch('/<div\s[^>]*data-column-root="todo"[^>]*data-collapsed/');
    });

    test('toggling a collapsed column asks the host to expand it', function () {
        $component = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapsedIds' => ['todo']])
            ->call('toggleBoardColumnCollapse', 'todo');

        expect($component->get('toggled'))->toBe([['todo', false]])
            ->and($component->html())->not->toContain('data-collapsed');
    });

    test('toggling a column that an empty rule collapses is ignored and it stays collapsed', function () {
        Task::factory()->todo()->create(['title' => 'Only Task']);

        $component = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapseEmpty' => true])
            ->call('toggleBoardColumnCollapse', 'in_progress');

        expect($component->get('toggled'))->toBe([])
            ->and($component->html())->toMatch('/<div\s[^>]*data-column-root="in_progress"[^>]*data-collapsed/');
    });

    test('toggling under collapse all is ignored and every column stays collapsed', function () {
        $component = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapseAll' => true, 'collapsedIds' => ['todo']])
            ->call('toggleBoardColumnCollapse', 'todo')
            ->call('toggleBoardColumnCollapse', 'in_progress');

        expect($component->get('toggled'))->toBe([])
            ->and(substr_count($component->html(), 'data-collapsed'))->toBe(3);
    });

    test('a rail collapsed only by a rule binds no click', function () {
        Task::factory()->todo()->create(['title' => 'Only Task']);

        $empty = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapseEmpty' => true])->html();
        $all = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapseAll' => true, 'collapsedIds' => ['todo']])->html();

        expect($empty)->toContain('data-collapsed')
            ->and($empty)->not->toMatch('/<div\s[^>]*data-column-root="in_progress"[^>]*x-on:click/')
            ->and($all)->toContain('data-collapsed')
            ->and($all)->not->toMatch('/<div\s[^>]*data-column-root="[a-z_]+"[^>]*x-on:click/')
            ->and($all)->not->toContain('x-on:dblclick');
    });

    test('a toggle shows the new state in the same response for a plain array of ids', function () {
        $component = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapsedIds' => ['todo']])
            ->call('toggleBoardColumnCollapse', 'todo')
            ->call('toggleBoardColumnCollapse', 'completed');

        expect($component->get('toggled'))->toBe([['todo', false], ['completed', true]])
            ->and(substr_count($component->html(), 'data-collapsed'))->toBe(1)
            ->and($component->html())->toMatch('/<div\s[^>]*data-column-root="completed"[^>]*data-collapsed/');
    });

    test('toggle ignores unknown and hidden columns', function () {
        $component = Livewire::test(TestBoard::class, ['collapsible' => true])
            ->call('toggleBoardColumnCollapse', 'nope')
            ->call('toggleBoardColumnCollapse', 'archived');

        expect($component->get('toggled'))->toBe([]);
    });

    test('toggle does nothing when the board is not collapsible', function () {
        $component = Livewire::test(TestBoard::class)
            ->call('toggleBoardColumnCollapse', 'todo');

        expect($component->get('toggled'))->toBe([]);
    });

    test('a collapsible header binds the double click and the rail binds the click', function () {
        $html = Livewire::test(TestBoard::class, ['collapsible' => true, 'collapsedIds' => ['in_progress']])->html();

        expect($html)->toMatch('/<div\s[^>]*class="ff-column-header"[^>]*x-on:dblclick/')
            ->and($html)->toMatch('/<div\s[^>]*data-column-root="in_progress"[^>]*x-on:click/')
            ->and($html)->not->toMatch('/<div\s[^>]*data-column-root="todo"[^>]*x-on:click/');
    });

    test('neither binding renders when the board is not collapsible', function () {
        $html = Livewire::test(TestBoard::class, ['collapsedIds' => ['in_progress']])->html();

        expect($html)->not->toContain('x-on:dblclick')
            ->and($html)->not->toMatch('/<div\s[^>]*data-column-root="in_progress"[^>]*x-on:click/');
    });
});
