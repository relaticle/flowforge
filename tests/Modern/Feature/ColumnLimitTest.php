<?php

declare(strict_types=1);

use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;

function todoCard(string $title, string $position): Task
{
    return Task::create(['title' => $title, 'status' => 'todo', 'order_position' => $position]);
}

function addRowButton(string $html, string $column): string
{
    preg_match_all('/<button[^>]*ff-add-row[^>]*>/s', $html, $matches);

    foreach ($matches[0] as $button) {
        if (preg_match('/u0022' . preg_quote($column, '/') . '\\\\u0022/', $button)) {
            return $button;
        }
    }

    return '';
}

function headerButtons(string $html): array
{
    preg_match_all('/<button[^>]*fi-icon-btn[^>]*>/s', $html, $matches);

    return $matches[0];
}

beforeEach(function () {
    todoCard('Alpha', '65535.0000000000');
    todoCard('Beta', '131070.0000000000');
});

test('the header shows the count against the limit', function () {
    Livewire::test(TestBoard::class, ['todoLimit' => 3])->assertSeeHtml('>2/3</span>');
});

test('the limit counts cards that a search hides', function () {
    Livewire::test(TestBoard::class, ['todoLimit' => 3])
        ->set('tableSearch', 'Alpha')
        ->assertSeeHtml('>2/3</span>');
});

test('a column at its limit is marked full', function () {
    expect(Livewire::test(TestBoard::class, ['todoLimit' => 2])->html())->toMatch('/data-column-root="todo"[^>]*\sdata-full[\s>]/');
});

test('a column below its limit is not marked full', function () {
    expect(Livewire::test(TestBoard::class, ['todoLimit' => 3])->html())->not->toMatch('/\sdata-full[\s>]/');
});

test('the column root exposes its limit for the drag script', function () {
    Livewire::test(TestBoard::class, ['todoLimit' => 3])
        ->assertSeeHtml('data-column-limit="3"')
        ->assertSeeHtml('data-column-total="2"');
});

test('the header add button names itself through a tooltip and never a native title', function () {
    $html = Livewire::test(TestBoard::class, ['withCreateAction' => true])->html();

    preg_match('/<div class="ff-column-actions">.*?<\/button>/s', $html, $header);

    expect($header[0])->toContain('x-tooltip')->not->toContain('title=');
});

test('a full column refuses new cards', function () {
    $component = Livewire::test(TestBoard::class, ['todoLimit' => 2, 'withCreateAction' => true]);
    $todoRow = addRowButton($component->html(), 'todo');

    expect($todoRow)->toMatch('/\sdisabled\s/')
        ->and($todoRow)->toContain("content: '" . __('flowforge::flowforge.limit_reached') . "'")->not->toContain('title=')
        ->and(addRowButton($component->html(), 'in_progress'))->toContain('ff-add-row')->not->toMatch('/\sdisabled\s/');

    $component
        ->assertActionDisabled(TestAction::make('createTask')->arguments(['column' => 'todo']))
        ->callAction(TestAction::make('createTask')->arguments(['column' => 'todo']), ['title' => 'Overflow']);

    expect(Task::where('title', 'Overflow')->exists())->toBeFalse();
});

test('a full column disables its header add button with a tooltip', function () {
    $buttons = headerButtons(Livewire::test(TestBoard::class, ['todoLimit' => 2, 'withCreateAction' => true])->html());

    expect($buttons)->toHaveCount(3)
        ->and($buttons[0])->toContain('aria-disabled="true"')
        ->and($buttons[0])->toContain('Limit reached')
        ->and($buttons[1])->not->toContain('aria-disabled="true"')
        ->and($buttons[1])->not->toContain('Limit reached')
        ->and($buttons[2])->not->toContain('aria-disabled="true"');
});

test('a search that hides cards does not open a full column', function () {
    $component = Livewire::test(TestBoard::class, ['todoLimit' => 2, 'withCreateAction' => true])
        ->set('tableSearch', 'Alpha')
        ->assertSeeHtml('data-column-total="2"')
        ->assertActionDisabled(TestAction::make('createTask')->arguments(['column' => 'todo']))
        ->callAction(TestAction::make('createTask')->arguments(['column' => 'todo']), ['title' => 'Sneaky']);

    expect(Task::where('title', 'Sneaky')->exists())->toBeFalse()
        ->and(addRowButton($component->html(), 'todo'))->toMatch('/\sdisabled\s/');
});

test('an unauthorized but visible create action keeps its add row disabled below the limit', function () {
    $component = Livewire::test(TestBoard::class, ['todoLimit' => 3, 'withCreateAction' => true, 'denyCreateAction' => true]);

    expect(addRowButton($component->html(), 'todo'))->toContain('ff-add-row')->toMatch('/\sdisabled\s/');
});

test('a column below its limit accepts new cards', function () {
    $component = Livewire::test(TestBoard::class, ['todoLimit' => 3, 'withCreateAction' => true]);

    expect(addRowButton($component->html(), 'todo'))->toContain('ff-add-row')->not->toMatch('/\sdisabled\s/');

    $component
        ->assertActionEnabled(TestAction::make('createTask')->arguments(['column' => 'todo']))
        ->callAction(TestAction::make('createTask')->arguments(['column' => 'todo']), ['title' => 'Third']);

    expect(Task::where('title', 'Third')->value('status'))->toBe('todo');
});

test('a limited column is counted once per render', function () {
    $component = Livewire::test(TestBoard::class, ['todoLimit' => 3, 'withCreateAction' => true]);

    DB::enableQueryLog();
    $component->call('$refresh');
    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn (string $sql): bool => str_contains($sql, 'count(') && str_contains($sql, '"status" = ?'));
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1);
});
