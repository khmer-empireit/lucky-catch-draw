<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-3 sm:flex-row">
            <x-filament::button tag="a" :href="$createUrl" icon="heroicon-o-plus-circle" color="primary" class="flex-1 justify-center">
                New Campaign
            </x-filament::button>

            <x-filament::button tag="a" :href="$uploadUrl" icon="heroicon-o-arrow-up-tray" color="gray" outlined class="flex-1 justify-center">
                Upload Excel
            </x-filament::button>

            <x-filament::button tag="a" :href="$drawUrl" icon="heroicon-o-sparkles" color="gray" outlined class="flex-1 justify-center">
                Run Draw
            </x-filament::button>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
