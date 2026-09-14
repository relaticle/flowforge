<?php

declare(strict_types=1);

namespace Relaticle\Flowforge;

use Closure;
use Exception;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasColor;
use Filament\Support\Concerns\HasIcon;
use Illuminate\Contracts\Support\Htmlable;
use Relaticle\Flowforge\Concerns\BelongsToBoard;

class Swimlane extends ViewComponent
{
    use BelongsToBoard;
    use HasColor;
    use HasIcon;

    protected string $view = 'flowforge::swimlane';

    protected string $viewIdentifier = 'swimlane';

    protected string $evaluationIdentifier = 'swimlane';

    protected string | Htmlable | Closure | null $label = null;

    protected string $name;

    final public function __construct(string $name)
    {
        $this->name = $name;
    }

    public static function make(?string $name = null): static
    {
        $swimlaneClass = static::class;

        $name ??= static::getDefaultName();

        if (blank($name)) {
            throw new Exception("Swimlane of class [$swimlaneClass] must have a unique name, passed to the [make()] method.");
        }

        $static = app($swimlaneClass, ['name' => $name]);
        $static->configure();

        return $static;
    }

    public static function getDefaultName(): ?string
    {
        return null;
    }

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function label(string | Htmlable | Closure | null $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getLabel(): string | Htmlable | null
    {
        return $this->evaluate($this->label) ?? $this->generateDefaultLabel();
    }

    protected function generateDefaultLabel(): string
    {
        return str($this->getName())
            ->kebab()
            ->replace(['-', '_'], ' ')
            ->title()
            ->toString();
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array<mixed>
     */
    protected function resolveDefaultClosureDependencyForEvaluationByName(string $parameterName): array
    {
        return match ($parameterName) {
            'swimlane' => [$this],
            'name' => [$this->getName()],
            'label' => [$this->getLabel()],
            default => parent::resolveDefaultClosureDependencyForEvaluationByName($parameterName),
        };
    }
}
