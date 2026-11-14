<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestBoard;

function menuXpath(string $html, string $query): DOMNodeList
{
    $document = new DOMDocument;
    libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();

    return (new DOMXPath($document))->query($query);
}

function menuHasClass(string $class): string
{
    return "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')";
}

describe('column header menu', function () {
    test('renders the host block and the column actions in the same dropdown panel', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true])->html();

        $panels = menuXpath($html, '//div[' . menuHasClass('fi-dropdown-panel') . "][contains(., 'Block for To Do')][contains(., 'Archive column')]");

        expect($panels)->toHaveCount(1)
            ->and(menuXpath($html, '//div[' . menuHasClass('fi-dropdown-panel') . ']'))->toHaveCount(3);
    });

    test('puts the host block before the actions', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true])->html();

        expect(strpos($html, 'Block for To Do'))->toBeLessThan(strpos($html, 'Archive column'));
    });

    test('replaces the separate actions trigger and keeps the add card button', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true, 'withCreateAction' => true])->html();

        expect(menuXpath($html, '//div[' . menuHasClass('ff-column-actions') . ']//div[' . menuHasClass('fi-dropdown') . ']'))->toHaveCount(0)
            ->and(menuXpath($html, '//div[' . menuHasClass('ff-column-actions') . ']//button'))->toHaveCount(3);
    });

    test('a collapsible header with a menu leaves the double click to the menu', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true, 'collapsible' => true])->html();

        expect($html)->not->toContain('x-on:dblclick');
    });

    test('the trigger is a div with the button role so a header drag handle does not filter it out', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true])->html();

        $triggers = menuXpath($html, '//div[' . menuHasClass('fi-dropdown-trigger') . "]/div[@role='button'][@tabindex='0'][" . menuHasClass('ff-column-heading') . ']');

        expect($triggers)->toHaveCount(3)
            ->and(menuXpath($html, '//div[' . menuHasClass('fi-dropdown-trigger') . ']//button'))->toHaveCount(0);
    });

    test('passes the column to the closure and renders only actions when the block is null', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true, 'nullMenuBlock' => true])->html();

        expect($html)->not->toContain('ff-test-menu-block')
            ->and(menuXpath($html, '//div[' . menuHasClass('fi-dropdown-panel') . "][contains(., 'Archive column')]"))->toHaveCount(3);
    });

    test('a collapsed column renders no menu', function () {
        $html = Livewire::test(TestBoard::class, ['withHeaderMenu' => true, 'collapsedIds' => ['in_progress']])->html();

        expect($html)->not->toContain('Block for In Progress')
            ->and($html)->toContain('Block for To Do');
    });

    test('without a header menu the header keeps the actions group', function () {
        $html = Livewire::test(TestBoard::class, ['withColumnActions' => true])->html();

        expect(menuXpath($html, '//div[' . menuHasClass('ff-column-actions') . ']//div[' . menuHasClass('fi-dropdown') . ']'))->toHaveCount(3)
            ->and(menuXpath($html, "//div[@role='button'][" . menuHasClass('ff-column-heading') . ']'))->toHaveCount(0);
    });
});
