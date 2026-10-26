<?php

namespace App\Livewire\Forms;

use App\Actions\LogViewing;
use App\Models\Viewing;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Form;

/**
 * Felder des Gesehen-Dialogs: Datum, Bewertung, Begleitung, Notiz.
 * Wird auf der Filmseite (neue Sichtung) und im Tagebuch (bearbeiten) verwendet.
 */
class ViewingForm extends Form
{
    /**
     * Uhrzeit für Sichtungen an einem vergangenen Tag (übliche Filmabend-Zeit).
     */
    private const string DEFAULT_TIME = '20:00';

    public ?Viewing $viewing = null;

    public string $date = '';

    public ?int $rating = null;

    /** @var list<int|string> */
    public array $companionIds = [];

    public string $note = '';

    /**
     * @return array<string, list<string>>
     */
    protected function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:1900-01-01', 'before_or_equal:'.$this->today()],
            'rating' => ['nullable', 'integer', 'between:1,10'],
            'companionIds' => ['array'],
            'companionIds.*' => ['integer', 'distinct', 'exists:companions,id'],
            'note' => ['nullable', 'string', 'max:280'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'date' => 'Datum',
            'rating' => 'Bewertung',
            'companionIds.*' => 'Begleitung',
            'note' => 'Notiz',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date.before_or_equal' => 'Das Datum darf nicht in der Zukunft liegen.',
        ];
    }

    /**
     * Leeres Formular für eine neue Sichtung von heute.
     */
    public function startNew(): void
    {
        $this->resetErrorBag();
        $this->viewing = null;
        $this->date = $this->today();
        $this->rating = null;
        $this->companionIds = [];
        $this->note = '';
    }

    public function edit(Viewing $viewing): void
    {
        $this->resetErrorBag();
        $this->viewing = $viewing;
        $this->date = $viewing->watched_at->timezone(config('app.timezone'))->toDateString();
        $this->rating = $viewing->rating;
        $this->companionIds = $viewing->companions()->pluck('companions.id')->all();
        $this->note = (string) $viewing->note;
    }

    public function store(int $tmdbId): Viewing
    {
        $this->validate();

        return app(LogViewing::class)->handle(
            tmdbId: $tmdbId,
            watchedAt: $this->watchedAt(),
            rating: $this->rating,
            note: $this->normalizedNote(),
            companionIds: $this->normalizedCompanionIds(),
        );
    }

    public function update(): Viewing
    {
        $this->validate();

        $viewing = $this->viewing;

        DB::transaction(function () use ($viewing): void {
            $viewing->update([
                'watched_at' => $this->watchedAt($viewing),
                'rating' => $this->rating,
                'note' => $this->normalizedNote(),
            ]);

            $viewing->companions()->sync($this->normalizedCompanionIds());
        });

        return $viewing;
    }

    /**
     * Heute → jetzt; ein anderer Tag → 20:00 Uhr. Bleibt der Tag beim Bearbeiten gleich, bleibt auch die Uhrzeit.
     */
    private function watchedAt(?Viewing $existing = null): CarbonInterface
    {
        $timezone = config('app.timezone');

        if ($existing !== null && $existing->watched_at->timezone($timezone)->toDateString() === $this->date) {
            return $existing->watched_at;
        }

        if ($this->date === $this->today()) {
            return now();
        }

        return Carbon::createFromFormat('Y-m-d H:i', $this->date.' '.self::DEFAULT_TIME, $timezone);
    }

    private function normalizedNote(): ?string
    {
        $note = trim($this->note);

        return $note === '' ? null : $note;
    }

    /**
     * @return list<int>
     */
    private function normalizedCompanionIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->companionIds)));
    }

    private function today(): string
    {
        return now(config('app.timezone'))->toDateString();
    }
}
