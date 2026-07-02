@php
    $boosterItemGroups = collect($boosterMenuGroups)
        ->flatMap(function (array $group): array {
            return collect($group['items'])
                ->mapWithKeys(function (array $item) use ($group): array {
                    if ($item['soon'] ?? false) {
                        return [];
                    }

                    $itemKey = data_get($item, 'query.favorites', false)
                        ? 'favorites'
                        : ($item['module'] ?? null);

                    return $itemKey ? [$itemKey => $group['key']] : [];
                })
                ->all();
        })
        ->all();
    $activeBoosterItem = $activeBoosterModule === 'tracking' && $boosterFavoritesOnly
        ? 'favorites'
        : $activeBoosterModule;
    $activeBoosterItem = array_key_exists($activeBoosterItem, $boosterItemGroups)
        ? $activeBoosterItem
        : 'analysis';
    $activeBoosterGroup = $boosterItemGroups[$activeBoosterItem] ?? 'product';
@endphp

<div
    x-data="{
        boosterOpen: {{ request()->routeIs('mp.trendyol-booster') ? 'true' : 'false' }},
        activeItem: @js($activeBoosterItem),
        openGroup: @js($activeBoosterGroup),
        itemGroups: @js($boosterItemGroups),
        activate(item, group = null) {
            const nextItem = this.itemGroups[item] ? item : 'analysis';
            this.activeItem = nextItem;
            this.openGroup = group || this.itemGroups[nextItem] || 'product';
            this.boosterOpen = true;
        },
        syncFromUrl() {
            const params = new URLSearchParams(window.location.search);
            const module = params.get('booster') || 'analysis';
            const favorites = ['1', 'true', 'on', 'yes'].includes(params.get('favorites'));
            const item = module === 'tracking' && favorites ? 'favorites' : module;
            this.activate(item, this.itemGroups[item]);
        }
    }"
    x-on:booster-module-changed.window="activate($event.detail.item || $event.detail.module, $event.detail.group)"
    x-on:popstate.window="syncFromUrl()"
    data-testid="trendyol-booster-sidebar-menu"
>
    <button type="button" @click="boosterOpen = !boosterOpen"
        class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-4 py-3 text-sm font-medium transition-colors
               {{ request()->routeIs('mp.trendyol-booster') ? 'bg-gray-100 text-gray-900' : 'text-gray-700 hover:bg-gray-100' }}">
        <span class="flex min-w-0 items-center">
            <x-lucide.icon name="activity" class="mr-3 h-5 w-5 shrink-0" />
            <span class="truncate">Trendyol Booster</span>
        </span>
        <svg :class="boosterOpen ? 'rotate-180' : ''" class="h-4 w-4 shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="boosterOpen" x-collapse x-cloak class="ml-4 mt-1 space-y-1">
        @foreach($boosterMenuGroups as $group)
            @php
                $groupKey = $group['key'];
            @endphp
            <div data-testid="booster-group-{{ $groupKey }}">
                <button
                    type="button"
                    @click="openGroup = openGroup === @js($groupKey) ? null : @js($groupKey)"
                    :aria-expanded="openGroup === @js($groupKey)"
                    class="flex min-h-[44px] w-full items-center justify-between rounded-lg px-3 py-2 text-xs font-semibold transition-colors lg:min-h-[40px]"
                    :class="openGroup === @js($groupKey) ? 'bg-slate-50 text-slate-900' : 'text-slate-500 hover:bg-slate-50 hover:text-slate-900'"
                >
                    <span class="min-w-0 truncate">{{ $group['label'] }}</span>
                    <svg :class="openGroup === @js($groupKey) ? 'rotate-180' : ''" class="h-3.5 w-3.5 shrink-0 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>

                <div x-show="openGroup === @js($groupKey)" x-collapse x-cloak class="ml-3 mt-0.5 space-y-0.5 border-l border-slate-200 pl-2">
                    @foreach($group['items'] as $item)
                        @if($item['soon'] ?? false)
                            <div title="{{ $item['label'] }} yakında kullanıma açılacak"
                                class="flex min-h-[44px] cursor-not-allowed items-center gap-2 rounded-lg px-3 py-2 text-xs text-slate-400 lg:min-h-[38px]">
                                <x-lucide.icon name="{{ $item['icon'] }}" class="h-3.5 w-3.5 shrink-0" />
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                                <span class="inline-flex shrink-0 items-center gap-1 rounded-full border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[9px] font-semibold text-amber-700">
                                    <x-lucide.icon name="clock" class="h-2.5 w-2.5" />
                                    Yakında
                                </span>
                            </div>
                        @else
                            @php
                                $itemQuery = array_merge(['booster' => $item['module']], $item['query'] ?? []);
                                $favoriteItem = (bool) data_get($item, 'query.favorites', false);
                                $itemKey = $favoriteItem ? 'favorites' : $item['module'];
                            @endphp
                            <a
                                href="{{ route('mp.trendyol-booster', $itemQuery) }}"
                                @click="activate(@js($itemKey), @js($groupKey)); sidebarOpen = false"
                                title="{{ $item['label'] }}"
                                class="flex min-h-[44px] items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors lg:min-h-[38px]"
                                :class="activeItem === @js($itemKey) ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'"
                            >
                                <x-lucide.icon name="{{ $item['icon'] }}" class="h-3.5 w-3.5 shrink-0 opacity-80" />
                                <span class="min-w-0 flex-1 truncate">{{ $item['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
