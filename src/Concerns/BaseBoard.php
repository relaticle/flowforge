<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Concerns;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Contracts\View\View;
use Relaticle\Flowforge\Board;

/**
 * Base functionality for all board pages.
 * Provides common setup for both regular pages and resource pages.
 */
trait BaseBoard
{
    use InteractsWithActions;
    use InteractsWithBoard {
        InteractsWithBoard::getDefaultActionRecord insteadof InteractsWithActions;
    }
    use InteractsWithForms;

    /**
     * Configure the board - implement in subclasses.
     */
    abstract public function board(Board $board): Board;

    /**
     * Get the board view for rendering.
     */
    protected function getBoardView(): string
    {
        return 'flowforge::filament.pages.board-page';
    }

    /**
     * Override Filament's page header when headerToolbar is enabled.
     *
     * Renders the page title with the filter/search toolbar and the
     * page header actions inline, replacing the default stacked layout.
     */
    public function getHeader(): ?View
    {
        if (! $this->getBoard()->hasHeaderToolbar()) {
            return null;
        }

        /** @var view-string $viewName */
        $viewName = 'flowforge::filament.pages.board-header';

        return view($viewName, [
            'heading' => $this->getHeading(),
            'subheading' => $this->getSubheading(),
            'breadcrumbs' => filament()->hasBreadcrumbs() ? $this->getBreadcrumbs() : [],
            'actions' => $this->getCachedHeaderActions(),
            'actionsAlignment' => $this->getHeaderActionsAlignment(),
        ]);
    }
}
