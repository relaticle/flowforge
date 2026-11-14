<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestGroupedBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestNumericGroupedBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\User;

function viewTask(string $title, string $priority, string $position, string $status = 'todo'): Task
{
    return Task::create(['title' => $title, 'status' => $status, 'priority' => $priority, 'order_position' => $position]);
}

function laneSegment(string $html, string $laneId): string
{
    $start = strpos($html, 'wire:key="lane-' . $laneId . '"');
    $next = strpos($html, 'wire:key="lane-', $start + 1);

    return substr($html, $start, $next === false ? null : $next - $start);
}

test('a board without groupings shows no picker', function () {
    Livewire::test(TestBoard::class)->assertDontSeeHtml('flowforge-group-by');
});

test('the picker lists every grouping', function () {
    $html = html_entity_decode(Livewire::test(TestGroupedBoard::class)->html(), ENT_QUOTES);

    expect(substr_count($html, 'role="menuitemradio"'))->toBe(substr_count($html, 'ff-grouping-option'))
        ->and($html)->toContain("\$wire.\$set('groupBy', null)")
        ->toContain("\$wire.\$set('groupBy', 'priority')")
        ->toContain("\$wire.\$set('groupBy', 'assignee')");
});

test('the picker button names the active grouping and the menu checks it', function () {
    $component = Livewire::test(TestGroupedBoard::class)
        ->assertSeeHtml('aria-label="' . __('flowforge::flowforge.group_by') . '"')
        ->set('groupBy', 'priority')
        ->assertSeeHtml('aria-label="' . __('flowforge::flowforge.group_by') . ': Priority"')
        ->assertSeeHtml('data-grouped');

    expect($component->html())->toMatch('/aria-checked="true"[^>]*>(?:(?!<\/button>).)*Priority/s')
        ->and(substr_count($component->html(), 'aria-checked="true"'))->toBe(1);
});

test('choosing a grouping renders a lane per value', function () {
    viewTask('Low card', 'low', '65535.0000000000');
    viewTask('High card', 'high', '131070.0000000000');

    $component = Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'priority')
        ->assertSeeHtml('data-lane-id="low"')
        ->assertSeeHtml('data-lane-id="high"');

    $html = $component->html();

    expect(laneSegment($html, 'low'))->toContain('Low card')->not->toContain('High card')
        ->and(laneSegment($html, 'high'))->toContain('High card')->not->toContain('Low card');
});

test('a lane header carries a toggle named by its title', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class)->set('groupBy', 'priority')->html();

    preg_match('/aria-labelledby="(ff-lane-title-[^"]+)"/', $html, $toggle);

    expect($toggle)->not->toBeEmpty()
        ->and($html)->toContain('id="' . $toggle[1] . '" class="ff-lane-title">Low</h4>')
        ->toContain('x-bind:aria-expanded="! isLaneCollapsed(\'low\')"');
});

test('lanes label themselves and show their total', function () {
    viewTask('Low one', 'low', '65535.0000000000');
    viewTask('Low two', 'low', '131070.0000000000', 'in_progress');
    viewTask('High one', 'high', '196605.0000000000');

    $html = Livewire::test(TestGroupedBoard::class)->set('groupBy', 'priority')->html();

    expect($html)
        ->toMatch('/>Low<\/h4>\s*<span class="ff-lane-count">2<\/span>/')
        ->toMatch('/>High<\/h4>\s*<span class="ff-lane-count">1<\/span>/');
});

test('the grouping is read from the url', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    Livewire::withQueryParams(['groupBy' => 'priority'])
        ->test(TestGroupedBoard::class)
        ->assertSeeHtml('data-lane-id="low"');
});

test('an unknown grouping in the url falls back to the plain board', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    Livewire::withQueryParams(['groupBy' => 'nope'])
        ->test(TestGroupedBoard::class)
        ->assertOk()
        ->assertDontSeeHtml('data-lane-id')
        ->assertSeeHtml('data-column-id="todo"')
        ->assertSeeHtml('wire:key="card-board-');
});

test('more lanes load on request', function () {
    viewTask('Low one', 'low', '65535.0000000000');
    viewTask('Low two', 'low', '131070.0000000000');
    viewTask('High one', 'high', '196605.0000000000');

    Livewire::test(TestGroupedBoard::class, ['lanesPerPage' => 1])
        ->set('groupBy', 'priority')
        ->assertSee(__('flowforge::flowforge.show_more_lanes'))
        ->assertDontSeeHtml('data-lane-id="high"')
        ->call('loadMoreLanes')
        ->assertSeeHtml('data-lane-id="high"');
});

test('more cards load for one cell', function () {
    viewTask('First', 'low', '65535.0000000000');
    viewTask('Second', 'low', '131070.0000000000');

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1])
        ->set('groupBy', 'priority')
        ->assertSee(__('flowforge::flowforge.show_more_cards', ['count' => 1]))
        ->assertSeeHtml('wire:key="card-low-' . Task::where('title', 'First')->value('id') . '"')
        ->assertDontSeeHtml('wire:key="card-low-' . Task::where('title', 'Second')->value('id') . '"')
        ->call('loadMoreItems', 'todo', null, 'low')
        ->assertSeeHtml('wire:key="card-low-' . Task::where('title', 'Second')->value('id') . '"');
});

test('switching the grouping resets lane and cell paging', function () {
    Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'priority')
        ->call('loadMoreLanes')
        ->call('loadMoreItems', 'todo', null, 'low')
        ->assertSet('visibleLaneCount', 20)
        ->assertSet('cellCardLimits.low.todo', 40)
        ->set('groupBy', 'assignee')
        ->assertSet('visibleLaneCount', null)
        ->assertSet('cellCardLimits', []);
});

test('a card in two lanes renders once in each', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    $marcus = User::factory()->create(['name' => 'Marcus Johnson']);
    $task = viewTask('Shared card', 'low', '65535.0000000000');
    $task->assignees()->attach([$emma->id, $marcus->id]);

    $html = Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'assignee')
        ->assertSeeHtml('>Emma Collins</h4>')
        ->assertSeeHtml('>Marcus Johnson</h4>')
        ->html();

    expect(substr_count($html, 'wire:key="card-' . $emma->id . '-' . $task->id . '"'))->toBe(1)
        ->and(substr_count($html, 'wire:key="card-' . $marcus->id . '-' . $task->id . '"'))->toBe(1);
});

test('lane mode puts one header per column above the lanes', function () {
    viewTask('Low card', 'low', '65535.0000000000');
    viewTask('High card', 'high', '131070.0000000000');

    Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'priority')
        ->assertSeeHtmlInOrder(['data-column-root="todo"', 'data-column-root="in_progress"', 'data-lane-id="low"']);
});

test('the board root says whether cards may cross lanes', function () {
    Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'priority')
        ->assertSeeHtml('data-lanes-writable="true"')
        ->set('groupBy', 'creator')
        ->assertSeeHtml('data-lanes-writable="false"');
});

test('every part of the lane grid is keyed', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1, 'lanesPerPage' => 1])
        ->set('groupBy', 'priority')
        ->assertSeeHtml('wire:key="column-headers"')
        ->assertSeeHtml('wire:key="lane-header-todo"')
        ->assertSeeHtml('wire:key="lane-header-in_progress"')
        ->assertSeeHtml('wire:key="lane-low"')
        ->assertSeeHtml('wire:key="cell-low-todo"')
        ->assertSeeHtml('wire:key="cell-low-in_progress"');
});

test('the show more buttons are keyed', function () {
    viewTask('One', 'low', '65535.0000000000');
    viewTask('Two', 'low', '131070.0000000000');
    viewTask('Three', 'high', '196605.0000000000');

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1, 'lanesPerPage' => 1])
        ->set('groupBy', 'priority')
        ->assertSeeHtml('wire:key="more-cards-low-todo"')
        ->assertSeeHtml('wire:key="more-lanes"');
});

test('the lane grid scrolls inside its own container with a sticky header row', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    Livewire::test(TestGroupedBoard::class)
        ->set('groupBy', 'priority')
        ->assertSeeHtml('class="ff-lanes"')
        ->assertSeeHtml('class="ff-lanes-heads"');
});

test('a client cannot overflow or inflate lane paging', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 5])
        ->set('groupBy', 'priority')
        ->call('loadMoreItems', 'todo', PHP_INT_MAX, 'low')
        ->assertSet('cellCardLimits.low.todo', 10)
        ->call('loadMoreItems', 'todo', PHP_INT_MAX, 'low')
        ->assertOk()
        ->assertSet('cellCardLimits.low.todo', 15)
        ->call('loadMoreItems', 'todo', -50, 'low')
        ->assertSet('cellCardLimits.low.todo', 16);
});

test('flat column paging loads up to the requested count', function () {
    foreach (range(1, 10) as $index) {
        viewTask("Card {$index}", 'low', (string) ($index * 65535) . '.0000000000');
    }

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 2])
        ->call('loadMoreItems', 'todo', 4)
        ->assertSet('columnCardLimits.todo', 6)
        ->call('loadMoreItems', 'todo', 100)
        ->assertSet('columnCardLimits.todo', 10);
});

test('flat column paging caps an overflow count at the column total', function () {
    foreach (range(1, 10) as $index) {
        viewTask("Card {$index}", 'low', (string) ($index * 65535) . '.0000000000');
    }

    $component = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 2])
        ->call('loadMoreItems', 'todo', PHP_INT_MAX);

    expect($component->get('columnCardLimits')['todo'])->toBeInt()->toBe(10);
});

test('flat column paging treats a negative count as one', function () {
    foreach (range(1, 10) as $index) {
        viewTask("Card {$index}", 'low', (string) ($index * 65535) . '.0000000000');
    }

    Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 2])
        ->call('loadMoreItems', 'todo', -50)
        ->assertSet('columnCardLimits.todo', 3);
});

test('a client cannot write paging state', function (string $property, mixed $value) {
    expect(fn () => Livewire::test(TestGroupedBoard::class)->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    ['visibleLaneCount', 1000000],
    ['cellCardLimits', ['low' => ['todo' => 1000000]]],
    ['columnCardLimits', ['todo' => 1000000]],
]);

test('the picker button has an id of its own per board', function () {
    $component = Livewire::test(TestGroupedBoard::class);

    $component->assertSeeHtml('id="flowforge-group-by-' . $component->id() . '"');
});

test('the picker sits in the board body without a header toolbar', function () {
    $html = Livewire::test(TestGroupedBoard::class)->html();

    expect(substr_count($html, 'id="flowforge-group-by-'))->toBe(1)
        ->and(strpos($html, 'id="flowforge-group-by-'))->toBeGreaterThan(strpos($html, 'ff-board'));
});

test('the picker moves to the page header with a header toolbar', function () {
    $html = Livewire::test(TestGroupedBoard::class, ['withHeaderToolbar' => true])->html();

    expect(substr_count($html, 'id="flowforge-group-by-'))->toBe(1)
        ->and(strpos($html, 'id="flowforge-group-by-'))->toBeLessThan(strpos($html, 'ff-board'));
});

test('lane mode runs no flat per column record query', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $flat = Livewire::test(TestGroupedBoard::class);
    $lanes = Livewire::test(TestGroupedBoard::class)->set('groupBy', 'priority');

    $flatQueries = function () use ($flat): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $flat->call('$refresh');
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    };

    $laneQueries = function () use ($lanes): array {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $lanes->call('$refresh');
        DB::disableQueryLog();

        return array_column(DB::getQueryLog(), 'query');
    };

    $isFlatRecordQuery = fn (string $query): bool => str_starts_with($query, 'select * from "tasks"');

    expect(array_filter($flatQueries(), $isFlatRecordQuery))->not->toBeEmpty()
        ->and(array_filter($laneQueries(), $isFlatRecordQuery))->toBeEmpty();
});

test('collapsing empty columns applies to the flat board only', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $collapsedColumn = '/<div\s+class="ff-column"[^>]*\sdata-collapsed[\s>]/';
    $component = Livewire::test(TestGroupedBoard::class, ['collapseEmpty' => true]);

    expect($component->html())->toMatch($collapsedColumn)
        ->and($component->set('groupBy', 'priority')->html())->not->toMatch($collapsedColumn);
});

function laneCells(string $html, string $laneId, string $columnId): string
{
    $key = strpos($html, 'wire:key="cell-' . $laneId . '-' . $columnId . '"');
    $start = strrpos(substr($html, 0, $key), '<div');
    $end = preg_match('/wire:key="(?:cell|lane|more)-/', $html, $match, PREG_OFFSET_CAPTURE, $key + 1) === 1 ? $match[0][1] : strlen($html);

    return substr($html, $start, $end - $start);
}

function laneTracks(string $html, string $property = '--_tracks'): string
{
    preg_match('/class="ff-lanes-grid"[^>]*' . preg_quote($property, '/') . ':\s*([^;"]+)/', $html, $match);

    return trim($match[1] ?? '');
}

test('every column takes the full width track in the lane grid and the heads', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority'])->html();

    expect(laneTracks($html))->toBe('var(--_column-width) var(--_column-width)')
        ->and(laneTracks($html, '--_tracks-open'))->toBe('repeat(2, var(--_column-width))')
        ->and(substr_count($html, '--_tracks: var(--_column-width) var(--_column-width)'))->toBe(2);
});

test('a collapsed column takes a narrow track in every lane', function () {
    viewTask('Low card', 'low', '65535.0000000000');
    viewTask('High card', 'high', '131070.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsedIds' => ['todo']])->html();

    expect(laneTracks($html))->toBe('var(--_collapsed-width) var(--_column-width)')
        ->and(laneTracks($html, '--_tracks-open'))->toBe('repeat(2, var(--_column-width))')
        ->and(substr_count($html, '--_tracks: var(--_collapsed-width) var(--_column-width)'))->toBe(2);
});

test('a cell under a collapsed column shows its count and no cards', function () {
    viewTask('Low one', 'low', '65535.0000000000');
    viewTask('Low two', 'low', '131070.0000000000');
    viewTask('Low started', 'low', '65535.0000000000', 'in_progress');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsedIds' => ['todo']])->html();
    $collapsed = laneCells($html, 'low', 'todo');
    $open = laneCells($html, 'low', 'in_progress');

    expect($collapsed)->toContain('data-collapsed')
        ->and($collapsed)->toMatch('/class="ff-count"[^>]*>\s*2\s*</')
        ->and($collapsed)->toContain('data-column-id="todo"')
        ->and($collapsed)->not->toContain('ff-card"')
        ->and($collapsed)->not->toContain('Low one')
        ->and($open)->not->toContain('data-collapsed')
        ->and($open)->toContain('Low started');
});

test('a cell under a collapsed column loads at most one record', function () {
    foreach (range(1, 5) as $index) {
        viewTask("Todo {$index}", 'low', "{$index}0000.0000000000");
    }

    viewTask('Started', 'low', '65535.0000000000', 'in_progress');

    $hydrated = 0;
    Event::listen('eloquent.retrieved: ' . Task::class, function () use (&$hydrated): void {
        $hydrated++;
    });

    Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsedIds' => ['todo']]);

    expect($hydrated)->toBeLessThanOrEqual(2);
});

test('collapse all applies to lanes', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapseAll' => true])->html();

    expect(laneTracks($html))->toBe('var(--_collapsed-width) var(--_collapsed-width)')
        ->and(laneCells($html, 'low', 'todo'))->toContain('data-collapsed')
        ->and(laneCells($html, 'low', 'in_progress'))->toContain('data-collapsed');
});

test('empty columns still never collapse in lanes', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapseEmpty' => true])->html();

    expect($html)->not->toMatch('/<div\s[^>]*class="ff-(?:column|cell)"[^>]*\sdata-collapsed[\s>]/')
        ->and(laneTracks($html))->toBe('var(--_column-width) var(--_column-width)');
});

test('a collapsed lane head renders as the rail and toggles from a click', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsedIds' => ['todo'], 'collapsible' => true])->html();

    expect($html)->toMatch('/<div\s[^>]*data-column-root="todo"[^>]*data-collapsed[^>]*x-on:click="toggleColumnCollapse\(\$event, \'todo\', true\)"/s')
        ->and($html)->toMatch('/<div\s[^>]*data-column-root="in_progress"(?:(?!data-collapsed)[^>])*>/')
        ->and(substr_count($html, 'wire:key="column-menu-'))->toBeLessThanOrEqual(2);
});

test('an expanded lane head keeps the double click collapse', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $html = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsible' => true])->html();

    expect($html)->toContain('x-on:dblclick="toggleColumnCollapse($event, \'todo\')"');
});

test('toggling a column from the lane heads reaches the host', function () {
    viewTask('Low card', 'low', '65535.0000000000');

    $component = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsible' => true])
        ->call('toggleBoardColumnCollapse', 'todo');

    expect($component->get('toggled'))->toBe([['todo', true]]);

    $component->call('toggleBoardColumnCollapse', 'todo');

    expect($component->get('toggled'))->toBe([['todo', true], ['todo', false]]);
});

test('a card dropped into a collapsed cell stays in that column and lane', function () {
    $card = viewTask('Mover', 'low', '65535.0000000000');
    viewTask('Resident', 'high', '65535.0000000000', 'in_progress');

    $component = Livewire::test(TestGroupedBoard::class, ['groupBy' => 'priority', 'collapsedIds' => ['in_progress']]);

    expect($component->instance()->moveCard((string) $card->id, 'in_progress', null, null, 'low', 'high'))->toBeTrue();

    $fresh = $card->fresh();
    $html = $component->call('$refresh')->html();

    expect($fresh->status)->toBe('in_progress')
        ->and($fresh->priority)->toBe('high')
        ->and(laneCells($html, 'high', 'in_progress'))->toMatch('/class="ff-count"[^>]*>\s*2\s*</')
        ->and($html)->not->toContain('Mover');
});

test('a cell under a collapsed numeric column id renders no card', function () {
    viewTask('Low one', 'low', '65535.0000000000', '1');
    viewTask('Low two', 'low', '131070.0000000000', '2');

    $html = Livewire::test(TestNumericGroupedBoard::class, ['groupBy' => 'priority'])->html();
    $collapsed = laneCells($html, 'low', '1');

    expect($collapsed)->toContain('data-collapsed')
        ->and($collapsed)->not->toContain('Low one')
        ->and(laneCells($html, 'low', '2'))->toContain('Low two');
});
