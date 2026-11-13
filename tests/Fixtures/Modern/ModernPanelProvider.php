<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Tests\Fixtures\Modern;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class ModernPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('modern')
            ->path('modern')
            ->login()
            ->spa()
            ->pages([
                TestBoard::class,
                TestBrowserBoard::class,
                TestClassicBoard::class,
                TestCollapsibleBoard::class,
                TestContractBoard::class,
                TestGroupedBoard::class,
                TestCardActionBoard::class,
                TestCardActionConfirmBoard::class,
                TestCardActionDefaultUrlBoard::class,
                TestCardActionModalHeadingBoard::class,
                TestCardActionModalBoard::class,
                TestCardActionNewTabBoard::class,
                TestDraggableCardActionBoard::class,
            ])
            ->resources([
                TestResource::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
