<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;

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
