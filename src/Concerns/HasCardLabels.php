<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Closure;
use Filament\Facades\Filament;
use Filament\Resources\Pages\Page as ResourcePage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasCardLabels
{
    protected string | Closure | null $cardLabel = null;

    protected string | Closure | null $pluralCardLabel = null;

    /**
     * Name a single card, e.g. "Task". Defaults to the model label.
     */
    public function cardLabel(string | Closure | null $label): static
    {
        $this->cardLabel = $label;

        return $this;
    }

    /**
     * Name a set of cards, e.g. "Tasks". Defaults to the plural model label.
     */
    public function pluralCardLabel(string | Closure | null $label): static
    {
        $this->pluralCardLabel = $label;

        return $this;
    }

    public function getCardLabel(): string
    {
        $label = $this->evaluate($this->cardLabel);

        if (filled($label)) {
            return $label;
        }

        $resource = $this->getCardResource();

        if ($resource !== null) {
            return $resource::getModelLabel();
        }

        $model = $this->getCardModel();

        if ($model !== null) {
            return Str::title(Str::snake(class_basename($model), ' '));
        }

        return __('flowforge::flowforge.card_label');
    }

    public function getPluralCardLabel(): string
    {
        $label = $this->evaluate($this->pluralCardLabel);

        if (filled($label)) {
            return $label;
        }

        $resource = $this->getCardResource();

        if ($resource !== null) {
            return $resource::getPluralModelLabel();
        }

        if ($this->getCardModel() !== null) {
            return Str::plural($this->getCardLabel());
        }

        return __('flowforge::flowforge.plural_card_label');
    }

    protected function getCardModel(): ?Model
    {
        return $this->getQuery()?->getModel();
    }

    /**
     * The Filament resource describing the cards, either the one the board page
     * belongs to or the one registered for the model in the current panel.
     *
     * @return class-string|null
     */
    protected function getCardResource(): ?string
    {
        $livewire = $this->getLivewire();

        if ($livewire instanceof ResourcePage) {
            return $livewire::getResource();
        }

        $model = $this->getCardModel();

        if ($model === null || ! class_exists(Filament::class)) {
            return null;
        }

        if (Filament::getCurrentPanel() === null) {
            return null;
        }

        return Filament::getModelResource($model);
    }
}
