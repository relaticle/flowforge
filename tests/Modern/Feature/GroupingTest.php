<?php

declare(strict_types=1);

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
