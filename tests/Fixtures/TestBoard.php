<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\BoardPage;
use Relaticle\Flowforge\Column;

class TestBoard extends BoardPage
{
    public bool $showAllColumns = false;

    public bool $hideEverything = false;

    public bool $collapseEmpty = false;

    public bool $withHeaderToolbar = false;

    public function getEloquentQuery(): Builder
    {
        return Task::query();
    }

    public function board(Board $board): Board
    {
        return $board
            ->collapseEmptyColumns($this->collapseEmpty)
            ->headerToolbar($this->withHeaderToolbar)
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->searchable(['title'])
            ->columnIdentifier('status')
            ->positionIdentifier('order_position')
            ->columns([
                Column::make('todo')->label('To Do')->color('gray')->visible(fn () => ! $this->hideEverything),
                Column::make('hidden_column')->visible(fn () => $this->showAllColumns && ! $this->hideEverything)->label('Hidden Column'),
                Column::make('archived')->label('Archived Column')->hidden(true),
                Column::make('in_progress')->label('In Progress')->color('blue')->visible(fn () => ! $this->hideEverything),
                Column::make('completed')->label('Completed')->color('green')->visible(fn () => ! $this->hideEverything),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportBoard')->label('Export board'),
        ];
    }
}
