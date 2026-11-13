<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Illuminate\Database\Eloquent\Builder;
use Relaticle\Flowforge\Board;
use Relaticle\Flowforge\Column;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Modern\BoardPage;

class TestCastGroupedBoard extends BoardPage
{
    public bool $groupedQuery = false;

    public bool $distinctJoin = false;

    public string $orderShape = 'none';

    public function getEloquentQuery(): Builder
    {
        $query = CastTask::query();

        if ($this->groupedQuery) {
            $query->groupBy('tasks.id', 'tasks.priority');
        }

        if ($this->distinctJoin) {
            $query->join('task_user', 'task_user.task_id', '=', 'tasks.id')->select('tasks.*')->distinct();
        }

        return match ($this->orderShape) {
            'title_desc' => $query->orderBy('tasks.title', 'desc'),
            'unselected' => $query->orderBy('task_user.user_id', 'desc'),
            'raw' => $query->orderByRaw('lower(tasks.title) desc'),
            default => $query,
        };
    }

    public function board(Board $board): Board
    {
        return $board
            ->query($this->getEloquentQuery())
            ->recordTitleAttribute('title')
            ->columnIdentifier('status')
            ->positionIdentifier('order_position')
            ->columns([
                Column::make('todo')->label('To Do'),
                Column::make('in_progress')->label('In Progress'),
            ])
            ->groupings([
                Grouping::attribute('priority')->label('Level'),
                Grouping::attribute('description')->label('State'),
                Grouping::attribute('estimated_hours')->label('Effort'),
                Grouping::attribute('actual_hours')->label('Flagged'),
            ]);
    }
}
