@php
    use Filament\Support\Enums\IconSize;
    use Filament\Support\Enums\Width;
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\Icons\Heroicon;
    use Filament\Tables\Enums\FiltersLayout;
    use Filament\Tables\Filters\Indicator;
    use Filament\Tables\View\TablesIconAlias;
    use Filament\Tables\View\TablesRenderHook;

    use function Filament\Support\generate_icon_html;

    $table = $this->getTable();
    $isFilterable = $table->isFilterable();
    $isSearchable = $table->isSearchable();
    $filterIndicators = $table->getFilterIndicators();

    // Filter layout configuration (matches Filament v4 exactly)
    $filtersLayout = $table->getFiltersLayout();
    $filtersTriggerAction = $table->getFiltersTriggerAction();
    $filtersApplyAction = $table->getFiltersApplyAction();
    $filtersForm = $this->getTableFiltersForm();
    $filtersFormWidth = $table->getFiltersFormWidth();
    $filtersFormMaxHeight = $table->getFiltersFormMaxHeight();
    $filtersResetAction = $table->getFiltersResetAction();
    $filtersResetActionPosition = $table->getFiltersResetActionPosition();
    $activeFiltersCount = $table->getActiveFiltersCount();

    // Convert string width to Width enum if needed
    if (is_string($filtersFormWidth)) {
        $filtersFormWidth = Width::tryFrom($filtersFormWidth) ?? $filtersFormWidth;
    }

    // Boolean flags based on layout (matches Filament v4 exactly)
    $hasFiltersDialog = $isFilterable && in_array($filtersLayout, [FiltersLayout::Dropdown, FiltersLayout::Modal]);
    $hasFiltersAboveContent = $isFilterable && in_array($filtersLayout, [FiltersLayout::AboveContent, FiltersLayout::AboveContentCollapsible]);
    $hasFiltersBelowContent = $isFilterable && ($filtersLayout === FiltersLayout::BelowContent);
    $hasFiltersBeforeContent = $isFilterable && in_array($filtersLayout, [FiltersLayout::BeforeContent, FiltersLayout::BeforeContentCollapsible]);
    $hasFiltersAfterContent = $isFilterable && in_array($filtersLayout, [FiltersLayout::AfterContent, FiltersLayout::AfterContentCollapsible]);
    $hasCollapsibleFilters = $isFilterable && in_array($filtersLayout, [
        FiltersLayout::AboveContentCollapsible,
        FiltersLayout::BeforeContentCollapsible,
        FiltersLayout::AfterContentCollapsible,
    ]);
    $hasFiltersTrigger = $isFilterable && ($hasFiltersDialog || $hasFiltersBeforeContent || $hasFiltersAfterContent);
    $isFiltersHidden = $filtersLayout === FiltersLayout::Hidden;

    // Toolbar visibility
    $hasHeaderToolbar = $isSearchable || $hasFiltersTrigger;
@endphp

{{-- Hidden layout: render nothing --}}
@if ($isFiltersHidden)
    {{-- No filter UI --}}
@elseif ($isFilterable || $isSearchable)
    {{-- Main container with Filament's table CSS cascade --}}
    <div
        @if ($hasCollapsibleFilters || $hasFiltersBeforeContent || $hasFiltersAfterContent)
            x-data="{ areFiltersOpen: @js(! $hasCollapsibleFilters) }"
        @endif
        @class([
            'fi-ta-ctn mb-4 !overflow-visible !shadow-none !ring-0 !bg-transparent',
            'flex' => $hasFiltersBeforeContent || $hasFiltersAfterContent,
        ])
    >
        {{-- BeforeContent sidebar (left of board) --}}
        @if ($hasFiltersBeforeContent)
            <div
                x-ref="filtersContentContainer"
                x-transition:enter-start="fi-opacity-0"
                x-transition:leave-end="fi-opacity-0"
                x-bind:class="{ 'fi-open': areFiltersOpen }"
                @class([
                    'fi-ta-filters-before-content-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mr-4',
                    'lg:fi-open' => ! $hasCollapsibleFilters,
                    (($filtersFormWidth ??= Width::ExtraSmall) instanceof Width) ? "fi-width-{$filtersFormWidth->value}" : (is_string($filtersFormWidth) ? $filtersFormWidth : null),
                ])
            >
                <x-filament-tables::filters
                    :apply-action="$filtersApplyAction"
                    :form="$filtersForm"
                    class="fi-ta-filters-before-content"
                    :reset-action="$filtersResetAction"
                    :reset-action-position="$filtersResetActionPosition"
                />
            </div>
        @endif

        {{-- Main content area --}}
        <div class="fi-ta-main flex-1">
            {{-- Filters Above Content (AboveContent and AboveContentCollapsible) --}}
            @if ($hasFiltersAboveContent)
                <div
                    @if ($hasCollapsibleFilters)
                        x-bind:class="{ 'fi-open': areFiltersOpen }"
                    @endif
                    @class([
                        'fi-ta-filters-above-content-ctn',
                        '!border-b-0 rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mb-4',
                    ])
                >
                    <x-filament-tables::filters
                        :apply-action="$filtersApplyAction"
                        :form="$filtersForm"
                        x-cloak
                        :x-show="$hasCollapsibleFilters ? 'areFiltersOpen' : null"
                        :reset-action="$filtersResetAction"
                        :reset-action-position="$filtersResetActionPosition"
                    />

                    @if ($hasCollapsibleFilters)
                        <span
                            x-on:click="areFiltersOpen = ! areFiltersOpen"
                            class="fi-ta-filters-trigger-action-ctn"
                        >
                            {{ $filtersTriggerAction->badge($activeFiltersCount) }}
                        </span>
                    @endif
                </div>
            @endif

            {{-- Toolbar with search and dialog triggers (for Dropdown/Modal/Before/After layouts) --}}
            @if ($hasHeaderToolbar)
                <div class="fi-ta-header-toolbar flex items-center gap-x-4 mb-4 px-0">
                    {{-- Filters trigger for sidebar layouts (BeforeContent/AfterContent) --}}
                    @if ($hasFiltersBeforeContent || $hasFiltersAfterContent)
                        <span
                            x-on:click="areFiltersOpen = ! areFiltersOpen"
                            class="fi-ta-filters-trigger-action-ctn"
                        >
                            {{ $filtersTriggerAction->badge($activeFiltersCount) }}
                        </span>
                    @endif

                    {{-- Filters Dialog (Dropdown or Modal) --}}
                    @if ($hasFiltersDialog)
                        @if (($filtersLayout === FiltersLayout::Modal) || $filtersTriggerAction->isModalSlideOver())
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
                            <x-filament::dropdown
                                :max-height="$filtersFormMaxHeight"
                                placement="bottom-start"
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
                                    :reset-action="$filtersResetAction"
                                    :reset-action-position="$filtersResetActionPosition"
                                />
                            </x-filament::dropdown>
                        @endif
                    @endif

                    @if ($isSearchable)
                        <x-filament-tables::search-field
                            :debounce="$table->getSearchDebounce()"
                            :on-blur="$table->isSearchOnBlur()"
                            :placeholder="$table->getSearchPlaceholder()"
                        />
                    @endif
                </div>
            @endif

            {{-- Board content slot (rendered by parent view) --}}
            {{ $slot ?? '' }}

            {{-- Filters Below Content --}}
            @if ($hasFiltersBelowContent)
                <div
                    @class([
                        'fi-ta-filters-below-content-ctn',
                        'rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 mt-4',
                    ])
                >
                    <x-filament-tables::filters
                        :apply-action="$filtersApplyAction"
                        :form="$filtersForm"
                        class="fi-ta-filters-below-content"
                        :reset-action="$filtersResetAction"
                        :reset-action-position="$filtersResetActionPosition"
                    />
                </div>
            @endif

            {{-- Filter indicators --}}
            @if ($filterIndicators)
                @if (filled($filterIndicatorsView = FilamentView::renderHook(TablesRenderHook::FILTER_INDICATORS, scopes: static::class, data: ['filterIndicators' => $filterIndicators])))
                    {{ $filterIndicatorsView }}
                @else
                    <div class="fi-ta-filter-indicators mt-3 px-0">
                        <div>
                            <span class="fi-ta-filter-indicators-label">
                                {{ __('filament-tables::table.filters.indicator') }}
                            </span>

                            <div class="fi-ta-filter-indicators-badges-ctn">
                                @foreach ($filterIndicators as $indicator)
                                    @php
                                        $indicatorColor = $indicator->getColor();
                                    @endphp

                                    <x-filament::badge :color="$indicatorColor">
                                        {{ $indicator->getLabel() }}

                                        @if ($indicator->isRemovable())
                                            @php
                                                $indicatorRemoveLivewireClickHandler = $indicator->getRemoveLivewireClickHandler();
                                            @endphp

                                            <x-slot
                                                name="deleteButton"
                                                :label="__('filament-tables::table.filters.actions.remove.label')"
                                                :wire:click="$indicatorRemoveLivewireClickHandler"
                                                wire:loading.attr="disabled"
                                                wire:target="removeTableFilter"
                                            ></x-slot>
                                        @endif
                                    </x-filament::badge>
                                @endforeach
                            </div>
                        </div>

                        @if (collect($filterIndicators)->contains(fn (Indicator $indicator): bool => $indicator->isRemovable()))
                            <button
                                type="button"
                                x-tooltip="{
                                    content: @js(__('filament-tables::table.filters.actions.remove_all.tooltip')),
                                    theme: $store.theme,
                                }"
                                wire:click="removeTableFilters"
                                wire:loading.attr="disabled"
                                wire:target="removeTableFilters,removeTableFilter"
                                class="fi-icon-btn fi-size-sm"
                            >
                                {{ generate_icon_html(Heroicon::XMark, alias: TablesIconAlias::FILTERS_REMOVE_ALL_BUTTON, size: IconSize::Small) }}
                            </button>
                        @endif
                    </div>
                @endif
            @endif
        </div>

        {{-- AfterContent sidebar (right of board) --}}
        @if ($hasFiltersAfterContent)
            <div
                x-ref="filtersContentContainer"
                x-transition:enter-start="fi-opacity-0"
                x-transition:leave-end="fi-opacity-0"
                x-bind:class="{ 'fi-open': areFiltersOpen }"
                @class([
                    'fi-ta-filters-after-content-ctn rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 ml-4',
                    'lg:fi-open' => ! $hasCollapsibleFilters,
                    (($filtersFormWidth ??= Width::ExtraSmall) instanceof Width) ? "fi-width-{$filtersFormWidth->value}" : (is_string($filtersFormWidth) ? $filtersFormWidth : null),
                ])
            >
                <x-filament-tables::filters
                    :apply-action="$filtersApplyAction"
                    :form="$filtersForm"
                    class="fi-ta-filters-after-content"
                    :reset-action="$filtersResetAction"
                    :reset-action-position="$filtersResetActionPosition"
                />
            </div>
        @endif
    </div>
@elseif ($isSearchable)
    <div class="fi-ta-ctn mb-4 !overflow-visible !shadow-none !ring-0 !bg-transparent">
        <div class="fi-ta-header-toolbar flex items-center gap-x-4">
            <x-filament-tables::search-field
                :debounce="$table->getSearchDebounce()"
                :on-blur="$table->isSearchOnBlur()"
                :placeholder="$table->getSearchPlaceholder()"
            />
        </div>
    </div>
@endif
