<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Closure;
use Relaticle\Flowforge\Swimlane;

/**
 * Swimlane management for Board (mirrors HasBoardColumns).
 *
 * When configured, the board renders as a 2D grid: columns × swimlanes.
 * When not configured, the board renders as a flat column layout (unchanged).
 */
trait HasBoardSwimlanes
{
    /**
     * @var array<Swimlane>
     */
    protected array $swimlanes = [];

    protected string | Closure | null $swimlaneIdentifierAttribute = null;

    /**
     * Configure board swimlanes.
     */
    public function swimlanes(array | Closure $swimlanes): static
    {
        $this->swimlanes = $this->evaluate($swimlanes);

        foreach ($this->swimlanes as $swimlane) {
            if ($swimlane instanceof Swimlane) {
                $swimlane->board($this);
            }
        }

        return $this;
    }

    /**
     * Set the swimlane identifier attribute (the model field used for grouping).
     */
    public function swimlaneIdentifier(string | Closure $attribute): static
    {
        $this->swimlaneIdentifierAttribute = $attribute;

        return $this;
    }

    /**
     * Get configured swimlanes.
     *
     * @return array<Swimlane>
     */
    public function getSwimlanes(): array
    {
        return $this->swimlanes;
    }

    /**
     * Get the swimlane identifier attribute.
     */
    public function getSwimlaneIdentifierAttribute(): ?string
    {
        return $this->evaluate($this->swimlaneIdentifierAttribute);
    }

    /**
     * Check if swimlanes are configured.
     */
    public function hasSwimlanes(): bool
    {
        return ! empty($this->swimlanes) && $this->getSwimlaneIdentifierAttribute() !== null;
    }

    /**
     * Get swimlane identifiers.
     *
     * @return array<string>
     */
    public function getSwimlaneIdentifiers(): array
    {
        return array_map(fn (Swimlane $swimlane) => $swimlane->getName(), $this->swimlanes);
    }

    /**
     * Get a specific swimlane by identifier.
     */
    public function getSwimlane(string $identifier): ?Swimlane
    {
        foreach ($this->swimlanes as $swimlane) {
            if ($swimlane->getName() === $identifier) {
                return $swimlane;
            }
        }

        return null;
    }
}
