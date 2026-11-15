<?php

declare(strict_types=1);

use Livewire\Livewire;
use Relaticle\Flowforge\Components\CardAvatar;
use Relaticle\Flowforge\Components\CardMeta;
use Relaticle\Flowforge\Components\CardTag;
use Relaticle\Flowforge\Tests\Fixtures\Modern\Task;
use Relaticle\Flowforge\Tests\Fixtures\Modern\TestContractBoard;
use Relaticle\Flowforge\Tests\Fixtures\Modern\User;

function contractSeed(): void
{
    $assignee = User::factory()->create(['name' => 'Emma Collins']);

    foreach ([['Alpha', 'todo', 'high', 65535], ['Beta', 'todo', 'low', 131070], ['Gamma', 'in_progress', 'medium', 65535]] as [$title, $status, $priority, $position]) {
        Task::create([
            'title' => $title,
            'status' => $status,
            'priority' => $priority,
            'description' => 'Needs a spec',
            'order_position' => "{$position}.0000000000",
        ])->assignees()->attach($assignee);
    }
}

function contractRender(array $properties = [], ?string $groupBy = null): string
{
    $component = Livewire::test(TestContractBoard::class, $properties);

    return ($groupBy === null ? $component : $component->set('groupBy', $groupBy))->html();
}

function contractModes(): array
{
    return [
        'flat' => [[]],
        'toolbar in the header' => [['withHeaderToolbar' => true]],
        'filters above' => [['filtersLayout' => 'AboveContent']],
        'filters above, collapsible' => [['filtersLayout' => 'AboveContentCollapsible']],
        'filters below' => [['filtersLayout' => 'BelowContent']],
        'filters before' => [['filtersLayout' => 'BeforeContent']],
        'filters after' => [['filtersLayout' => 'AfterContent']],
        'filters in a modal' => [['filtersLayout' => 'Modal']],
        'filters in a dropdown' => [['filtersLayout' => 'Dropdown']],
        'cards that cannot be dragged' => [['sortable' => false, 'cardActionName' => 'view']],
        'cards that mount an action' => [['cardActionName' => 'editTask']],
        'cards that link' => [['cardActionName' => 'view']],
        'lanes by priority' => [[], 'priority'],
        'lanes with an avatar, an icon and a colour' => [[], 'owner'],
    ];
}

function contractCss(): string
{
    return file_get_contents(__DIR__ . '/../../../resources/css/flowforge-modern.css');
}

function contractCssWithoutComments(): string
{
    return preg_replace('#/\*.*?\*/#s', '', contractCss());
}

function contractTokens(): array
{
    return require __DIR__ . '/../../../resources/design-tokens.php';
}

function contractRenderedClasses(string $html): array
{
    preg_match_all('/(?<![\w:-])class="([^"]*)"/', $html, $matches);

    return array_values(array_unique(array_merge(...array_map(
        fn (string $classes): array => preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY),
        $matches[1],
    ))));
}

const CONTRACT_FLAT_HOOKS = [
    'ff-board', 'ff-columns', 'ff-column', 'ff-column-header', 'ff-count', 'ff-summary', 'ff-tray', 'ff-card', 'ff-card-actions',
    'ff-add-row', 'ff-limit-hint', 'ff-card-tag', 'ff-card-meta', 'ff-card-avatar',
];

const CONTRACT_LANE_HOOKS = [
    'ff-board', 'ff-column', 'ff-column-header', 'ff-count', 'ff-summary', 'ff-lanes', 'ff-lane-header', 'ff-cell',
    'ff-card', 'ff-card-actions', 'ff-card-tag', 'ff-card-meta', 'ff-card-avatar',
];

function contractHasHook(string $html, string $hook): bool
{
    return preg_match('/(?<![\w:-])class="[^"]*(?<![\w-])' . $hook . '(?![\w-])/', $html) === 1;
}

test('every documented hook renders in the flat board', function (string $hook) {
    contractSeed();

    expect(contractHasHook(contractRender(), $hook))->toBeTrue();
})->with(CONTRACT_FLAT_HOOKS);

test('every documented hook renders in the lane board', function (string $hook) {
    contractSeed();

    expect(contractHasHook(contractRender(groupBy: 'priority'), $hook))->toBeTrue();
})->with(CONTRACT_LANE_HOOKS);

test('the flat and lane hooks together are the seventeen documented hooks', function () {
    expect(array_unique([...CONTRACT_FLAT_HOOKS, ...CONTRACT_LANE_HOOKS]))->toHaveCount(17);
});

test('a flat board renders the server-side states', function () {
    contractSeed();
    $html = contractRender();

    expect($html)
        ->toMatch('/<div\s[^>]*class="ff-column"[^>]*\sdata-full[\s>]/')
        ->toMatch('/<div\s[^>]*class="ff-column"[^>]*\sdata-collapsed[\s>]/');
});

test('each board mode binds the drop state', function () {
    contractSeed();

    expect(contractRender())->toContain('x-bind:data-drop="columnDrop(')
        ->and(contractRender(groupBy: 'priority'))->toContain('x-bind:data-drop="columnDrop(')->toContain('x-bind:data-drop="cellDrop(');
});

test('modern markup carries only ff and filament classes', function (array $properties, ?string $groupBy = null) {
    contractSeed();

    $foreign = array_values(array_filter(
        contractRenderedClasses(contractRender($properties, $groupBy)),
        fn (string $token): bool => preg_match('/^(?:[a-z0-9-]+:)*(?:ff|fi)-/', $token) !== 1,
    ));

    expect($foreign)->toBe([]);
})->with(contractModes());

test('lane and column icons render', function () {
    contractSeed();

    expect(contractRender())->toContain('ff-column-icon')
        ->and(contractRender(groupBy: 'owner'))->toContain('ff-lane-icon')->toContain('ff-lane-avatar')->toContain('ff-lane-dot');
});

function contractViewClassTokens(string $source): array
{
    $tokens = [];
    $add = function (string $classes) use (&$tokens): void {
        $classes = preg_replace('/\{\{.*?\}\}|\{!!.*?!!\}/s', '', $classes);

        foreach (preg_split('/\s+/', trim($classes), -1, PREG_SPLIT_NO_EMPTY) as $token) {
            $tokens[] = $token;
        }
    };

    preg_match_all('/(?<![\w:-])class="([^"]*)"/', $source, $literals);
    array_map($add, $literals[1]);

    preg_match_all('/(?:@class\(|->class\()/', $source, $calls, PREG_OFFSET_CAPTURE);

    foreach ($calls[0] as [$call, $offset]) {
        $start = $offset + strlen($call);
        $depth = 1;

        for ($end = $start; $end < strlen($source) && $depth > 0; $end++) {
            $depth += match ($source[$end]) {
                '(' => 1,
                ')' => -1,
                default => 0,
            };
        }

        preg_match_all('/\'([^\'\\\\]*)\'|"([^"\\\\]*)"/', substr($source, $start, $end - $start), $strings);
        array_map($add, array_filter(array_merge($strings[1], $strings[2]), 'strlen'));
    }

    preg_match_all('/x-bind:class="\{(.*?)\}"/s', $source, $objects);

    foreach ($objects[1] as $object) {
        preg_match_all("/'([^']+)'\\s*:/", $object, $keys);
        array_map($add, $keys[1]);
    }

    return $tokens;
}

function contractViewFiles(): array
{
    $root = __DIR__ . '/../../../resources/views';
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator("{$root}/modern")) as $file) {
        if ($file->isFile()) {
            $files[] = $file->getPathname();
        }
    }

    return [...$files, "{$root}/components/card-avatar.blade.php", "{$root}/components/card-tag.blade.php", "{$root}/components/card-title.blade.php"];
}

test('every class token in the modern view sources starts with ff or fi', function () {
    $offenders = [];

    foreach (contractViewFiles() as $path) {
        foreach (contractViewClassTokens(file_get_contents($path)) as $token) {
            if (preg_match('/^(?:[a-z0-9-]+:)*(?:ff|fi)-/', $token) !== 1) {
                $offenders[] = basename($path) . ': ' . $token;
            }
        }
    }

    expect($offenders)->toBe([]);
});

test('the view scan reads class arrays and bind objects', function () {
    $tokens = contractViewClassTokens(<<<'BLADE'
        <div class="ff-a" @class(['ff-b', 'p-4' => $x]) x-bind:class="{ 'flex': open }" {{ $attributes->class(['mt-2']) }}>
        BLADE);

    expect($tokens)->toBe(['ff-a', 'ff-b', 'p-4', 'mt-2', 'flex']);
});

test('the stylesheet reads exactly the documented variables', function () {
    preg_match_all('/var\((--ff-[a-z-]+)/', contractCss(), $matches);

    expect(array_values(array_unique($matches[1])))->toEqualCanonicalizing(array_keys(contractTokens()));
});

test('every documented variable names a tier and a version', function () {
    foreach (contractTokens() as $name => $token) {
        expect($name)->toStartWith('--ff-')
            ->and($token['tier'])->toBeIn(['semantic', 'component'])
            ->and($token['since'])->toBeIn(['4.2.0', '4.4.0']);
    }
});

test('the stylesheet never sets a public variable', function () {
    expect(contractCssWithoutComments())->not->toMatch('/--ff-[a-z-]+\s*:/');
});

test('the stylesheet avoids apply and light-dark', function () {
    expect(contractCssWithoutComments())->not->toContain('@apply')->not->toContain('light-dark(');
});

test('every rule sits in the flowforge layer', function () {
    $css = contractCssWithoutComments();
    $statement = '@layer properties, theme, base, components, utilities;';

    expect(trim($css))->toStartWith($statement);

    $rest = trim(substr(trim($css), strlen($statement)));

    expect($rest)->toStartWith('@layer components.flowforge {')->and($rest)->toEndWith('}');

    $depth = 0;
    $closedEarly = false;

    foreach (str_split($rest) as $index => $character) {
        $depth += $character === '{' ? 1 : ($character === '}' ? -1 : 0);
        $closedEarly = $closedEarly || ($depth === 0 && $index > 0 && $index < strlen($rest) - 1 && $character === '}');
    }

    expect($closedEarly)->toBeFalse();
});

test('the ghost mirrors exactly the documented variables', function () {
    preg_match_all("/'(--ff-[a-z-]+)'/", file_get_contents(__DIR__ . '/../../../resources/js/flowforge-modern.js'), $matches);

    expect($matches[1])->toEqualCanonicalizing(array_keys(contractTokens()));
});

test('card tags and avatars do not grow inside a card flex', function () {
    Task::create(['title' => 'Alpha', 'status' => 'todo', 'priority' => 'high', 'description' => 'Needs a spec', 'order_position' => '65535.0000000000']);

    $html = Livewire::test(TestContractBoard::class)->html();

    expect(CardTag::make('priority')->canGrow())->toBeFalse()
        ->and(CardAvatar::make('assignees')->canGrow())->toBeFalse()
        ->and(CardMeta::make([])->canGrow())->toBeFalse()
        ->and(substr_count($html, 'ff-card-flex-item--grow'))->toBe(1)
        ->and(substr_count($html, 'ff-card-flex-item--fixed'))->toBeGreaterThanOrEqual(2);
});

test('card meta hides its entries labels', function () {
    Task::create(['title' => 'Alpha', 'status' => 'todo', 'description' => 'Needs a spec', 'order_position' => '65535.0000000000']);

    $html = Livewire::test(TestContractBoard::class)->html();

    expect($html)->toContain('Needs a spec')
        ->toMatch('/class="fi-in-entry-label fi-sr-only"[^>]*>\s*Description/')
        ->not->toMatch('/class="fi-in-entry-label"/');
});
