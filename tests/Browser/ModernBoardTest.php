<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;
use Relaticle\Flowforge\Tests\Browser\Support\PageHandle;
use Relaticle\Flowforge\Tests\Browser\Support\Pointer;
use Relaticle\Flowforge\Tests\Fixtures\Modern\BrowserUser;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;

use function Pest\Laravel\actingAs;

function boardPage(string $path, array $options = [], bool $dark = false): AwaitableWebpage
{
    $visit = visit($path, $options);

    return ($dark ? $visit->inDarkMode() : $visit)->assertSee('To Do');
}

function boardSeed(string $priority = 'medium'): array
{
    $rows = [['A', 'todo', 1000], ['B', 'todo', 2000], ['C', 'todo', 3000], ['D', 'in_progress', 1000]];

    return array_map(fn (array $row): Task => Task::query()->create([
        'title' => $row[0],
        'status' => $row[1],
        'order_position' => "{$row[2]}.0000000000",
        'priority' => $priority,
    ]), $rows);
}

function boardCard(Task $task): string
{
    return "[data-card-id=\"{$task->id}\"]";
}

function boardOrder(AwaitableWebpage $page, string $column): array
{
    $ids = $page->script("[...document.querySelectorAll('[data-column-id=\"{$column}\"] > [data-card-id]')].map((card) => card.dataset.cardId)");

    return array_map('intval', $ids);
}

function boardStored(string $status): array
{
    return Task::query()->where('status', $status)->orderBy('order_position')->pluck('id')->map(fn ($id): int => (int) $id)->all();
}

function boardCount(AwaitableWebpage $page, string $column): string
{
    return $page->script("document.querySelector('[data-column-root=\"{$column}\"] .ff-count').innerText.trim()");
}

function boardStyle(AwaitableWebpage $page, string $selector, string $property): string
{
    $selectorJson = json_encode($selector);
    $propertyJson = json_encode($property);

    return $page->script(<<<JS
        (async () => {
            const element = document.querySelector({$selectorJson});

            getComputedStyle(element).getPropertyValue({$propertyJson});

            await Promise.all(
                document.getAnimations()
                    .filter((animation) => animation instanceof CSSTransition)
                    .map((animation) => animation.finished.catch(() => null))
            );

            return getComputedStyle(element).getPropertyValue({$propertyJson});
        })()
    JS);
}

function boardCountRequests(AwaitableWebpage $page): void
{
    $page->script(<<<'JS'
        (() => {
            window.__updates = 0;
            const send = window.fetch;

            window.fetch = function (...args) {
                if (String(args[0]?.url ?? args[0]).includes('livewire')) {
                    window.__updates++;
                }

                return send.apply(this, args);
            };
        })()
    JS);
}

function boardUpdates(AwaitableWebpage $page): int
{
    return (int) $page->script('window.__updates');
}

function boardHoldUpdates(AwaitableWebpage $page): void
{
    $page->script(<<<'JS'
        (() => {
            const send = window.fetch;
            const held = [];
            let holding = true;

            window.fetch = function (...args) {
                if (! holding || ! String(args[0]?.url ?? args[0]).includes('livewire')) {
                    return send.apply(this, args);
                }

                return new Promise((resolve) => held.push(() => resolve(send.apply(this, args))));
            };

            window.__releaseNextUpdate = () => held.shift()();

            window.__releaseUpdates = () => {
                holding = false;
                held.splice(0).forEach((forward) => forward());
            };
        })()
    JS);
}

function boardReleaseNextUpdate(AwaitableWebpage $page): void
{
    $page->script('window.__releaseNextUpdate()');
}

function boardReleaseUpdates(AwaitableWebpage $page): void
{
    $page->script('window.__releaseUpdates()');
}

function boardPaintedCount(AwaitableWebpage $page, string $column): string
{
    $page->script('new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))');

    return boardCount($page, $column);
}

function boardUntilCountsSettle(Pointer $pointer): void
{
    $pointer->untilScript("[...document.querySelectorAll('.ff-count > :first-child')].every((total) => getComputedStyle(total).display !== 'none')");
}

function boardWatchCounts(AwaitableWebpage $page, array $columns): void
{
    $columnsJson = json_encode($columns);

    $page->script(<<<JS
        (() => {
            window.__seenCounts = new Set();

            const sample = () => {
                window.__seenCounts.add({$columnsJson}.map((column) => document.querySelector('[data-column-root="' + column + '"] .ff-count').innerText.trim()).join('/'));
                requestAnimationFrame(sample);
            };

            requestAnimationFrame(sample);
        })()
    JS);
}

function boardSeenCounts(AwaitableWebpage $page): array
{
    $page->script('new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)))');

    return $page->script('[...window.__seenCounts]');
}

function boardTraySampler(int $budgetMilliseconds): string
{
    return <<<JS
        (() => {
            window.__trayBackgrounds = [];

            const sample = () => {
                const tray = document.querySelector('.ff-tray');

                if (tray) {
                    window.__trayBackgrounds.push(getComputedStyle(tray).backgroundColor);
                }

                if (window.__trayBackgrounds.length < 30 && performance.now() < {$budgetMilliseconds}) {
                    requestAnimationFrame(sample);
                }
            };

            requestAnimationFrame(sample);
        })()
    JS;
}

beforeEach(function () {
    actingAs(BrowserUser::query()->create(['name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']));
});

test('a modern board page loads in a real browser', function () {
    boardSeed();

    boardPage('/modern/test-board')->assertNoJavaScriptErrors();
});

test('a card dropped between two cards keeps its place after a reload', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $pointer->drag(boardCard($d), boardCard($b), 0.25);
    $pointer->untilEquals(fn () => boardStored('todo'), [$a->id, $d->id, $b->id, $c->id], 'the stored todo order');

    expect(boardOrder($page, 'todo'))->toBe([$a->id, $d->id, $b->id, $c->id])
        ->and($d->refresh()->status)->toBe('todo');

    $page->refresh();
    $pointer->untilEquals(fn () => boardOrder($page, 'todo'), [$a->id, $d->id, $b->id, $c->id], 'the todo order after a reload');

    expect(boardOrder($page, 'in_progress'))->toBe([]);
});

test('header counts change while a card hovers and stay right after the drop', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    expect(boardCount($page, 'todo'))->toBe('3')
        ->and(boardCount($page, 'in_progress'))->toBe('1');

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);

    expect(boardCount($page, 'todo'))->toBe('4')
        ->and(boardCount($page, 'in_progress'))->toBe('0')
        ->and(boardCount($page, 'completed'))->toBe('0');

    $pointer->release();
    $pointer->untilEquals(fn () => boardStored('todo'), [$a->id, $d->id, $b->id, $c->id], 'the stored todo order');
    $pointer->untilEquals(fn () => [boardCount($page, 'todo'), boardCount($page, 'in_progress')], ['4', '0'], 'the todo and in progress counts');

    expect(boardCount($page, 'completed'))->toBe('0');
});

test('header counts keep the moved values from the drop until the move settles', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    boardHoldUpdates($page);

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);
    boardWatchCounts($page, ['todo', 'in_progress']);
    $pointer->release();
    $pointer->untilScript("document.querySelector('[data-pending]') !== null");

    expect(boardPaintedCount($page, 'todo'))->toBe('4')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('0');

    boardReleaseUpdates($page);
    $pointer->untilScript("! document.querySelector('[data-pending]')");

    expect(boardStored('todo'))->toBe([$a->id, $d->id, $b->id, $c->id])
        ->and(boardPaintedCount($page, 'todo'))->toBe('4')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('0')
        ->and(boardSeenCounts($page))->toBe(['4/0']);
});

test('header counts return to the original totals when a held move is refused', function () {
    [, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    boardHoldUpdates($page);

    $pointer->press(boardCard($b))->glideTo(boardCard($d), yFraction: 0.25);
    $b->delete();
    $pointer->release();
    $pointer->untilScript("document.querySelector('[data-pending]') !== null");

    expect(boardPaintedCount($page, 'todo'))->toBe('2')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('2');

    boardReleaseUpdates($page);
    $pointer->untilScript('document.body.innerText.includes("That card is no longer on the board")');
    $pointer->untilScript("! document.querySelector('[data-pending]')");

    expect(boardPaintedCount($page, 'todo'))->toBe('3')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('1');
});

test('header counts hold both moves while two drops wait for the server', function () {
    [$a, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    boardHoldUpdates($page);

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25)->release();
    $pointer->untilScript('! window.Sortable.active && ! window.Sortable.dragged');
    $pointer->press(boardCard($a))->glideTo('[data-column-root="completed"]');
    boardWatchCounts($page, ['todo', 'in_progress', 'completed']);
    $pointer->release();
    $pointer->untilScript("document.querySelectorAll('[data-pending]').length === 2");

    expect(boardPaintedCount($page, 'todo'))->toBe('3')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('0')
        ->and(boardPaintedCount($page, 'completed'))->toBe('1');

    boardReleaseUpdates($page);
    $pointer->untilEquals(fn () => boardStored('completed'), [$a->id], 'the stored completed column');
    boardUntilCountsSettle($pointer);

    expect(boardPaintedCount($page, 'todo'))->toBe('3')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('0')
        ->and(boardPaintedCount($page, 'completed'))->toBe('1')
        ->and(boardSeenCounts($page))->toBe(['3/0/1']);
});

test('a refused move gives back only its own counts while another move waits', function () {
    [$a, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    boardHoldUpdates($page);

    $pointer->press(boardCard($b))->glideTo(boardCard($d), yFraction: 0.25);
    $b->delete();
    $pointer->release();
    $pointer->untilScript('! window.Sortable.active && ! window.Sortable.dragged');
    $pointer->press(boardCard($a))->glideTo('[data-column-root="completed"]')->release();
    $pointer->untilScript("document.querySelectorAll('[data-pending]').length === 2");

    expect(boardPaintedCount($page, 'todo'))->toBe('1')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('2')
        ->and(boardPaintedCount($page, 'completed'))->toBe('1');

    boardReleaseNextUpdate($page);
    $pointer->untilScript("document.querySelectorAll('[data-pending]').length === 1");

    expect(boardPaintedCount($page, 'todo'))->toBe('2')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('1')
        ->and(boardPaintedCount($page, 'completed'))->toBe('1');

    boardReleaseUpdates($page);
    $pointer->untilEquals(fn () => boardStored('completed'), [$a->id], 'the stored completed column');
    boardUntilCountsSettle($pointer);

    expect(boardPaintedCount($page, 'todo'))->toBe('1')
        ->and(boardPaintedCount($page, 'in_progress'))->toBe('1')
        ->and(boardPaintedCount($page, 'completed'))->toBe('1');
});

test('a refused drop returns the card to its index with a message', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $pointer->press(boardCard($b))->glideTo(boardCard($d), yFraction: 0.25);

    expect(boardOrder($page, 'todo'))->toBe([$a->id, $c->id]);

    $b->delete();
    $pointer->release();

    $pointer->untilScript('document.body.innerText.includes("That card is no longer on the board")');
    $pointer->untilScript("! document.querySelector('[data-pending]')");

    expect(boardOrder($page, 'todo'))->toBe([$a->id, $b->id, $c->id])
        ->and(boardOrder($page, 'in_progress'))->toBe([$d->id]);
});

test('a full column refuses a drop at hover', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-browser-board');
    $pointer = Pointer::on($page);
    boardCountRequests($page);
    $requestsBefore = boardUpdates($page);

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);

    $page->assertScript("document.querySelector('[data-column-root=\"todo\"]').dataset.drop === 'blocked'")
        ->assertScript("getComputedStyle(document.querySelector('.ff-limit-hint')).display !== 'none'");

    $pointer->release();
    $pointer->untilScript('! document.documentElement.classList.contains("ff-dragging") && ! document.querySelector("[data-dragging]")');

    expect(boardUpdates($page))->toBe($requestsBefore)
        ->and(boardOrder($page, 'todo'))->toBe([$a->id, $b->id, $c->id])
        ->and(boardOrder($page, 'in_progress'))->toBe([$d->id])
        ->and($d->refresh()->status)->toBe('in_progress');

    $pointer->untilScript('! window.Sortable.active && ! window.Sortable.dragged');
    $pointer->press(boardCard($a))->glideTo(boardCard($d), yFraction: 0.25);
    $pointer->untilScript('document.querySelector(\'[data-column-root="in_progress"]\').dataset.drop === "active"');
    $pointer->release();
    $pointer->untilEquals(fn () => $a->refresh()->status, 'in_progress', 'the stored status of card A');
    $pointer->untilScript('! document.querySelector("[data-pending]")');

    expect(boardUpdates($page))->toBe($requestsBefore + 1);
});

test('the group by menu switches the board to lanes and back', function () {
    boardSeed('high');
    $page = boardPage('/modern/test-grouped-board');
    $pointer = Pointer::on($page);
    $trigger = '[id^="flowforge-group-by-"]';

    $pointer->click($trigger)->click('.ff-grouping-option:nth-child(2)');
    $pointer->untilScript('document.querySelector(\'[data-lane-id="high"]\') !== null');

    expect($page->script("document.querySelector('{$trigger}').innerText.trim()"))->toBe('Priority')
        ->and($page->script("document.querySelector('{$trigger}').hasAttribute('data-grouped')"))->toBeTrue();

    $pointer->click($trigger)->click('.ff-grouping-option:nth-child(1)');
    $pointer->untilScript('document.querySelector("[data-lane-id]") === null && document.querySelector(".ff-columns") !== null');

    expect($page->script("document.querySelector('{$trigger}').innerText.trim()"))->toBe(__('flowforge::flowforge.group_by'));
});

test('a lane label stays in view while the lanes scroll sideways and collapses its lane', function () {
    boardSeed('high');
    $page = boardPage('/modern/test-grouped-board?groupBy=priority')->resize(640, 800);
    $pointer = Pointer::on($page);
    $header = '.ff-lane-header';
    $headerLeft = "Math.round(document.querySelector('{$header}').getBoundingClientRect().left)";

    $page->script("document.querySelector('.ff-board').style.setProperty('--ff-column-width', '900px')");
    $restingLeft = $page->script($headerLeft);
    $page->script("(() => { const lanes = document.querySelector('.ff-lanes'); lanes.scrollLeft = lanes.scrollWidth; })()");
    $pointer->untilScript("(() => { const lanes = document.querySelector('.ff-lanes'); return lanes.scrollLeft > 0 && lanes.scrollLeft + lanes.clientWidth >= lanes.scrollWidth - 1; })()");

    expect($page->script($headerLeft))->toBe($restingLeft);

    $pointer->click($header);
    $pointer->untilScript("document.querySelector('{$header}').hasAttribute('data-collapsed') && [...document.querySelectorAll('[data-lane-id=\"high\"]')].every((cell) => cell.closest('.ff-cell').offsetParent === null)");

    expect($page->script("document.querySelector('{$header} .ff-lane-toggle').getAttribute('aria-expanded')"))->toBe('false');

    $page->script('(async () => { await window.Livewire.all()[0].$wire.$refresh(); })()');

    expect($page->script("document.querySelector('{$header}').hasAttribute('data-collapsed')"))->toBeTrue();

    $page->script("document.querySelector('{$header} .ff-lane-toggle').focus()");
    $page->keys("{$header} .ff-lane-toggle", 'Enter');
    $pointer->untilScript("! document.querySelector('{$header}').hasAttribute('data-collapsed')");
});

test('a card moves to another lane', function () {
    $rows = [['Low one', 'low', 1000], ['Low two', 'low', 2000], ['High one', 'high', 3000]];
    [$lowOne, $lowTwo, $highOne] = array_map(fn (array $row): Task => Task::query()->create([
        'title' => $row[0],
        'status' => 'todo',
        'priority' => $row[1],
        'order_position' => "{$row[2]}.0000000000",
    ]), $rows);

    $page = boardPage('/modern/test-grouped-board?groupBy=priority');
    $pointer = Pointer::on($page);

    $pointer->drag(boardCard($lowOne), boardCard($highOne), 0.25);
    $pointer->untilEquals(fn () => $lowOne->refresh()->priority, 'high', 'the stored priority of the moved card');

    expect($lowTwo->refresh()->priority)->toBe('low')
        ->and($page->script("[...document.querySelectorAll('[data-lane-id=\"high\"][data-column-id=\"todo\"] > [data-card-id]')].map((card) => Number(card.dataset.cardId))"))
        ->toBe([$lowOne->id, $highOne->id]);
});

test('a drag selects no text', function () {
    [, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $pointer->press(boardCard($d))->glideTo(boardCard($b))->glideTo('[data-column-root="completed"]');

    $page->assertScript('document.documentElement.classList.contains("ff-dragging")')
        ->assertScript('window.getSelection().toString() === ""');

    $pointer->release();
    $pointer->untilScript('! document.documentElement.classList.contains("ff-dragging")');

    $page->assertScript('window.getSelection().toString() === ""');
});

test('reduced motion leaves the dragged card untilted', function () {
    [, $b, , $d] = boardSeed();
    $dragged = '.ff-card[data-dragging] > .ff-card-body';

    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    $pointer->press(boardCard($d))->glideTo(boardCard($b));
    $tilted = boardStyle($page, $dragged, 'rotate');
    $pointer->release();

    $reduced = boardPage('/modern/test-board', ['reducedMotion' => 'reduce']);
    $reducedPointer = Pointer::on($reduced);
    $reducedPointer->press(boardCard($d))->glideTo(boardCard($b));
    $untilted = boardStyle($reduced, $dragged, 'rotate');
    $reducedPointer->release();

    expect($tilted)->not->toBe('none')
        ->and($untilted)->toBe('none');
});

test('dark mode switches the defaults', function () {
    boardSeed();

    $light = boardPage('/modern/test-board');
    $dark = boardPage('/modern/test-board', dark: true);

    $dark->assertScript('document.documentElement.classList.contains("dark")');

    expect(boardStyle($dark, '.ff-tray', 'background-color'))->not->toBe(boardStyle($light, '.ff-tray', 'background-color'))
        ->and(boardStyle($dark, '.ff-card', 'background-color'))->not->toBe(boardStyle($light, '.ff-card', 'background-color'));
});

test('dragging sets the documented states', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $page->script(<<<'JS'
        (() => {
            window.__pendingSeen = false;

            new MutationObserver(() => {
                window.__pendingSeen ||= document.querySelector('[data-pending]') !== null;
            }).observe(document.body, { attributes: true, subtree: true, attributeFilter: ['data-pending'] });
        })()
    JS);

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);

    $page->assertScript('document.querySelectorAll("body > .ff-card[data-dragging]").length === 1')
        ->assertScript('document.querySelectorAll("[data-column-id=\"todo\"] > [data-placeholder]").length === 1')
        ->assertScript('document.querySelector("[data-column-root=\"todo\"]").dataset.drop === "active"');

    $pointer->release();
    $pointer->untilEquals(fn () => boardStored('todo'), [$a->id, $d->id, $b->id, $c->id], 'the stored todo order');
    $pointer->untilScript('window.__pendingSeen && ! document.querySelector("[data-pending]")');

    $page->assertScript('document.querySelectorAll("[data-dragging], [data-placeholder], [data-drop], [data-pending]").length === 0');
});

test('app tokens win at every scope', function () {
    [, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $page->script(<<<'JS'
        (() => {
            const style = document.createElement('style');
            style.textContent = ':root { --ff-tray-bg: rgb(1, 2, 3); --ff-card-bg: rgb(7, 8, 9); } .ff-board { --ff-card-bg: rgb(4, 5, 6); }';
            document.head.append(style);
            document.querySelector('.ff-card').style.setProperty('--ff-card-radius', '3px');
        })()
    JS);

    expect(boardStyle($page, '.ff-tray', 'background-color'))->toBe('rgb(1, 2, 3)')
        ->and(boardStyle($page, boardCard($b), 'background-color'))->toBe('rgb(4, 5, 6)')
        ->and(boardStyle($page, '.ff-card', 'border-top-left-radius'))->toBe('3px')
        ->and(boardStyle($page, boardCard($b), 'border-top-left-radius'))->not->toBe('3px');

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);

    expect(boardStyle($page, 'body > .ff-card[data-dragging] > .ff-card-body', 'background-color'))->toBe('rgb(4, 5, 6)');

    $pointer->release();
});

test('app tokens win at every scope including columns and cells', function () {
    [, , , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $page->script(<<<'JS'
        (() => {
            const todo = document.querySelector('[data-column-root="todo"]');

            todo.style.setProperty('--ff-tray-bg', 'rgb(1, 2, 3)');
            todo.style.setProperty('--ff-accent-tint', 'rgb(9, 8, 7)');
            document.querySelector('[data-column-root="in_progress"] .ff-tray').style.setProperty('--ff-tray-radius', '5px');
        })()
    JS);

    expect(boardStyle($page, '[data-column-root="todo"] .ff-tray', 'background-color'))->toBe('rgb(1, 2, 3)')
        ->and(boardStyle($page, '[data-column-root="in_progress"] .ff-tray', 'background-color'))->not->toBe('rgb(1, 2, 3)')
        ->and(boardStyle($page, '[data-column-root="in_progress"] .ff-tray', 'border-top-left-radius'))->toBe('5px');

    $pointer->press(boardCard($d))->glideTo('[data-column-root="todo"] .ff-tray', yFraction: 0.9);

    expect(boardStyle($page, '[data-column-root="todo"] .ff-tray', 'background-color'))->toBe('rgb(9, 8, 7)');

    $pointer->release();

    $grouped = boardPage('/modern/test-grouped-board?groupBy=priority');
    $grouped->script("document.querySelector('.ff-cell').style.setProperty('--ff-tray-bg', 'rgb(11, 12, 13)')");

    expect(boardStyle($grouped, '.ff-cell', 'background-color'))->toBe('rgb(11, 12, 13)');
});

test('the drag clone keeps tokens set on its own card and column', function () {
    [, $b, , $d] = boardSeed();
    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);

    $page->script(<<<'JS'
        (() => {
            const style = document.createElement('style');
            style.textContent = '.ff-board { --ff-card-bg: rgb(4, 5, 6); }';
            document.head.append(style);
            document.querySelector('[data-column-root="in_progress"]').style.setProperty('--ff-card-radius', '7px');
        })()
    JS);
    $page->script("document.querySelector('[data-card-id=\"{$d->id}\"]').style.setProperty('--ff-card-bg', 'rgb(10, 20, 30)')");

    $pointer->press(boardCard($d))->glideTo(boardCard($b), yFraction: 0.25);

    expect(boardStyle($page, 'body > .ff-card[data-dragging] > .ff-card-body', 'background-color'))->toBe('rgb(10, 20, 30)')
        ->and(boardStyle($page, 'body > .ff-card[data-dragging] > .ff-card-body', 'border-top-left-radius'))->toBe('7px');

    $pointer->release();
});

test('board icons render at one rem', function () {
    boardSeed('low');
    $page = boardPage('/modern/test-contract-board');

    expect(boardStyle($page, '.ff-column-icon', 'width'))->toBe('16px')
        ->and(boardStyle($page, '.ff-add-row-icon', 'width'))->toBe('16px');

    $lanes = boardPage('/modern/test-contract-board?groupBy=owner');

    expect(boardStyle($lanes, '.ff-lane-icon', 'width'))->toBe('16px');
});

test('modern assets load on an spa visit', function () {
    [$a, $b, $c, $d] = boardSeed();
    $page = boardPage('/modern/test-classic-board');
    $pointer = Pointer::on($page);
    $page->script('window.__spaVisit = true');

    $pointer->click('a[href$="/modern/test-board"]');
    $pointer->untilScript('document.querySelector(".ff-board") !== null');

    $page->assertScript('window.__spaVisit === true')
        ->assertScript('getComputedStyle(document.querySelector(".ff-tray")).backgroundColor !== "rgba(0, 0, 0, 0)"');

    $pointer->drag(boardCard($d), boardCard($b), 0.25);
    $pointer->untilEquals(fn () => boardStored('todo'), [$a->id, $d->id, $b->id, $c->id], 'the stored todo order');

    $page->assertNoJavaScriptErrors();
});

test('a classic board stays classic after a modern visit', function () {
    boardSeed();
    $probe = <<<'JS'
        (() => {
            const read = (selector) => {
                const style = getComputedStyle(document.querySelector(selector));

                return ['display', 'width', 'padding-top', 'background-color', 'border-top-left-radius', 'box-shadow', 'min-height']
                    .map((property) => style.getPropertyValue(property));
            };

            return JSON.stringify({
                column: read('[x-sortable]'),
                card: read('[x-sortable-item]'),
                modernElements: [...document.querySelectorAll('*')].filter((element) => [...element.classList].some((name) => name.startsWith('ff-'))).length,
                modernBehaviour: document.querySelector('[x-sortable]').closest('[x-data]')._x_dataStack.some((data) => 'configureSortable' in data),
            });
        })()
    JS;

    $baseline = boardPage('/modern/test-classic-board');
    $classic = json_decode($baseline->script($probe), true);

    $page = boardPage('/modern/test-board');
    $pointer = Pointer::on($page);
    $pointer->untilScript('document.querySelector(".ff-tray") !== null');
    $pointer->click('a[href$="/modern/test-classic-board"]');
    $pointer->untilScript('document.querySelector(".ff-board") === null && document.querySelector("[x-sortable]") !== null');

    $page->assertScript('[...document.styleSheets].some((sheet) => (sheet.href ?? "").includes("flowforge-modern.css"))');

    expect(json_decode($page->script($probe), true))->toBe($classic)
        ->and($classic['modernElements'])->toBe(0)
        ->and($classic['modernBehaviour'])->toBeFalse();
});

test('the lane header row stays visible while the lanes scroll', function () {
    foreach (range(1, 36) as $index) {
        Task::query()->create([
            'title' => "Card {$index}",
            'status' => 'todo',
            'priority' => ['low', 'medium', 'high'][$index % 3],
            'order_position' => ($index * 1000) . '.0000000000',
        ]);
    }

    $page = boardPage('/modern/test-grouped-board?groupBy=priority');
    $pointer = Pointer::on($page);
    $page->script('document.querySelector(".ff-lanes").scrollTop = 700');
    $page->assertScript('document.querySelector(".ff-lanes").scrollTop > 400')
        ->assertScript(<<<'JS'
            (() => {
                const lanes = document.querySelector('.ff-lanes').getBoundingClientRect();
                const heads = document.querySelector('.ff-lanes-heads').getBoundingClientRect();
                const topmost = document.elementFromPoint(heads.left + 40, heads.top + heads.height / 2);

                return Math.abs(heads.top - lanes.top) <= 1 && topmost.closest('.ff-lanes-heads') !== null;
            })()
        JS);
});

test('a card leaves room for an overlay scrollbar on both sides of its tray', function () {
    boardSeed();
    $page = boardPage('/modern/test-board');

    $page->assertScript(<<<'JS'
        (() => {
            const cards = document.querySelector('[data-column-id="todo"]').getBoundingClientRect();
            const card = document.querySelector('[data-column-id="todo"] > [data-card-id]').getBoundingClientRect();

            return card.left - cards.left === 12 && cards.right - card.right === 12;
        })()
    JS);
});

test('a full column and a collapsed rail carry their server-side states', function () {
    boardSeed();
    $page = boardPage('/modern/test-browser-board');

    $page->assertScript('document.querySelector("[data-column-root=\"todo\"]").hasAttribute("data-full")')
        ->assertScript('! document.querySelector("[data-column-root=\"in_progress\"]").hasAttribute("data-full")')
        ->assertScript('document.querySelector("[data-column-root=\"completed\"]").hasAttribute("data-collapsed")')
        ->assertScript('! document.querySelector("[data-column-root=\"todo\"]").hasAttribute("data-collapsed")');

    expect(boardCount($page, 'todo'))->toBe('3/3')
        ->and(boardStyle($page, '[data-column-root="todo"] .ff-count', 'color'))->not->toBe(boardStyle($page, '[data-column-root="in_progress"] .ff-count', 'color'))
        ->and(boardStyle($page, '[data-column-root="completed"]', 'width'))->toBe('48px')
        ->and(boardStyle($page, '[data-column-root="todo"]', 'width'))->toBe('300px');
});

test('the board never paints unstyled on a cold load', function () {
    boardSeed();
    $page = boardPage('/modern/test-classic-board');
    $pointer = Pointer::on($page);

    PageHandle::navigateWithInitScript($page, '/modern/test-board', boardTraySampler(5000));

    $pointer->untilScript('window.__trayBackgrounds.length >= 30');
    $backgrounds = $page->script('window.__trayBackgrounds');

    expect($backgrounds[0])->not->toBe('rgba(0, 0, 0, 0)')
        ->and($backgrounds[0])->toBe(end($backgrounds));
});

test('the board never paints unstyled on a repeat spa visit', function () {
    boardSeed();
    $page = boardPage('/modern/test-classic-board');
    $pointer = Pointer::on($page);

    $pointer->click('a[href$="/modern/test-board"]');
    $pointer->untilScript('document.querySelector(".ff-board") !== null');
    $pointer->click('a[href$="/modern/test-classic-board"]');
    $pointer->untilScript('document.querySelector(".ff-board") === null && document.querySelector("[x-sortable]") !== null');

    $page->script(boardTraySampler(20000));
    $pointer->click('a[href$="/modern/test-board"]');
    $pointer->untilScript('window.__trayBackgrounds.length >= 30');
    $backgrounds = $page->script('window.__trayBackgrounds');

    expect($backgrounds[0])->not->toBe('rgba(0, 0, 0, 0)')
        ->and($backgrounds[0])->toBe(end($backgrounds));

    $page->assertScript('document.head.querySelectorAll(\'link[href*="flowforge-modern.css"]\').length === 1')
        ->assertScript('document.querySelectorAll(\'link[href*="flowforge-modern.css"]\').length === 1');
});

test('a click that drifts a few pixels opens the card action instead of dragging', function () {
    [$a, $b, $c] = boardSeed();
    $page = boardPage('/modern/test-draggable-card-action-board');
    $pointer = Pointer::on($page);

    $pointer->press(boardCard($a) . ' .ff-card-body')->nudge(3, 2)->release();
    $pointer->untilScript("[...document.querySelectorAll('.fi-modal-window')].some((modal) => modal.checkVisibility())");

    expect(boardStored('todo'))->toBe([$a->id, $b->id, $c->id]);
});
