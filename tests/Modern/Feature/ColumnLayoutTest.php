<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;

test('an empty column shows no placeholder sentence and no dashed box', function () {
    Livewire::test(TestBoard::class)
        ->assertStatus(200)
        ->assertDontSee('in this column')
        ->assertDontSeeHtml('border-dashed');
});

test('each column tray is labelled with its name and card count', function () {
    Task::create(['title' => 'Only Task', 'status' => 'todo', 'order_position' => '65535.0000000000']);

    $component = Livewire::test(TestBoard::class);
    $board = $component->instance()->getBoard();

    $component
        ->assertSeeHtml('aria-label="To Do, 1 ' . e($board->getCardLabel()) . '"')
        ->assertSeeHtml('aria-label="In Progress, 0 ' . e($board->getPluralCardLabel()) . '"');
});

test('the column count renders as plain text, not a badge', function () {
    Task::create(['title' => 'Only Task', 'status' => 'todo', 'order_position' => '65535.0000000000']);

    $html = Livewire::test(TestBoard::class)->html();

    expect($html)->toContain('ff-count')
        ->and($html)->not->toContain('fi-badge');
});

test('a colored column shows a dot before its label', function () {
    Livewire::test(TestBoard::class)->assertSeeHtml('ff-column-dot');
});

test('a hex column color draws its dot in that exact color', function () {
    $html = Livewire::test(TestBoard::class, ['hexCompleted' => true])->html();

    expect($html)->toContain('style="--color-500:#ff5454"');
});

test('the header sits above the tray, outside it', function () {
    $html = Livewire::test(TestBoard::class)->html();

    expect(strpos($html, 'ff-column-header'))->toBeLessThan(strpos($html, 'ff-tray'));
});

test('each column root exposes its id and total for the drag script', function () {
    Task::create(['title' => 'Only Task', 'status' => 'todo', 'order_position' => '65535.0000000000']);

    Livewire::test(TestBoard::class)
        ->assertSeeHtml('data-column-root="todo"')
        ->assertSeeHtml('data-column-total="1"');
});

test('a markup column label reads as plain text in the tray label', function () {
    $component = Livewire::test(TestBoard::class, ['htmlLabel' => true]);
    $board = $component->instance()->getBoard();

    $component->assertSeeHtml('aria-label="In Progress, 0 ' . e($board->getPluralCardLabel()) . '"');

    expect($component->html())->not->toContain('aria-label="&lt;em&gt;')
        ->and($component->html())->not->toContain('&amp;lt;em');
});

test('a collapsed rail titles itself with the plain column label', function () {
    Task::create(['title' => 'Only Task', 'status' => 'todo', 'order_position' => '65535.0000000000']);

    $html = Livewire::test(TestBoard::class, ['htmlLabel' => true, 'collapseEmpty' => true])->html();

    expect($html)->toContain('title="In Progress"')
        ->and($html)->not->toContain('title="&lt;em&gt;');
});
