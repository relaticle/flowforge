<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Components\CardAvatar;
use Relaticle\Flowforge\Components\CardFlex;
use Relaticle\Flowforge\Components\CardMeta;
use Relaticle\Flowforge\Components\CardTag;
use Relaticle\Flowforge\Components\CardTitle;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;
use Relaticle\Flowforge\Modern\BoardPage;

class TestContractBoard extends BoardPage
{
    public bool $withHeaderToolbar = false;

    public bool $sortable = true;

    public ?string $filtersLayout = null;

    public ?string $resetFiltersLabel = null;

    public ?string $cardActionName = null;

    public function getEloquentQuery(): Builder
    {
        return Task::query()->with('assignees');
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->searchable(['title'])
            ->headerToolbar($this->withHeaderToolbar)
            ->collapseEmptyColumns()
            ->columnIdentifier('status')
            ->when($this->sortable, fn (Board $board): Board => $board->positionIdentifier('order_position'))
            ->when($this->filtersLayout, fn (Board $board, string $layout): Board => $board->filters(
                [SelectFilter::make('priority')->options(['low' => 'Low', 'high' => 'High'])],
                constant(FiltersLayout::class . '::' . $layout),
            ))
            ->when($this->resetFiltersLabel, fn (Board $board, string $label): Board => $board->filtersResetAction(
                fn (Action $action): Action => $action->label($label),
            ))
            ->cardAction($this->cardActionName)
            ->columnSummary(fn (Builder $columnQuery): string => $columnQuery->count() . ' shown')
            ->createCardAction(
                CreateAction::make('createTask')
                    ->model(Task::class)
                    ->schema([TextInput::make('title')->required()])
                    ->mutateDataUsing(fn (array $data, array $arguments): array => [...$data, 'status' => $arguments['column'] ?? 'todo']),
            )
            ->columns([
                Column::make('todo')->label('To Do')->color('gray')->icon('heroicon-o-bolt')->limit(1),
                Column::make('in_progress')->label('In Progress')->color('blue'),
                Column::make('completed')->label('Completed'),
            ])
            ->groupings([
                Grouping::attribute('priority')->label('Priority'),
                Grouping::make('owner')->label('Owner')->lanes(fn (): array => [
                    Lane::make('avatar')->avatar('https://example.test/lane.png')->query(fn (Builder $query): Builder => $query->where('priority', 'high')),
                    Lane::make('icon')->icon('heroicon-o-user')->query(fn (Builder $query): Builder => $query->where('priority', 'low')),
                    Lane::make('color')->color('danger')->query(fn (Builder $query): Builder => $query->where('priority', 'medium')),
                ]),
            ])
            ->cardActions([
                Action::make('editTask')->action(function (): void {}),
                Action::make('view')->url(fn (Task $record): string => "https://example.test/tasks/{$record->getKey()}"),
            ])
            ->cardSchema(fn (Schema $schema): Schema => $schema->components([
                CardTag::make('priority'),
                CardTitle::make(),
                CardMeta::make([
                    CardTag::make('status'),
                    TextEntry::make('description'),
                ]),
                CardFlex::make([CardTag::make('priority'), CardAvatar::make('assignees')]),
                CardAvatar::make('assignees')->nameUsing(fn (User $user): string => $user->name),
            ]));
    }
}
