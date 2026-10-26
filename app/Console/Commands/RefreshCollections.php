<?php

namespace App\Console\Commands;

use App\Jobs\RefreshCollection;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('filmlog:refresh-collections')]
#[Description('Stellt RefreshCollection für Filmreihen gesehener Filme in die Queue (neu oder älter als 30 Tage)')]
class RefreshCollections extends Command
{
    public function handle(): int
    {
        $count = self::queueStale();

        $this->info("{$count} Filmreihen zum Aktualisieren eingereiht.");

        return self::SUCCESS;
    }

    /**
     * Filmreihen, aus denen mindestens ein Film gesehen ist und die fehlen oder veraltet sind.
     */
    public static function queueStale(): int
    {
        $staleBefore = now()->subDays(config('filmlog.movie_cache_days'));

        $collectionIds = DB::table('movies')
            ->whereNotNull('movies.collection_id')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('viewings')->whereColumn('viewings.tmdb_id', 'movies.tmdb_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('collections')
                ->whereColumn('collections.id', 'movies.collection_id')
                ->where('collections.cached_at', '>=', $staleBefore))
            ->distinct()
            ->orderBy('movies.collection_id')
            ->pluck('movies.collection_id');

        $collectionIds->each(fn ($collectionId) => RefreshCollection::dispatch((int) $collectionId));

        return $collectionIds->count();
    }
}
