<?php

namespace App\Jobs;

use App\Actions\ImportTraktExport;
use App\Models\TraktImport;
use App\Services\Trakt\InvalidTraktExportException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Verarbeitet einen hochgeladenen Trakt-Export im worker. Jede Etappe läuft höchstens TIME_BUDGET Sekunden
 * und stellt sich danach selbst neu in die Queue – so bleibt sie unter dem Worker-Timeout und unter
 * retry_after der database-Queue (90 s), auch bei Exporten mit Tausenden Filmen.
 */
class ImportTraktZip implements ShouldQueue
{
    use Queueable;

    private const int TIME_BUDGET = 50;

    public int $timeout = 85;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 120];

    public function __construct(public TraktImport $import) {}

    public function handle(ImportTraktExport $importTraktExport): void
    {
        if ($this->import->isFinished()) {
            return;
        }

        try {
            $finished = $importTraktExport->handle($this->import, self::TIME_BUDGET);
        } catch (InvalidTraktExportException $exception) {
            $this->markFailed($exception->getMessage());

            return;
        }

        if (! $finished) {
            self::dispatch($this->import);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception?->getMessage() ?? 'Unbekannter Fehler');
    }

    private function markFailed(string $message): void
    {
        $this->import->fill([
            'status' => TraktImport::FAILED,
            'error' => $message,
            'finished_at' => now(),
        ])->save();
    }
}
