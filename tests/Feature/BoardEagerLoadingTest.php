<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Relaticle\Flowforge\Tests\Fixtures\Project;
use Relaticle\Flowforge\Tests\Fixtures\Task;
use Relaticle\Flowforge\Tests\Fixtures\TestBoard;

test('runs the board query eager loads once for every column', function () {
    $alpha = Project::factory()->create(['name' => 'Alpha']);
    $beta = Project::factory()->create(['name' => 'Beta']);
    Task::factory()->todo()->create(['project_id' => $alpha->id]);
    Task::factory()->inProgress()->create(['project_id' => $beta->id]);
    Task::factory()->completed()->create(['project_id' => $alpha->id]);

    $board = Livewire::test(TestBoard::class, ['eagerLoadProject' => true])->instance()->getBoard();

    DB::enableQueryLog();
    $columns = $board->getViewData()['columns'];
    $projectQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "projects"'));
    DB::disableQueryLog();

    $projectNames = collect($columns)->map(fn (array $column): array => collect($column['items'])
        ->map(fn (array $item): string => $item['model']->getRelation('project')->name)
        ->all());

    expect($projectQueries)->toHaveCount(1)
        ->and($projectNames->all())->toBe([
            'todo' => ['Alpha'],
            'in_progress' => ['Beta'],
            'completed' => ['Alpha'],
        ]);
});
