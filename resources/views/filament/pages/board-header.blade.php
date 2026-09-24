@php
    use Filament\Support\Enums\Width;
    use Filament\Support\Facades\FilamentView;
    use Filament\Tables\Enums\FiltersLayout;
    use Filament\Tables\Filters\Indicator;
    use Filament\Tables\View\TablesRenderHook;
    use Filament\View\PanelsRenderHook;

    $table = $this->getTable();
    $isFilterable = $table->isFilterable();
    $isSearchable = $table->isSearchable();
    $filterIndicators = $table->getFilterIndicators();

    $filtersLayout = $table->getFiltersLayout();
    $filtersTriggerAction = $table->getFiltersTriggerAction();
    $filtersApplyAction = $table->getFiltersApplyAction();
    $filtersForm = $this->getTableFiltersForm();
    $filtersFormWidth = $table->getFiltersFormWidth();
    $filtersFormMaxHeight = $table->getFiltersFormMaxHeight();
    $filtersResetActionPosition = $table->getFiltersResetActionPosition();
    $activeFiltersCount = $table->getActiveFiltersCount();

    if (is_string($filtersFormWidth)) {
        $filtersFormWidth = Width::tryFrom($filtersFormWidth) ?? $filtersFormWidth;
    }

    $hasFiltersDialog = $isFilterable && in_array($filtersLayout, [FiltersLayout::Dropdown, FiltersLayout::Modal]);
    $isModalLayout = ($filtersLayout === FiltersLayout::Modal) || ($hasFiltersDialog && $filtersTriggerAction->isModalSlideOver());
@endphp

<div class="flex flex-col gap-y-1">
    {{-- Breadcrumbs --}}
    @if (filled($breadcrumbs))
        <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" class="mb-2" />
    @endif

    {{-- Title row: heading + filter + search + actions --}}
    <div class="flex flex-wrap items-center gap-4">
        <div class="flex-1 basis-auto min-w-0">
            {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_BEFORE, scopes: $this->getRenderHookScopes()) }}

            <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                {{ $heading }}
            </h1>

            {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_HEADING_AFTER, scopes: $this->getRenderHookScopes()) }}

            @if (filled($subheading))
                <p class="fi-header-subheading mt-1 max-w-2xl text-sm text-gray-600 dark:text-gray-400">
                    {{ $subheading }}
                </p>
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-x-6 gap-y-3">
            @if ($isFilterable && $hasFiltersDialog)
                @if ($isModalLayout)
                    @php
                        $filtersTriggerActionModalAlignment = $filtersTriggerAction->getModalAlignment();
                        $filtersTriggerActionIsModalAutofocused = $filtersTriggerAction->isModalAutofocused();
                        $filtersTriggerActionHasModalCloseButton = $filtersTriggerAction->hasModalCloseButton();
                        $filtersTriggerActionIsModalClosedByClickingAway = $filtersTriggerAction->isModalClosedByClickingAway();
                        $filtersTriggerActionIsModalClosedByEscaping = $filtersTriggerAction->isModalClosedByEscaping();
                        $filtersTriggerActionModalDescription = $filtersTriggerAction->getModalDescription();
                        $filtersTriggerActionVisibleModalFooterActions = $filtersTriggerAction->getVisibleModalFooterActions();
                        $filtersTriggerActionModalFooterActionsAlignment = $filtersTriggerAction->getModalFooterActionsAlignment();
                        $filtersTriggerActionModalHeading = $filtersTriggerAction->getCustomModalHeading() ?? __('filament-tables::table.filters.heading');
                        $filtersTriggerActionModalIcon = $filtersTriggerAction->getModalIcon();
                        $filtersTriggerActionModalIconColor = $filtersTriggerAction->getModalIconColor();
                        $filtersTriggerActionIsModalSlideOver = $filtersTriggerAction->isModalSlideOver();
                        $filtersTriggerActionIsModalFooterSticky = $filtersTriggerAction->isModalFooterSticky();
                        $filtersTriggerActionIsModalHeaderSticky = $filtersTriggerAction->isModalHeaderSticky();
                    @endphp

                    <x-filament::modal
                        :alignment="$filtersTriggerActionModalAlignment"
                        :autofocus="$filtersTriggerActionIsModalAutofocused"
                        :close-button="$filtersTriggerActionHasModalCloseButton"
                        :close-by-clicking-away="$filtersTriggerActionIsModalClosedByClickingAway"
                        :close-by-escaping="$filtersTriggerActionIsModalClosedByEscaping"
                        :description="$filtersTriggerActionModalDescription"
                        :footer-actions="$filtersTriggerActionVisibleModalFooterActions"
                        :footer-actions-alignment="$filtersTriggerActionModalFooterActionsAlignment"
                        :heading="$filtersTriggerActionModalHeading"
                        :icon="$filtersTriggerActionModalIcon"
                        :icon-color="$filtersTriggerActionModalIconColor"
                        :slide-over="$filtersTriggerActionIsModalSlideOver"
                        :sticky-footer="$filtersTriggerActionIsModalFooterSticky"
                        :sticky-header="$filtersTriggerActionIsModalHeaderSticky"
                        :width="$filtersFormWidth"
                        :wire:key="$this->getId() . '.board.filters'"
                        class="fi-ta-filters-modal"
                    >
                        <x-slot name="trigger">
                            {{ $filtersTriggerAction->badge($activeFiltersCount) }}
                        </x-slot>

                        {{ $filtersTriggerAction->getModalContent() }}

                        {{ $filtersForm }}

                        {{ $filtersTriggerAction->getModalContentFooter() }}
                    </x-filament::modal>
                @else
                    {{-- Wrap in fi-ta-ctn context so Filament's scoped table CSS applies to filters --}}
                    <div class="fi-ta-ctn fi-ta-ctn-with-header" style="display: contents;">
                        <div class="fi-ta-header-toolbar" style="display: contents;">
                            <x-filament::dropdown
                                :max-height="$filtersFormMaxHeight"
                                placement="bottom-end"
                                shift
                                :flip="false"
                                :width="$filtersFormWidth ?? Width::ExtraSmall"
                                :wire:key="$this->getId() . '.board.filters'"
                                class="fi-ta-filters-dropdown"
                            >
                                <x-slot name="trigger">
                                    {{ $filtersTriggerAction->badge($activeFiltersCount) }}
                                </x-slot>

                                <x-filament-tables::filters
                                    :apply-action="$filtersApplyAction"
                                    :form="$filtersForm"
                                    :reset-action-position="$filtersResetActionPosition"
                                />
                            </x-filament::dropdown>
                        </div>
                    </div>
                @endif
            @endif

            @if ($isSearchable)
                <x-filament-tables::search-field
                    :debounce="$table->getSearchDebounce()"
                    :on-blur="$table->isSearchOnBlur()"
                    :placeholder="$table->getSearchPlaceholder()"
                />
            @endif

            {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_BEFORE, scopes: $this->getRenderHookScopes()) }}

            @if ($actions)
                <x-filament::actions
                    :actions="$actions"
                    :alignment="$actionsAlignment"
                />
            @endif

            {{ FilamentView::renderHook(PanelsRenderHook::PAGE_HEADER_ACTIONS_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </div>

    {{-- Active filter indicators --}}
    @if ($filterIndicators)
        @if (filled($filterIndicatorsView = FilamentView::renderHook(TablesRenderHook::FILTER_INDICATORS, scopes: static::class, data: ['filterIndicators' => $filterIndicators])))
            {{ $filterIndicatorsView }}
        @else
            <div class="fi-ta-filter-indicators flex flex-wrap items-center gap-1 mt-1">
                @foreach ($filterIndicators as $indicator)
                    <x-filament::badge :color="$indicator->getColor()">
                        {{ $indicator->getLabel() }}

                        @if ($indicator->isRemovable())
                            <x-slot
                                name="deleteButton"
                                :label="__('filament-tables::table.filters.actions.remove.label')"
                                :wire:click="$indicator->getRemoveLivewireClickHandler()"
                                wire:loading.attr="disabled"
                                wire:target="removeTableFilter"
                            ></x-slot>
                        @endif
                    </x-filament::badge>
                @endforeach
            </div>
        @endif
    @endif
</div>
