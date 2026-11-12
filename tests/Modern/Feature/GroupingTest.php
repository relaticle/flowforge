<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Relaticle\Flowforge\Exceptions\CardMoveRejectedException;
use Relaticle\Flowforge\Grouping;
use Relaticle\Flowforge\Lane;
use Relaticle\Flowforge\Tests\Fixtures\Modern\CastTask;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestCastGroupedBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestGroupedBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestJoinedColumnBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\User;

beforeEach(function () {
    CastTask::$orderScope = false;
    CastTask::$vetoSaves = false;
    CastTask::$written = null;
});

function groupedTask(string $title, string $status, string $priority, string $position): Task
{
    return Task::create(['title' => $title, 'status' => $status, 'priority' => $priority, 'order_position' => $position]);
}

function laneIds(array $laneData): array
{
    return array_column($laneData['lanes'], 'id');
}

function cellTitles(array $laneData, string $laneId, string $columnId): array
{
    $lane = collect($laneData['lanes'])->firstWhere('id', $laneId);

    return array_map(fn (array $record): string => $record['title'], $lane['cells'][$columnId]['items']);
}

test('attribute lanes come from the cards in view, busiest first', function () {
    groupedTask('Low one', 'todo', 'low', '65535.0000000000');
    groupedTask('Low two', 'in_progress', 'low', '65535.0000000000');
    groupedTask('High one', 'todo', 'high', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $lanes = $board->getLanes($board->getGrouping('priority'));

    expect(array_map(fn ($lane) => $lane->getName(), $lanes))->toBe(['low', 'high', Grouping::EMPTY_LANE])
        ->and((string) $lanes[0]->getLabel())->toBe('Low');
});

test('attribute lanes survive a board query with a bound group by and having', function () {
    groupedTask('Low one', 'todo', 'low', '65535.0000000000');
    groupedTask('High one', 'todo', 'high', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['boundHaving' => true])->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('priority'), 10, [])))->toBe(['high', 'low']);
});

test('attribute lanes follow the board search', function () {
    groupedTask('Low one', 'todo', 'low', '65535.0000000000');
    groupedTask('High one', 'todo', 'high', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->set('tableSearch', 'High')->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('priority'), 10, [])))->toBe(['high']);
});

test('each card lands in its own column cell', function () {
    groupedTask('Low todo', 'todo', 'low', '65535.0000000000');
    groupedTask('Low doing', 'in_progress', 'low', '65535.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);

    expect(cellTitles($data, 'low', 'todo'))->toBe(['Low todo'])
        ->and(cellTitles($data, 'low', 'in_progress'))->toBe(['Low doing']);
});

test('a lane whose cards all sit in hidden columns is not rendered', function () {
    groupedTask('Visible', 'todo', 'low', '65535.0000000000');
    groupedTask('Parked', 'archived', 'urgent', '65535.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('priority'), 10, [])))->toBe(['low']);
});

test('the empty lane renders last', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    groupedTask('Assigned', 'todo', 'low', '65535.0000000000')->assignees()->attach($emma);
    groupedTask('Unassigned', 'todo', 'low', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('assignee'), 10, [])))
        ->toBe([(string) $emma->id, Grouping::EMPTY_LANE]);
});

test('a card with two assignees appears in both lanes', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    $marcus = User::factory()->create(['name' => 'Marcus Johnson']);
    groupedTask('Shared', 'todo', 'low', '65535.0000000000')->assignees()->attach([$emma->id, $marcus->id]);

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('assignee'), 10, []);

    expect(cellTitles($data, (string) $emma->id, 'todo'))->toBe(['Shared'])
        ->and(cellTitles($data, (string) $marcus->id, 'todo'))->toBe(['Shared']);
});

test('each cell is capped at the page size and still reports its full total', function () {
    groupedTask('First', 'todo', 'low', '65535.0000000000');
    groupedTask('Second', 'todo', 'low', '131070.0000000000');
    groupedTask('Third', 'todo', 'low', '196605.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 2])->instance()->getBoard();
    $lane = $board->getLaneViewData($board->getGrouping('priority'), 10, [])['lanes'][0];

    expect($lane['cells']['todo']['items'])->toHaveCount(2)
        ->and($lane['cells']['todo']['total'])->toBe(3)
        ->and(array_column($lane['cells']['todo']['items'], 'title'))->toBe(['First', 'Second']);
});

test('a raised cell limit loads more cards for that cell only', function () {
    groupedTask('First', 'todo', 'low', '65535.0000000000');
    groupedTask('Second', 'todo', 'low', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1])->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, ['low' => ['todo' => 2]]);

    expect(cellTitles($data, 'low', 'todo'))->toBe(['First', 'Second']);
});

test('lanes beyond the visible count are reported, not loaded', function () {
    groupedTask('Low', 'todo', 'low', '65535.0000000000');
    groupedTask('High', 'todo', 'high', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 1, []);

    expect(laneIds($data))->toHaveCount(1)
        ->and($data['hasMoreLanes'])->toBeTrue();
});

test('each lane costs one record query, the empty lane included', function () {
    groupedTask('Low', 'todo', 'low', '65535.0000000000');
    groupedTask('Medium', 'todo', 'medium', '131070.0000000000');
    groupedTask('High', 'todo', 'high', '196605.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $board->getLaneViewData($board->getGrouping('priority'), 10, []);
    $recordQueries = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'flowforge_cell_row'));

    expect($recordQueries)->toHaveCount(4);
});

test('a board whose column lives on a joined table partitions by it', function () {
    $open = groupedTask('Open deal', 'todo', 'low', '65535.0000000000');
    $won = groupedTask('Won deal', 'todo', 'low', '131070.0000000000');
    DB::table('task_states')->insert([
        ['task_id' => $open->id, 'state_value' => 'open'],
        ['task_id' => $won->id, 'state_value' => 'won'],
    ]);

    $board = Livewire::test(TestJoinedColumnBoard::class)->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);

    $lane = $data['lanes'][0];

    expect(cellTitles($data, 'low', 'open'))->toBe(['Open deal'])
        ->and(cellTitles($data, 'low', 'won'))->toBe(['Won deal'])
        ->and($lane['cells']['open']['total'])->toBe(1)
        ->and($lane['cells']['won']['total'])->toBe(1);
});

test('an unknown grouping name resolves to none', function () {
    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    expect($board->getGrouping('nope'))->toBeNull()
        ->and($board->getGrouping(null))->toBeNull();
});

test('a lane query stops at the cell limit in SQL, per column', function () {
    groupedTask('First', 'todo', 'low', '65535.0000000000');
    groupedTask('Second', 'todo', 'low', '131070.0000000000');
    groupedTask('Third', 'todo', 'low', '196605.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $lane = $board->getLanes($board->getGrouping('priority'))[0];

    expect($board->getLaneRecords($lane, 2)->pluck('title')->all())->toBe(['First', 'Second']);
});

test('a raised limit on one cell leaves the other cell on the page size', function () {
    groupedTask('Todo one', 'todo', 'low', '65535.0000000000');
    groupedTask('Todo two', 'todo', 'low', '131070.0000000000');
    groupedTask('Doing one', 'in_progress', 'low', '65535.0000000000');
    groupedTask('Doing two', 'in_progress', 'low', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1])->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, ['low' => ['todo' => 2]]);

    expect(cellTitles($data, 'low', 'todo'))->toBe(['Todo one', 'Todo two'])
        ->and(cellTitles($data, 'low', 'in_progress'))->toBe(['Doing one']);
});

test('lane records keep the board query eager loads', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    groupedTask('Assigned', 'todo', 'low', '65535.0000000000')->assignees()->attach($emma);

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $lane = $board->getLanes($board->getGrouping('priority'))[0];

    expect($board->getLaneRecords($lane, 5)->first()->relationLoaded('assignees'))->toBeTrue();
});

test('a joined column partitions totals per column even with several cards in one state', function () {
    foreach (['One', 'Two'] as $index => $title) {
        $card = groupedTask($title, 'todo', 'low', (string) (($index + 1) * 65535) . '.0000000000');
        DB::table('task_states')->insert(['task_id' => $card->id, 'state_value' => 'open']);
    }

    $won = groupedTask('Three', 'todo', 'low', '196605.0000000000');
    DB::table('task_states')->insert(['task_id' => $won->id, 'state_value' => 'won']);

    $board = Livewire::test(TestJoinedColumnBoard::class)->instance()->getBoard();
    $lane = $board->getLaneViewData($board->getGrouping('priority'), 10, [])['lanes'][0];

    expect($lane['cells']['open']['total'])->toBe(2)
        ->and($lane['cells']['won']['total'])->toBe(1);
});

test('a joined board with no explicit select still reads its column identifier', function () {
    $open = groupedTask('Open deal', 'todo', 'low', '65535.0000000000');
    DB::table('task_states')->insert(['task_id' => $open->id, 'state_value' => 'open']);

    $board = Livewire::test(TestJoinedColumnBoard::class, ['withoutSelect' => true])->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);

    expect(cellTitles($data, 'low', 'open'))->toBe(['Open deal']);
});

test('an attribute grouping on a joined table is read-only', function () {
    $board = Livewire::test(TestJoinedColumnBoard::class)->instance()->getBoard();

    expect($board->getGrouping('state_value')->isWritable())->toBeFalse()
        ->and($board->getGrouping('priority')->isWritable())->toBeTrue();
});

test('a global scope ordering never reaches the lane or record queries', function () {
    CastTask::$orderScope = true;
    groupedTask('Zed', 'todo', '1', '65535.0000000000');

    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $board->getLaneViewData($board->getGrouping('priority'), 10, []);
    $sql = collect(DB::getQueryLog())->pluck('query');

    expect($sql->filter(fn (string $query): bool => str_contains($query, 'order by "title"')))->toBeEmpty()
        ->and($sql->filter(fn (string $query): bool => str_contains($query, 'flowforge_cell_row')))->not->toBeEmpty();
});

test('a board query that already groups does not duplicate lanes', function () {
    groupedTask('One', 'todo', '1', '65535.0000000000');
    groupedTask('Two', 'todo', '1', '131070.0000000000');

    $board = Livewire::test(TestCastGroupedBoard::class, ['groupedQuery' => true])->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('priority'), 10, [])))->toBe(['1']);
});

test('a distinct board query keeps one row per card and its true total', function () {
    $emma = User::factory()->create();
    $marcus = User::factory()->create();
    groupedTask('Shared', 'todo', '1', '65535.0000000000')->assignees()->attach([$emma->id, $marcus->id]);

    $board = Livewire::test(TestCastGroupedBoard::class, ['distinctJoin' => true])->instance()->getBoard();
    $lane = $board->getLaneViewData($board->getGrouping('priority'), 10, [])['lanes'][0];

    expect($lane['cells']['todo']['items'])->toHaveCount(1)
        ->and($lane['cells']['todo']['total'])->toBe(1);
});

test('the model default withCount is selected once per lane query', function () {
    groupedTask('One', 'todo', '1', '65535.0000000000');

    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $board->getLaneViewData($board->getGrouping('priority'), 10, []);
    $laneSql = collect(DB::getQueryLog())->pluck('query')->first(fn (string $query): bool => str_contains($query, 'flowforge_cell_row'));

    expect(substr_count($laneSql, 'inner join "task_user"'))->toBe(1);
});

test('probing for more lanes does not load the next lane', function () {
    groupedTask('Low', 'todo', 'low', '65535.0000000000');
    groupedTask('High', 'todo', 'high', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 1, []);
    $recordQueries = collect(DB::getQueryLog())->filter(fn (array $entry): bool => str_contains($entry['query'], 'flowforge_cell_row'));

    expect($data['hasMoreLanes'])->toBeTrue()
        ->and($recordQueries)->toHaveCount(1);
});

test('lanes may come from a collection', function () {
    $lanes = Grouping::make('c')
        ->lanes(fn () => collect([Lane::make('a'), Lane::make('b')]))
        ->getLanes(Task::query());

    expect(array_map(fn (Lane $lane): string => $lane->getName(), $lanes))->toBe(['a', 'b']);
});

test('enum cast lanes take their label and color from the enum', function () {
    groupedTask('Active', 'todo', '1', '65535.0000000000')->update(['description' => 'active']);

    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $lane = $board->getLaneViewData($board->getGrouping('description'), 10, [])['lanes'][0];

    expect((string) $lane['label'])->toBe('Active Item')
        ->and($lane['color'])->toBe('success');
});

test('an int backed enum accepts the string a driver returns', function () {
    groupedTask('Tier', 'todo', '1', '65535.0000000000');

    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();

    expect(laneIds($board->getLaneViewData($board->getGrouping('priority'), 10, [])))->toBe(['1']);
});

test('a string backed enum accepts the int a driver returns', function () {
    groupedTask('Tier', 'todo', '1', '65535.0000000000')->update(['estimated_hours' => 1]);

    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $lane = $board->getLaneViewData($board->getGrouping('estimated_hours'), 10, [])['lanes'][0];

    expect((string) $lane['label'])->toBe('Tier one');
});

test('a non numeric value in an int backed enum column keeps its raw label', function () {
    groupedTask('Odd', 'todo', 'urgent', '65535.0000000000');

    $lane = Grouping::attribute('priority')->getLanes(CastTask::query())[0];

    expect($lane->getName())->toBe('urgent')
        ->and((string) $lane->getLabel())->toBe('Urgent');
});

test('raw values label their own lane', function () {
    groupedTask('Zero', 'todo', '0', '65535.0000000000');
    groupedTask('Country', 'todo', 'USA', '131070.0000000000');
    groupedTask('Brand', 'todo', 'McDonald', '196605.0000000000');
    groupedTask('Stage', 'todo', 'in_review', '262140.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $labels = collect($board->getLaneViewData($board->getGrouping('priority'), 10, [])['lanes'])
        ->mapWithKeys(fn (array $lane): array => [$lane['id'] => (string) $lane['label']]);

    expect($labels->all())->toBe(['0' => '0', 'McDonald' => 'McDonald', 'USA' => 'USA', 'in_review' => 'In Review']);
});

test('an empty string value lands in the empty lane, not a blank lane', function () {
    groupedTask('Blank', 'todo', '', '65535.0000000000');
    groupedTask('Low', 'todo', 'low', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);

    expect(laneIds($data))->toBe(['low', Grouping::EMPTY_LANE])
        ->and(cellTitles($data, Grouping::EMPTY_LANE, 'todo'))->toBe(['Blank']);
});

test('the empty lane is named after the grouping label', function () {
    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $lanes = $board->getLanes($board->getGrouping('estimated_hours'));

    expect((string) end($lanes)->getLabel())->toBe('No effort');
});

test('a lane default label only rewrites identifier style names', function () {
    expect(Lane::make('USA')->getLabel())->toBe('USA')
        ->and(Lane::make('0')->getLabel())->toBe('0')
        ->and(Lane::make('in_progress')->getLabel())->toBe('In Progress');
});

test('moving into an attribute lane writes a non-fillable column', function () {
    $card = groupedTask('Card', 'todo', '1', '65535.0000000000');
    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $grouping = $board->getGrouping('description');
    $lanes = collect($board->getLanes($grouping));

    $grouping->moveCard(CastTask::findOrFail($card->id), $lanes->last(), Lane::make('pending'));

    expect(DB::table('tasks')->where('id', $card->id)->value('description'))->toBe('pending');
});

test('moving into a boolean lane writes a boolean', function () {
    $card = groupedTask('Card', 'todo', '1', '65535.0000000000');
    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $grouping = $board->getGrouping('actual_hours');

    $grouping->moveCard(CastTask::findOrFail($card->id), Lane::make('0'), Lane::make('1'));

    expect(CastTask::$written)->toBeTrue()
        ->and(DB::table('tasks')->where('id', $card->id)->value('actual_hours'))->toBe(1);
});

test('a vetoed save refuses the move instead of reporting success', function () {
    $card = groupedTask('Card', 'todo', '1', '65535.0000000000');
    $board = Livewire::test(TestCastGroupedBoard::class)->instance()->getBoard();
    $grouping = $board->getGrouping('description');
    CastTask::$vetoSaves = true;

    expect(fn () => $grouping->moveCard(CastTask::findOrFail($card->id), Lane::make('active'), Lane::make('pending')))
        ->toThrow(CardMoveRejectedException::class, __('flowforge::flowforge.lane_write_failed'));
});

test('a move callback may type the card by its model class and name it record', function () {
    $card = CastTask::findOrFail(groupedTask('Card', 'todo', '1', '65535.0000000000')->id);
    $seen = null;

    Grouping::make('typed')
        ->moveUsing(function (CastTask $model, Lane $to) use (&$seen): void {
            $seen = [$model->id, $to->getName()];
        })
        ->moveCard($card, Lane::make('a'), Lane::make('b'));

    expect($seen)->toBe([$card->id, 'b']);
});

test('a move callback may name the card record without a type', function () {
    $card = CastTask::findOrFail(groupedTask('Card', 'todo', '1', '65535.0000000000')->id);
    $seen = null;

    Grouping::make('named')
        ->moveUsing(function ($record, Lane $to) use (&$seen): void {
            $seen = $record instanceof CastTask ? $record->id : null;
        })
        ->moveCard($card, Lane::make('a'), Lane::make('b'));

    expect($seen)->toBe($card->id);
});

test('runs the board query eager loads once for every column', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    $marcus = User::factory()->create(['name' => 'Marcus Johnson']);
    groupedTask('Todo', 'todo', 'low', '65535.0000000000')->assignees()->attach($emma->id);
    groupedTask('Doing', 'in_progress', 'high', '65535.0000000000')->assignees()->attach($marcus->id);

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $columns = $board->getViewData()['columns'];
    $assigneeQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "users"'));
    DB::disableQueryLog();

    $assigneeNames = collect($columns)->map(fn (array $column): array => array_map(
        fn (array $item): array => $item['model']->getRelation('assignees')->pluck('name')->all(),
        $column['items'],
    ));

    expect($assigneeQueries)->toHaveCount(1)
        ->and($assigneeNames->all())->toBe([
            'todo' => [['Emma Collins']],
            'in_progress' => [['Marcus Johnson']],
        ]);
});

test('runs the board query eager loads once for every lane', function () {
    $emma = User::factory()->create(['name' => 'Emma Collins']);
    $marcus = User::factory()->create(['name' => 'Marcus Johnson']);
    groupedTask('Low', 'todo', 'low', '65535.0000000000')->assignees()->attach($emma->id);
    groupedTask('High', 'in_progress', 'high', '65535.0000000000')->assignees()->attach($marcus->id);
    groupedTask('Medium', 'todo', 'medium', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class)->instance()->getBoard();

    DB::enableQueryLog();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);
    $assigneeQueries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'from "users"'));
    DB::disableQueryLog();

    $assigneeNames = collect($data['lanes'])->mapWithKeys(fn (array $lane): array => [
        $lane['id'] => collect($lane['cells'])->flatMap(fn (array $cell): array => array_map(
            fn (array $item): array => $item['model']->getRelation('assignees')->pluck('name')->all(),
            $cell['items'],
        ))->all(),
    ]);

    expect($assigneeQueries)->toHaveCount(1)
        ->and($assigneeNames->sortKeys()->all())->toBe([
            'high' => [['Marcus Johnson']],
            'low' => [['Emma Collins']],
            'medium' => [[]],
        ]);
});

function laneCellQuery(string $board, array $props, string $grouping, int $limit): Builder
{
    $instance = Livewire::test($board, $props)->instance()->getBoard();
    $lane = $instance->getLanes($instance->getGrouping($grouping))[0];

    return (fn () => $this->getLaneRecordsQuery($lane, $limit))->call($instance);
}

function laneCellTitles(string $board, array $props, int $limit = 10, string $grouping = 'priority'): array
{
    return laneCellQuery($board, $props, $grouping, $limit)->get()->pluck('title')->all();
}

test('a lane query with no board order keeps its exact SQL and bindings', function () {
    groupedTask('One', 'todo', '1', '65535.0000000000');

    $plain = laneCellQuery(TestGroupedBoard::class, [], 'priority', 5);
    $withCount = laneCellQuery(TestCastGroupedBoard::class, [], 'priority', 5);

    expect($plain->toSql())->toBe('select * from (select "tasks".*, row_number() over (partition by "status" order by "tasks"."order_position", "tasks"."id") as flowforge_cell_row, count(*) over (partition by "status") as flowforge_cell_total from "tasks" where "priority" = ?) as "tasks" where "flowforge_cell_row" <= ? order by "flowforge_cell_row" asc')
        ->and($plain->getBindings())->toBe(['1', 5])
        ->and($withCount->toSql())->toBe('select * from (select "tasks".*, (select count(*) from "users" inner join "task_user" on "users"."id" = "task_user"."user_id" where "tasks"."id" = "task_user"."task_id") as "assignees_count", row_number() over (partition by "status" order by "tasks"."order_position", "tasks"."id") as flowforge_cell_row, count(*) over (partition by "status") as flowforge_cell_total from "tasks" where "priority" = ?) as "tasks" where "flowforge_cell_row" <= ? order by "flowforge_cell_row" asc')
        ->and($withCount->getBindings())->toBe(['1', 5]);
})->group('pgsql');

test('lane cells follow the order of the board query', function () {
    groupedTask('Alpha', 'todo', 'low', '65535.0000000000');
    groupedTask('Charlie', 'todo', 'low', '131070.0000000000');
    groupedTask('Bravo', 'todo', 'low', '196605.0000000000');

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'title_desc']))->toBe(['Charlie', 'Bravo', 'Alpha']);
})->group('pgsql');

test('lane cells break ties in the board query order by position', function () {
    $later = groupedTask('Same', 'todo', 'low', '131070.0000000000');
    $zed = groupedTask('Zed', 'todo', 'low', '98302.0000000000');
    $earlier = groupedTask('Same', 'todo', 'low', '65535.0000000000');

    $records = laneCellQuery(TestGroupedBoard::class, ['orderShape' => 'title_desc'], 'priority', 10)->get();

    expect($records->pluck('id')->all())->toBe([$zed->id, $earlier->id, $later->id]);
})->group('pgsql');

test('lane cells honour several orders on the board query', function () {
    groupedTask('Low hours b', 'todo', 'low', '65535.0000000000')->update(['estimated_hours' => 1]);
    groupedTask('High hours b', 'todo', 'low', '131070.0000000000')->update(['estimated_hours' => 5]);
    groupedTask('High hours a', 'todo', 'low', '196605.0000000000')->update(['estimated_hours' => 5]);

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'hours_title']))
        ->toBe(['High hours a', 'High hours b', 'Low hours b']);
})->group('pgsql');

test('the cell limit keeps the top cards by the board query order and the total stays full', function () {
    groupedTask('Alpha', 'todo', 'low', '65535.0000000000');
    groupedTask('Bravo', 'todo', 'low', '131070.0000000000');
    groupedTask('Charlie', 'todo', 'low', '196605.0000000000');
    groupedTask('Delta', 'todo', 'low', '262140.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 2, 'orderShape' => 'title_desc'])->instance()->getBoard();
    $cell = $board->getLaneViewData($board->getGrouping('priority'), 10, [])['lanes'][0]['cells']['todo'];

    expect(array_column($cell['items'], 'title'))->toBe(['Delta', 'Charlie'])
        ->and($cell['total'])->toBe(4);
})->group('pgsql');

test('each cell orders its own cards', function () {
    groupedTask('Alpha todo', 'todo', 'low', '65535.0000000000');
    groupedTask('Zulu todo', 'todo', 'low', '131070.0000000000');
    groupedTask('Alpha doing', 'in_progress', 'low', '65535.0000000000');
    groupedTask('Zulu doing', 'in_progress', 'low', '131070.0000000000');

    $board = Livewire::test(TestGroupedBoard::class, ['cardsPerCell' => 1, 'orderShape' => 'title_desc'])->instance()->getBoard();
    $data = $board->getLaneViewData($board->getGrouping('priority'), 10, []);

    expect(cellTitles($data, 'low', 'todo'))->toBe(['Zulu todo'])
        ->and(cellTitles($data, 'low', 'in_progress'))->toBe(['Zulu doing']);
})->group('pgsql');

test('an order carrying a binding lands beside the select and where bindings', function () {
    groupedTask('Alpha', 'todo', 'low', '65535.0000000000');
    groupedTask('Gamma', 'todo', 'low', '131070.0000000000');
    groupedTask('Beta', 'todo', 'low', '196605.0000000000');
    groupedTask('Excluded', 'todo', 'low', '262140.0000000000');

    $records = laneCellQuery(TestGroupedBoard::class, ['orderShape' => 'bound_raw'], 'priority', 10)->get();

    expect($records->pluck('title')->all())->toBe(['Gamma', 'Alpha', 'Beta'])
        ->and($records->pluck('picked')->unique()->all())->toBe(['picked']);
})->group('pgsql');

test('a subquery order keeps its bindings in place', function () {
    $emma = User::factory()->create();
    $marcus = User::factory()->create();
    $third = User::factory()->create();
    groupedTask('One', 'todo', 'low', '65535.0000000000')->assignees()->attach($emma);
    groupedTask('Two', 'todo', 'low', '131070.0000000000')->assignees()->attach($marcus);
    groupedTask('Three', 'todo', 'low', '196605.0000000000')->assignees()->attach([$marcus->id, $third->id]);
    groupedTask('Four', 'todo', 'low', '262140.0000000000');
    groupedTask('Excluded', 'todo', 'low', '327675.0000000000');

    $records = laneCellQuery(TestGroupedBoard::class, ['orderShape' => 'bound_subquery', 'subqueryUserId' => $emma->id], 'priority', 10)->get();

    expect($records->pluck('title')->all())->toBe(['Three', 'Two', 'One', 'Four'])
        ->and($records->pluck('picked')->unique()->all())->toBe(['picked']);
})->group('pgsql');

test('an order on an expression is honoured', function () {
    groupedTask('Ab', 'todo', 'low', '65535.0000000000');
    groupedTask('Abcd', 'todo', 'low', '131070.0000000000');
    groupedTask('Abc', 'todo', 'low', '196605.0000000000');

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'expression']))->toBe(['Abcd', 'Abc', 'Ab']);
})->group('pgsql');

test('an order on a selected alias falls back to position order', function () {
    $emma = User::factory()->create();
    groupedTask('First', 'todo', 'low', '65535.0000000000');
    groupedTask('Second', 'todo', 'low', '131070.0000000000')->assignees()->attach($emma);

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'alias']))->toBe(['First', 'Second']);
})->group('pgsql');

test('a distinct board query honours an order on a selected column of its own table', function () {
    $emma = User::factory()->create();
    groupedTask('Alpha', 'todo', '1', '65535.0000000000')->assignees()->attach($emma);
    groupedTask('Bravo', 'todo', '1', '131070.0000000000')->assignees()->attach($emma);

    expect(laneCellTitles(TestCastGroupedBoard::class, ['distinctJoin' => true, 'orderShape' => 'title_desc']))->toBe(['Bravo', 'Alpha']);
});

test('a distinct board query falls back to position order for an order its wrapper cannot read', function (string $shape) {
    $emma = User::factory()->create();
    $marcus = User::factory()->create();
    groupedTask('Alpha', 'todo', '1', '65535.0000000000')->assignees()->attach($emma);
    groupedTask('Bravo', 'todo', '1', '131070.0000000000')->assignees()->attach($marcus);

    expect(laneCellTitles(TestCastGroupedBoard::class, ['distinctJoin' => true, 'orderShape' => $shape]))->toBe(['Alpha', 'Bravo']);
})->with(['raw', 'unselected']);

test('a joined board query ordered by latest orders lane cells newest first', function () {
    $author = User::factory()->create();
    foreach (['Oldest' => '2024-01-01', 'Newest' => '2024-03-01', 'Middle' => '2024-02-01'] as $title => $day) {
        $task = groupedTask($title, 'todo', 'low', (string) (65535 * (DB::table('tasks')->count() + 1)) . '.0000000000');
        DB::table('tasks')->where('id', $task->id)->update(['created_by' => $author->id, 'created_at' => $day . ' 00:00:00']);
    }

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'joined_latest']))->toBe(['Newest', 'Middle', 'Oldest']);
})->group('pgsql');

test('a bare order column shared with a joined table resolves against the model table', function () {
    $first = groupedTask('First', 'todo', 'low', '65535.0000000000');
    $second = groupedTask('Second', 'todo', 'low', '131070.0000000000');
    DB::table('task_labels')->insert([['task_id' => $first->id, 'label' => 'a'], ['task_id' => $second->id, 'label' => 'b']]);

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'joined_same_name']))->toBe(['Second', 'First']);
})->group('pgsql');

test('an order on one of several selected aliases falls back to position order', function (string $shape) {
    groupedTask('Short', 'todo', 'low', '65535.0000000000');
    groupedTask('A longer title', 'todo', 'low', '131070.0000000000');

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => $shape]))->toBe(['Short', 'A longer title']);
})->with(['multi_alias', 'multi_alias_raw'])->group('pgsql');

test('an order on a quoted alias with a space falls back to position order', function () {
    groupedTask('Short', 'todo', 'low', '65535.0000000000');
    groupedTask('A longer title', 'todo', 'low', '131070.0000000000');

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'quoted_alias']))->toBe(['Short', 'A longer title']);
})->group('pgsql');

test('a raw order calling a function named like a selected alias is honoured', function () {
    $emma = User::factory()->create();
    groupedTask('Unassigned', 'todo', 'low', '65535.0000000000');
    groupedTask('Assigned', 'todo', 'low', '131070.0000000000')->assignees()->attach($emma);

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'function_named_like_alias']))->toBe(['Assigned', 'Unassigned']);
})->group('pgsql');

test('a raw order with a string literal equal to a selected alias is honoured', function () {
    groupedTask('Alpha', 'todo', 'low', '65535.0000000000');
    groupedTask('Zulu', 'todo', 'low', '131070.0000000000');
    groupedTask('picked', 'todo', 'low', '196605.0000000000');

    expect(laneCellTitles(TestGroupedBoard::class, ['orderShape' => 'literal_named_like_alias']))->toBe(['picked', 'Zulu', 'Alpha']);
})->group('pgsql');

test('a from-bucket binding and an order binding stay aligned', function () {
    groupedTask('Alpha', 'todo', 'low', '65535.0000000000');
    groupedTask('Gamma', 'todo', 'low', '131070.0000000000');
    groupedTask('Beta', 'todo', 'low', '196605.0000000000');

    $records = laneCellQuery(TestGroupedBoard::class, ['orderShape' => 'from_sub'], 'priority', 10)->get();

    expect($records->pluck('title')->all())->toBe(['Gamma', 'Alpha', 'Beta'])
        ->and($records->pluck('picked')->unique()->all())->toBe(['picked']);
})->group('pgsql');
