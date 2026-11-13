<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Browser\Support;

use Closure;
use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Playwright\Client;
use Pest\Browser\Playwright\Page;
use ReflectionException;
use ReflectionProperty;
use RuntimeException;

final class Pointer
{
    private const STEPS = 25;

    private const STEP_DELAY_MS = 16;

    private const TIMEOUT_MS = 10000;

    private const POLL_MS = 100;

    /** @var array{x: float, y: float}|null */
    private ?array $position = null;

    private function __construct(
        private readonly AwaitableWebpage $webpage,
        private readonly Page $page,
    ) {}

    public static function on(AwaitableWebpage $webpage): self
    {
        return new self($webpage, PageHandle::of($webpage));
    }

    /** @return array{x: float, y: float} */
    public function pointIn(string $selector, float $xFraction = 0.5, float $yFraction = 0.5): array
    {
        $box = $this->measure($selector, $xFraction, $yFraction);

        if (! $box['found']) {
            throw new RuntimeException("No element matches {$selector}.");
        }

        return ['x' => $box['x'], 'y' => $box['y']];
    }

    public function press(string $selector): self
    {
        $box = null;

        // A menu can still be opening or moving into place, so press only a still point that lands on the target.
        $this->until(function () use ($selector, &$box): bool {
            $box = $this->measure($selector);

            return $box['hits'];
        }, "{$selector} to take a press at its centre");

        $this->send('mouseMove', ['x' => $box['x'], 'y' => $box['y']]);
        $this->send('mouseDown', ['button' => 'left', 'clickCount' => 1]);

        return $this;
    }

    public function glideTo(string $selector, float $xFraction = 0.5, float $yFraction = 0.5): self
    {
        $target = $this->pointIn($selector, $xFraction, $yFraction);
        $from = $this->position ?? $target;

        for ($step = 1; $step <= self::STEPS; $step++) {
            $this->send('mouseMove', [
                'x' => $from['x'] + ($target['x'] - $from['x']) * $step / self::STEPS,
                'y' => $from['y'] + ($target['y'] - $from['y']) * $step / self::STEPS,
            ]);
            $this->wait(self::STEP_DELAY_MS);
        }

        $this->untilScript('Boolean(window.Sortable && window.Sortable.active && window.Sortable.ghost)');

        return $this->settle();
    }

    public function nudge(float $dx, float $dy): self
    {
        $from = $this->position ?? throw new RuntimeException('Press before nudging.');

        $this->send('mouseMove', ['x' => $from['x'] + $dx, 'y' => $from['y'] + $dy]);

        return $this;
    }

    public function release(): self
    {
        $this->send('mouseUp', ['button' => 'left', 'clickCount' => 1]);

        return $this;
    }

    public function click(string $selector): self
    {
        return $this->press($selector)->release();
    }

    public function doubleClick(string $selector): self
    {
        $this->click($selector);
        $this->send('mouseDown', ['button' => 'left', 'clickCount' => 2]);
        $this->send('mouseUp', ['button' => 'left', 'clickCount' => 2]);

        return $this;
    }

    public function drag(string $from, string $to, float $yFraction = 0.5): self
    {
        return $this->press($from)->glideTo($to, yFraction: $yFraction)->release();
    }

    public function settle(): self
    {
        $snapshot = <<<'JS'
            JSON.stringify([
                [...document.querySelectorAll('[data-drop]')].map((element) => (element.dataset.columnRoot ?? element.className) + ':' + element.dataset.drop),
                [...document.querySelectorAll('[data-placeholder]')].map((element) => [element.parentElement?.dataset.columnId, element.parentElement?.dataset.laneId, [...element.parentElement.children].indexOf(element)]),
            ])
        JS;

        $previous = null;

        $this->until(function () use ($snapshot, &$previous): bool {
            $current = $this->webpage->script($snapshot);
            $settled = $current === $previous;
            $previous = $current;

            return $settled;
        }, 'the hover state to stop changing');

        return $this;
    }

    public function wait(int $milliseconds): self
    {
        $this->webpage->script("new Promise((resolve) => setTimeout(resolve, {$milliseconds}))");

        return $this;
    }

    public function untilScript(string $expression, mixed $expected = true): self
    {
        $last = null;

        $this->until(function () use ($expression, $expected, &$last): bool {
            $last = $this->webpage->script($expression);

            return $last === $expected;
        }, "the script [{$expression}] to return " . json_encode($expected), function () use (&$last): mixed {
            return $last;
        });

        return $this;
    }

    public function untilEquals(Closure $observe, mixed $expected, string $description): self
    {
        $last = null;

        return $this->until(function () use ($observe, $expected, &$last): bool {
            $last = $observe();

            return $last === $expected;
        }, $description . ' to equal ' . json_encode($expected), function () use (&$last): mixed {
            return $last;
        });
    }

    public function until(Closure $condition, string $description, ?Closure $observed = null): self
    {
        for ($waited = 0; $waited < self::TIMEOUT_MS; $waited += self::POLL_MS) {
            if ($condition()) {
                return $this;
            }

            $this->wait(self::POLL_MS);
        }

        throw new RuntimeException(sprintf(
            'Timed out after %dms waiting for %s. Last observed: %s. Page state: %s',
            self::TIMEOUT_MS,
            $description,
            json_encode($observed?->call($this)),
            json_encode($this->state()),
        ));
    }

    /** @return array{x: float, y: float, found: bool, hits: bool} */
    private function measure(string $selector, float $xFraction = 0.5, float $yFraction = 0.5): array
    {
        $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR);

        // Alpine applies x-show on the next animation frame, so a box measured before it can move before the press lands.
        $box = $this->webpage->script(<<<JS
            (async () => {
                await new Promise((resolve) => requestAnimationFrame(resolve));

                const element = document.querySelector({$selectorJson});

                if (! element) {
                    return { x: 0, y: 0, found: false, hits: false };
                }

                const before = element.getBoundingClientRect();

                await new Promise((resolve) => requestAnimationFrame(resolve));

                const box = element.getBoundingClientRect();
                const stable = ['left', 'top', 'width', 'height'].every((side) => box[side] === before[side]);
                const x = box.left + box.width * {$xFraction};
                const y = box.top + box.height * {$yFraction};
                const hit = document.elementFromPoint(x, y);

                return { x, y, found: true, hits: stable && box.width > 0 && box.height > 0 && hit !== null && element.contains(hit) };
            })()
        JS);

        return ['x' => (float) $box['x'], 'y' => (float) $box['y'], 'found' => (bool) $box['found'], 'hits' => (bool) $box['hits']];
    }

    private function state(): mixed
    {
        return $this->webpage->script(<<<'JS'
            ({
                url: location.href,
                dragging: document.documentElement.classList.contains('ff-dragging'),
                sortableActive: Boolean(window.Sortable?.active),
                sortableDragged: Boolean(window.Sortable?.dragged),
                ghost: Boolean(window.Sortable?.ghost),
                drops: [...document.querySelectorAll('[data-drop]')].map((element) => (element.dataset.columnRoot ?? element.className) + ':' + element.dataset.drop),
                pending: document.querySelectorAll('[data-pending]').length,
                columns: Object.fromEntries([...document.querySelectorAll('[data-column-id]')].map((element) => [element.dataset.columnId, [...element.querySelectorAll(':scope > [data-card-id]')].map((card) => card.dataset.cardId)])),
            })
        JS);
    }

    /** @param  array<string, mixed>  $params */
    private function send(string $method, array $params): void
    {
        if (isset($params['x'], $params['y'])) {
            $this->position = ['x' => $params['x'], 'y' => $params['y']];
        }

        iterator_to_array(Client::instance()->execute($this->guid(), $method, $params));
    }

    private function guid(): string
    {
        try {
            return (new ReflectionProperty(Page::class, 'guid'))->getValue($this->page);
        } catch (ReflectionException $exception) {
            throw new RuntimeException('The browser plugin no longer keeps the page channel in Page::$guid, so Pointer cannot send mouse input. Update Pointer for the installed pest-plugin-browser.', previous: $exception);
        }
    }
}
