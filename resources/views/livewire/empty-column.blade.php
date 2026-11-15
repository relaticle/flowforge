@props(['pluralCardLabel'])

<div class="p-3 flex flex-col items-center justify-center h-full min-h-[150px] rounded-lg border-2 border-dashed border-gray-200 dark:border-gray-700">
    <x-filament::icon 
        :alias="\Relaticle\Flowforge\View\FlowforgeIconAlias::EMPTY_COLUMN"
        icon="heroicon-o-archive-box"
        class="w-10 h-10 text-gray-400 dark:text-gray-600 mb-2" 
    />
    <p class="text-sm text-gray-500 dark:text-gray-400">
        {{ __('flowforge::flowforge.no_cards_in_column', ['cardLabel' => strtolower($pluralCardLabel)]) }}
    </p>
</div>
