<?php

namespace App\Livewire;

use App\Jobs\ImportTraktZip;
use App\Models\TraktImport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Trakt-Import: ZIP hochladen, Verarbeitung läuft im worker, Fortschritt und Bericht per Polling.
 */
#[Title('Trakt-Import')]
class TraktImportPage extends Component
{
    use WithFileUploads;

    /**
     * Größe des ZIPs in KB (Laravel-Regel max:…).
     */
    public const int MAX_UPLOAD_KB = 51_200;

    public ?UploadedFile $file = null;

    /**
     * Die letzten Importe, neuester zuerst.
     *
     * @return Collection<int, TraktImport>
     */
    #[Computed]
    public function imports(): Collection
    {
        return TraktImport::query()->latest('id')->limit(5)->get();
    }

    #[Computed]
    public function isRunning(): bool
    {
        return $this->imports->contains(fn (TraktImport $import): bool => ! $import->isFinished());
    }

    public function startImport(): void
    {
        if ($this->isRunning) {
            return;
        }

        $this->validate(
            ['file' => ['required', 'file', 'mimes:zip', 'max:'.self::MAX_UPLOAD_KB]],
            ['file.mimes' => 'Bitte die ZIP-Datei aus dem Trakt-Export hochladen.'],
            ['file' => 'Datei'],
        );

        $import = TraktImport::create([
            'original_name' => Str::limit($this->file->getClientOriginalName(), 200, ''),
            'path' => $this->file->storeAs('imports', Str::uuid().'.zip', 'local'),
            'status' => TraktImport::PENDING,
        ]);

        ImportTraktZip::dispatch($import);

        $this->reset('file');
        unset($this->imports, $this->isRunning);
    }

    public function render(): View
    {
        return view('livewire.trakt-import-page', ['maxUploadMb' => intdiv(self::MAX_UPLOAD_KB, 1024)]);
    }
}
