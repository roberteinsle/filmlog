<div>
    @if ($variant === 'icon')
        <button type="button" wire:click="openDialog" class="flex size-10 items-center justify-center rounded-full text-stone-500 transition hover:bg-stone-800 hover:text-amber-300" aria-label="Zur Liste hinzufügen" title="Zur Liste">
            <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
        </button>
    @else
        <button type="button" wire:click="openDialog" class="inline-flex items-center gap-2 rounded-lg border border-stone-700 px-4 py-2 text-sm font-medium text-stone-200 transition hover:border-stone-500">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
            Zur Liste
        </button>
    @endif

    @if ($error)
        <p class="mt-1 text-xs text-red-400" role="alert">{{ $error }}</p>
    @endif

    @if ($open)
        <x-modal title="Auf welche Listen?" close="closeDialog">
            <ul class="mb-4 space-y-1">
                @foreach ($this->lists as $list)
                    @php($isMember = in_array($list->id, $this->memberListIds, true))
                    <li wire:key="list-{{ $list->id }}">
                        <button type="button" wire:click="toggleList({{ $list->id }})" role="checkbox" aria-checked="{{ $isMember ? 'true' : 'false' }}"
                                @class([
                                    'flex w-full items-center gap-3 rounded-lg border px-3 py-2.5 text-left text-sm transition',
                                    'border-amber-400/60 bg-amber-400/10 text-amber-100' => $isMember,
                                    'border-stone-800 text-stone-300 hover:border-stone-600' => ! $isMember,
                                ])>
                            <span @class(['flex size-5 shrink-0 items-center justify-center rounded border', 'border-amber-400 bg-amber-400 text-stone-950' => $isMember, 'border-stone-600' => ! $isMember])>
                                @if ($isMember)
                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5L20 7" /></svg>
                                @endif
                            </span>
                            <span class="flex-1">{{ $list->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>

            <form wire:submit="createList" class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model="newListName" maxlength="50" placeholder="Neue Liste" aria-label="Name der neuen Liste"
                           class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-sm outline-none placeholder:text-stone-600 focus:border-amber-400">
                    @error('newListName') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-lg border border-stone-700 px-3 py-2 text-sm text-stone-200 hover:border-stone-500">Anlegen</button>
            </form>

            <div class="mt-5 flex justify-end">
                <button type="button" wire:click="closeDialog" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300">Fertig</button>
            </div>
        </x-modal>
    @endif
</div>
