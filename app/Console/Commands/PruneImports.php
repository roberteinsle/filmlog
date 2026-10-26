<?php

namespace App\Console\Commands;

use App\Models\TraktImport;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('filmlog:prune-imports {--days=7 : Abgeschlossene Importe, die älter sind, verlieren ihr ZIP}')]
#[Description('Löscht die ZIP-Dateien abgeschlossener Trakt-Importe; der Import-Bericht bleibt erhalten')]
class PruneImports extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $finishedBefore = now()->subDays((int) $this->option('days'));
        $deleted = 0;

        TraktImport::query()
            ->whereIn('status', [TraktImport::DONE, TraktImport::FAILED])
            ->where('finished_at', '<', $finishedBefore)
            ->each(function (TraktImport $import) use ($disk, &$deleted): void {
                if ($disk->exists($import->path)) {
                    $disk->delete($import->path);
                    $deleted++;
                }
            });

        // ZIPs ohne Import-Datensatz (z. B. abgebrochene Uploads)
        $knownPaths = TraktImport::query()->pluck('path')->flip();

        foreach ($disk->files('imports') as $path) {
            if (! $knownPaths->has($path) && $disk->lastModified($path) < $finishedBefore->getTimestamp()) {
                $disk->delete($path);
                $deleted++;
            }
        }

        $this->info("{$deleted} Import-Dateien gelöscht.");

        return self::SUCCESS;
    }
}
