<div>
    <x-page-header title="Listen">Watchlist und eigene Listen, sortierbar und filterbar.</x-page-header>

    @php($watchlist = $this->lists->firstWhere('is_watchlist', true))
    @php($ownLists = $this->lists->where('is_watchlist', false))

    <div class="max-w-2xl space-y-6">
        <div class="rounded-xl border border-amber-400/30 bg-amber-400/5 px-4 py-3">
            <x-list-card :list="$watchlist" />
        </div>

        <section>
            <h2 class="mb-2 text-sm font-medium tracking-wide text-stone-500 uppercase">Eigene Listen</h2>

            @if ($ownLists->isEmpty())
                <p class="mb-4 text-sm text-stone-500">Noch keine eigenen Listen, z. B. „Weihnachtsfilme“ oder „Klassiker nachholen“.</p>
            @else
                <ul wire:sort="moveList" class="mb-4 divide-y divide-stone-800 rounded-xl border border-stone-800">
                    @foreach ($ownLists as $list)
                        <li wire:key="list-{{ $list->id }}" wire:sort:item="{{ $list->id }}" class="flex items-center gap-3 bg-stone-950 px-3 py-3 first:rounded-t-xl last:rounded-b-xl">
                            <button type="button" wire:sort:handle class="cursor-grab touch-none text-stone-600 hover:text-stone-300 active:cursor-grabbing" aria-label="{{ $list->name }} verschieben">
                                <svg class="size-5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="6" r="1.5" /><circle cx="15" cy="6" r="1.5" /><circle cx="9" cy="12" r="1.5" /><circle cx="15" cy="12" r="1.5" /><circle cx="9" cy="18" r="1.5" /><circle cx="15" cy="18" r="1.5" /></svg>
                            </button>

                            @if ($editingId === $list->id)
                                <form wire:submit="saveList" wire:sort:ignore class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                    <input type="text" wire:model="editName" maxlength="50" aria-label="Name der Liste" required
                                           class="min-w-0 flex-1 rounded-lg border border-stone-700 bg-stone-900 px-3 py-1.5 text-sm outline-none focus:border-amber-400">
                                    <button type="submit" class="rounded-lg bg-amber-400 px-3 py-1.5 text-sm font-semibold text-stone-950 hover:bg-amber-300">Speichern</button>
                                    <button type="button" wire:click="cancelEditing" class="px-2 py-1.5 text-sm text-stone-400 hover:text-stone-100">Abbrechen</button>
                                    @error('editName') <p class="w-full text-sm text-red-400">{{ $message }}</p> @enderror
                                </form>
                            @else
                                <x-list-card :list="$list" />
                                <div class="flex shrink-0 flex-col items-end gap-1 text-xs sm:flex-row sm:gap-3" wire:sort:ignore>
                                    <button type="button" wire:click="startEditing({{ $list->id }})" class="text-stone-500 hover:text-stone-200">Umbenennen</button>
                                    <button type="button" wire:click="deleteList({{ $list->id }})"
                                            wire:confirm="Liste „{{ $list->name }}“ löschen? Die Filme selbst und ihre Sichtungen bleiben erhalten."
                                            class="text-stone-500 hover:text-red-400">Löschen</button>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form wire:submit="createList" class="flex items-start gap-2">
                <div class="min-w-0 flex-1">
                    <input type="text" wire:model="name" maxlength="50" placeholder="Neue Liste" aria-label="Name der neuen Liste"
                           class="w-full rounded-lg border border-stone-700 bg-stone-900 px-3 py-2 text-sm outline-none placeholder:text-stone-600 focus:border-amber-400">
                    @error('name') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
                </div>
                <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 hover:bg-amber-300">Anlegen</button>
            </form>
        </section>
    </div>
</div>
