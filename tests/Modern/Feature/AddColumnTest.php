<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentIcon;
use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestGroupedBoard;
use Relaticle\Flowforge\View\FlowforgeIconAlias;

function laneCellColumnOrder(string $html, string $laneId): array
{
    $start = strpos($html, 'wire:key="lane-' . $laneId . '"');
    $next = strpos($html, 'wire:key="lane-', $start + 1);
    $segment = substr($html, $start, $next === false ? null : $next - $start);

    preg_match_all('/data-column-id="([^"]+)"/', $segment, $matches);

    return $matches[1];
}

function headTracks(string $html, string $property): string
{
    preg_match('/class="ff-lanes-heads"[^>]*' . preg_quote($property, '/') . ':\s*([^;"]+)/', $html, $match);

    return trim($match[1] ?? '');
}

describe('add column tile', function () {
    test('no tile renders unless the host adds columns', function () {
        Livewire::test(TestBoard::class)->assertDontSeeHtml('ff-add-column');
    });

    test('the tile renders when the host adds columns', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])->assertSeeHtml('ff-add-column');
    });

    test('the tile carries the default label as its tooltip', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])
            ->assertSeeHtml('title="' . __('flowforge::flowforge.add_column') . '"');
    });

    test('the tile carries the host label as its tooltip', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true, 'addColumnTitle' => 'Add stage'])
            ->assertSeeHtml('title="Add stage"');
    });

    test('the tile draws the icon the host maps to its alias', function () {
        preg_match('/ d="([^"]+)"/', svg('heroicon-m-star')->toHtml(), $star);

        FilamentIcon::register([FlowforgeIconAlias::ADD_COLUMN => 'heroicon-m-star']);

        $html = Livewire::test(TestBoard::class, ['withAddColumn' => true])->html();

        expect($html)->toContain($star[1]);
    });

    test('the input placeholder defaults to the column name text', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])
            ->assertSeeHtml('placeholder="' . __('flowforge::flowforge.column_name') . '"');
    });

    test('the input placeholder carries the host text', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true, 'addColumnHint' => 'Stage name'])
            ->assertSeeHtml('placeholder="Stage name"')
            ->assertDontSeeHtml('placeholder="' . __('flowforge::flowforge.column_name') . '"');
    });

    test('a blank name reads as a request for a name', function () {
        $component = Livewire::test(TestBoard::class, ['withAddColumn' => true])->call('addBoardColumn', ' ');

        expect($component->errors()->get('boardNewColumn'))->toBe(['Enter a name.']);
    });

    test('adding a column passes the trimmed name to the host', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])
            ->call('addBoardColumn', '  Won  ')
            ->assertHasNoErrors('boardNewColumn')
            ->assertSet('addedColumns', ['Won']);
    });

    test('a color from the host palette reaches the host, and any other color arrives as null', function () {
        $component = Livewire::test(TestBoard::class, ['withAddColumn' => true, 'withAddColumnColors' => true]);

        expect($component->html())->toContain('aria-label="Red"')->toContain('style="--color-500:#ff5454"');

        $component->call('addBoardColumn', 'Won', '#ff5454')
            ->call('addBoardColumn', 'Lost', '#000000')
            ->call('addBoardColumn', 'Paused');

        expect($component->get('addedColumnColors'))->toBe(['#ff5454', null, null]);
    });

    test('the form opens on the default color when it is in the palette', function () {
        $board = fn (string $default) => Livewire::test(TestBoard::class, ['withAddColumn' => true, 'withAddColumnColors' => true, 'addColumnDefault' => $default])->instance()->getBoard();

        expect($board('blue')->getAddColumnDefaultColor())->toBe('blue')
            ->and($board('#000000')->getAddColumnDefaultColor())->toBeNull();
    });

    test('the board shows the new column in the same response', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])
            ->call('addBoardColumn', 'Won')
            ->assertSeeHtml('data-column-root="Won"');
    });

    test('a blank name is refused before the host is called', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true])
            ->call('addBoardColumn', '   ')
            ->assertHasErrors(['boardNewColumn'])
            ->assertSet('addedColumns', []);
    });

    test('the host validation message reaches the tile', function () {
        $component = Livewire::test(TestBoard::class, ['withAddColumn' => true, 'rejectColumnName' => 'Lost'])
            ->call('addBoardColumn', 'Lost')
            ->assertHasErrors(['boardNewColumn'])
            ->assertSet('addedColumns', []);

        expect($component->errors()->get('boardNewColumn'))->toBe(['Taken'])
            ->and($component->html())->toContain('Taken');
    });

    test('a later success clears the error', function () {
        Livewire::test(TestBoard::class, ['withAddColumn' => true, 'rejectColumnName' => 'Lost'])
            ->call('addBoardColumn', 'Lost')
            ->call('addBoardColumn', 'Won')
            ->assertHasNoErrors('boardNewColumn')
            ->assertSet('addedColumns', ['Won']);
    });

    test('a board without the host closure ignores the call', function () {
        Livewire::test(TestBoard::class)
            ->call('addBoardColumn', 'Won')
            ->assertSet('addedColumns', [])
            ->assertHasNoErrors('boardNewColumn');
    });

    test('in lanes the tile is an extra head track and every lane stays aligned', function () {
        Task::create(['title' => 'Low card', 'status' => 'todo', 'priority' => 'low', 'order_position' => '65535.0000000000']);
        Task::create(['title' => 'High card', 'status' => 'in_progress', 'priority' => 'high', 'order_position' => '65535.0000000000']);

        $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'withAddColumn' => true])->html();

        expect(headTracks($html, '--_tracks'))->toBe('var(--_column-width) var(--_column-width) var(--_collapsed-width)')
            ->and(headTracks($html, '--_tracks-open'))->toBe('repeat(2, var(--_column-width)) var(--_collapsed-width)')
            ->and(laneCellColumnOrder($html, 'low'))->toBe(['todo', 'in_progress'])
            ->and(laneCellColumnOrder($html, 'high'))->toBe(['todo', 'in_progress'])
            ->and($html)->toContain('ff-add-column');
    });

    test('in lanes the grid keeps one track per column', function () {
        $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'withAddColumn' => true])->html();

        preg_match('/class="ff-lanes-grid"[^>]*--_tracks:\s*([^;"]+)/', $html, $match);

        expect(trim($match[1]))->toBe('var(--_column-width) var(--_column-width)');
    });

    test('in lanes the new column appears in the same response', function () {
        Task::create(['title' => 'Low card', 'status' => 'todo', 'priority' => 'low', 'order_position' => '65535.0000000000']);

        $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'withAddColumn' => true])
            ->call('addBoardColumn', 'Won')
            ->html();

        expect(laneCellColumnOrder($html, 'low'))->toBe(['todo', 'in_progress', 'Won'])
            ->and(headTracks($html, '--_tracks'))->toBe('var(--_column-width) var(--_column-width) var(--_column-width) var(--_collapsed-width)');
    });
});
