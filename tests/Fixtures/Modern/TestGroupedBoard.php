<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PDOException;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;
use Relaticle\Flowforge\Modern\BoardPage;

class TestGroupedBoard extends BoardPage
{
    public int $lanesPerPage = 10;

    public int $cardsPerCell = 20;

    public bool $withHeaderToolbar = false;

    public bool $collapseEmpty = false;

    public ?int $inProgressLimit = null;

    public bool $duplicateOnPersist = false;

    public bool $boundHaving = false;

    public string $orderShape = 'none';

    public int $subqueryUserId = 0;

    public array $authorizedLanes = [];

    public function getEloquentQuery(): Builder
    {
        $query = Task::query()->with('assignees');

        if ($this->boundHaving) {
            $query->groupByRaw('tasks.id, ?', [1])->havingRaw('count(*) > ?', [0]);
        }

        return match ($this->orderShape) {
            'title_desc' => $query->orderBy('title', 'desc'),
            'hours_title' => $query->orderBy('estimated_hours', 'desc')->orderBy('title'),
            'bound_raw' => $query
                ->select('tasks.*')
                ->selectRaw('? as picked', ['picked'])
                ->where('tasks.title', '<>', 'Excluded')
                ->orderByRaw('case when tasks.title = ? then 0 else 1 end', ['Gamma']),
            'bound_subquery' => $query
                ->select('tasks.*')
                ->selectRaw('? as picked', ['picked'])
                ->where('tasks.title', '<>', 'Excluded')
                ->orderBy(
                    DB::table('task_user')
                        ->selectRaw('count(*)')
                        ->whereColumn('task_user.task_id', 'tasks.id')
                        ->where('task_user.user_id', '<>', $this->subqueryUserId),
                    'desc',
                ),
            'expression' => $query->orderBy(DB::raw('length(tasks.title)'), 'desc'),
            'alias' => $query->withCount('assignees')->orderBy('assignees_count', 'desc'),
            default => $query,
        };
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->searchable(['title'])
            ->columnIdentifier('status')
            ->positionIdentifier('order_position')
            ->cardsPerColumn($this->cardsPerCell)
            ->lanesPerPage($this->lanesPerPage)
            ->headerToolbar($this->withHeaderToolbar)
            ->collapseEmptyColumns($this->collapseEmpty)
            ->columns([
                Column::make('todo')->label('To Do'),
                Column::make('in_progress')->label('In Progress')->limit($this->inProgressLimit),
            ])
            ->groupings([
                Grouping::attribute('priority')->label('Priority'),
                $this->assigneeGrouping(),
                $this->creatorGrouping(),
                Grouping::make('probe')
                    ->label('Probe')
                    ->lanes(fn (): array => [
                        Lane::make('low')->query(fn (Builder $query): Builder => $query->where('priority', 'low')),
                        Lane::make('high')->query(fn (Builder $query): Builder => $query->where('priority', 'high')),
                    ])
                    ->moveUsing(fn (Task $card) => $card->forceFill(['title' => (string) $card->status])->save()),
                $this->assigneeGrouping('attach')
                    ->moveUsing(fn (Task $card, Lane $to) => $card->assignees()->attach($to->getName())),
                Grouping::make('flaky')
                    ->label('Flaky')
                    ->lanes(fn (): array => [
                        Lane::make('low')->query(fn (Builder $query): Builder => $query->where('priority', 'low')),
                        Lane::make('high')->query(fn (Builder $query): Builder => $query->where('priority', 'high')),
                    ])
                    ->moveUsing(function (): void {
                        if (Cache::pull('flaky-lane-callback')) {
                            throw new QueryException('sqlite', 'x', [], new PDOException('UNIQUE constraint failed'));
                        }
                    }),
                Grouping::make('strict')
                    ->label('Strict')
                    ->lanes(fn (): array => [
                        Lane::make('low')->query(fn (Builder $query): Builder => $query->where('priority', 'low')),
                        Lane::make('high')->query(fn (Builder $query): Builder => $query->where('priority', 'high')),
                    ])
                    ->moveUsing(fn () => throw CardMoveRejectedException::because('Nope')),
            ]);
    }

    protected function authorizeCardMove(Model $card, string $targetColumnId, ?string $toLaneId = null): void
    {
        $this->authorizedLanes[] = $toLaneId;
    }

    protected function persistCardMove(Model $card, mixed $columnValue, string $position): void
    {
        parent::persistCardMove($card, $columnValue, $position);

        if ($this->duplicateOnPersist) {
            $this->duplicateOnPersist = false;

            throw new QueryException('sqlite', 'x', [], new PDOException('UNIQUE constraint failed'));
        }
    }

    protected function assigneeGrouping(string $name = 'assignee'): Grouping
    {
        return Grouping::make($name)
            ->label('Assignee')
            ->lanes(fn (Builder $boardQuery): array => User::query()
                ->whereIn('id', DB::table('task_user')->whereIn('task_id', (clone $boardQuery)->select('tasks.id'))->select('user_id'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): Lane => Lane::make($user->id)
                    ->label($user->name)
                    ->query(fn (Builder $query): Builder => $query->whereHas('assignees', fn (Builder $assignees): Builder => $assignees->whereKey($user->id))))
                ->all())
            ->emptyLane('Unassigned', fn (Builder $query): Builder => $query->whereDoesntHave('assignees'))
            ->moveUsing(function (Task $card, Lane $from, Lane $to): void {
                if ($from->getName() !== Grouping::EMPTY_LANE) {
                    $card->assignees()->detach($from->getName());
                }

                if ($to->getName() !== Grouping::EMPTY_LANE) {
                    $card->assignees()->syncWithoutDetaching([$to->getName()]);
                }
            });
    }

    protected function creatorGrouping(): Grouping
    {
        return Grouping::make('creator')
            ->label('Creator')
            ->lanes(fn (Builder $boardQuery): array => User::query()
                ->whereIn('id', (clone $boardQuery)->select('created_by'))
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): Lane => Lane::make($user->id)
                    ->label($user->name)
                    ->query(fn (Builder $query): Builder => $query->where('created_by', $user->id)))
                ->all());
    }
}
