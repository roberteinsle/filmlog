@props(['companions', 'submit', 'cancel', 'submitLabel' => 'Speichern'])

{{-- Felder für App\Livewire\Forms\ViewingForm (Eigenschaft $form der Livewire-Komponente). --}}
<form wire:submit="{{ $submit }}" class="space-y-5">
    <div>
        <label for="viewing-date" class="mb-1.5 block text-sm font-medium text-stone-300">Datum</label>
        <input id="viewing-date" type="date" wire:model="form.date" max="{{ now(config('app.timezone'))->toDateString() }}" required
               class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 [color-scheme:dark] outline-none focus:border-amber-400">
        @error('form.date') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
    </div>

    <fieldset>
        <div class="mb-1.5 flex items-baseline justify-between">
            <legend class="text-sm font-medium text-stone-300">Bewertung</legend>
            <button type="button" wire:click="$set('form.rating', null)" class="text-xs text-stone-500 hover:text-stone-300">ohne Bewertung</button>
        </div>
        <div class="grid grid-cols-10 gap-1">
            @foreach (range(1, 10) as $value)
                <label wire:key="rating-{{ $value }}" class="relative">
                    <input type="radio" wire:model="form.rating" value="{{ $value }}" name="rating" class="peer sr-only">
                    <span class="flex h-10 cursor-pointer items-center justify-center rounded-md border border-stone-700 text-sm text-stone-300 transition peer-checked:border-amber-400 peer-checked:bg-amber-400 peer-checked:font-semibold peer-checked:text-stone-950 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400 hover:border-stone-500">{{ $value }}</span>
                </label>
            @endforeach
        </div>
        @error('form.rating') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
    </fieldset>

    <fieldset>
        <legend class="mb-1.5 text-sm font-medium text-stone-300">Mit wem? <span class="font-normal text-stone-500">Nichts gewählt = allein</span></legend>
        @if ($companions->isEmpty())
            <p class="text-sm text-stone-500">
                Noch keine Begleitungen angelegt.
                <a href="{{ route('settings') }}" wire:navigate class="text-amber-300 underline-offset-2 hover:underline">In den Einstellungen anlegen</a>
            </p>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach ($companions as $companion)
                    <label wire:key="companion-{{ $companion->id }}">
                        <input type="checkbox" wire:model="form.companionIds" value="{{ $companion->id }}" class="peer sr-only">
                        <span class="inline-block cursor-pointer rounded-full border border-stone-700 px-3 py-1.5 text-sm text-stone-300 transition peer-checked:border-amber-400 peer-checked:bg-amber-400/15 peer-checked:text-amber-200 peer-focus-visible:ring-2 peer-focus-visible:ring-amber-400 hover:border-stone-500">{{ $companion->name }}</span>
                    </label>
                @endforeach
            </div>
        @endif
        @error('form.companionIds.*') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
    </fieldset>

    <div>
        <label for="viewing-note" class="mb-1.5 block text-sm font-medium text-stone-300">Notiz</label>
        <input id="viewing-note" type="text" wire:model="form.note" maxlength="280" placeholder="Ein Satz zum Film (optional)"
               class="w-full rounded-lg border border-stone-700 bg-stone-950 px-3 py-2 text-stone-100 outline-none placeholder:text-stone-600 focus:border-amber-400">
        @error('form.note') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
    </div>

    <div class="flex justify-end gap-2 pt-1">
        <button type="button" wire:click="{{ $cancel }}" class="rounded-lg px-4 py-2 text-sm text-stone-400 hover:text-stone-100">Abbrechen</button>
        <button type="submit" class="rounded-lg bg-amber-400 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-300 disabled:opacity-60" wire:loading.attr="disabled" wire:target="{{ $submit }}">{{ $submitLabel }}</button>
    </div>
</form>
