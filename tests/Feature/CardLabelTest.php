<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\TestBoard;
use Relaticle\Flowforge\Tests\Fixtures\TestBoardResourcePage;
use Relaticle\Flowforge\Tests\Fixtures\TestResource;

describe('card labels', function () {
    test('names the cards after the resource of the board page', function () {
        $board = Livewire::test(TestBoardResourcePage::class)->instance()->getBoard();

        expect($board->getCardLabel())->toBe(TestResource::getModelLabel())
            ->and($board->getPluralCardLabel())->toBe(TestResource::getPluralModelLabel());
    });

    test('names the cards after the resource registered for the model', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard();

        expect($board->getCardLabel())->toBe('Task')
            ->and($board->getPluralCardLabel())->toBe('Tasks');
    });

    test('explicit labels win over the model', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard()
            ->cardLabel('Ticket')
            ->pluralCardLabel('Tickets');

        expect($board->getCardLabel())->toBe('Ticket')
            ->and($board->getPluralCardLabel())->toBe('Tickets');
    });

    test('falls back to the translated label without a query', function () {
        $board = Livewire::test(TestBoard::class)->instance()->getBoard()->query(fn () => null);

        expect($board->getCardLabel())->toBe(__('flowforge::flowforge.card_label'))
            ->and($board->getPluralCardLabel())->toBe(__('flowforge::flowforge.plural_card_label'));
    });

    test('the empty column names the cards', function () {
        Livewire::test(TestBoard::class)
            ->assertStatus(200)
            ->assertSee('No tasks in this column');
    });
});
