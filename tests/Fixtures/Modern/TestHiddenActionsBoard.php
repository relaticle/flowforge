<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Modern\BoardPage;

class TestHiddenActionsBoard extends BoardPage
{
    public function getEloquentQuery(): Builder
    {
        return Task::query();
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->columnIdentifier('status')
            ->columns([
                Column::make('todo')->label('To Do'),
            ])
            ->columnActions([
                Action::make('renameColumn')->action(function (): void {})->hidden(),
                Action::make('clearColumn')->action(function (): void {})->hidden(),
            ])
            ->cardActions([
                Action::make('edit')->action(function (): void {})->hidden(),
                Action::make('delete')->action(function (): void {})->hidden(),
            ]);
    }
}
