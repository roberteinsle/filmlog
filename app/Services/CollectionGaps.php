<?php

namespace App\Services;

use App\Models\MovieCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * „Lücken“: Filmreihen, aus denen mindestens ein, aber nicht jeder bereits erschienene Teil gesehen ist.
 */
class CollectionGaps
{
    /**
     * @return Collection<int, array{collection: MovieCollection, seen: int, released: int, missing: list<array{id: int, title: string, release_date: ?string, poster_path: ?string}>}>
     */
    public function all(): Collection
    {
        $seenTmdbIds = DB::table('viewings')->distinct()->pluck('tmdb_id')->map(fn ($id): int => (int) $id)->flip();

        $collectionIds = DB::table('movies')
            ->whereNotNull('collection_id')
            ->whereIn('tmdb_id', $seenTmdbIds->keys())
            ->distinct()
            ->pluck('collection_id');

        return MovieCollection::query()
            ->whereIn('id', $collectionIds)
            ->get()
            ->map(function (MovieCollection $collection) use ($seenTmdbIds): array {
                $released = $collection->releasedParts();
                $missing = array_values(array_filter($released, fn (array $part): bool => ! $seenTmdbIds->has($part['id'])));

                return [
                    'collection' => $collection,
                    'seen' => count($released) - count($missing),
                    'released' => count($released),
                    'missing' => $missing,
                ];
            })
            ->filter(fn (array $gap): bool => $gap['seen'] > 0 && $gap['missing'] !== [])
            // Fast vollständige Reihen zuerst
            ->sortBy([fn (array $a, array $b): int => count($a['missing']) <=> count($b['missing']), fn (array $a, array $b): int => strcmp($a['collection']->name, $b['collection']->name)])
            ->values();
    }

    /**
     * Gesehene Filme, deren Filmreihe noch nicht geladen ist (z. B. direkt nach dem Trakt-Import).
     */
    public function pendingCollections(): int
    {
        return DB::table('movies')
            ->whereNotNull('collection_id')
            ->whereExists(fn ($query) => $query->selectRaw('1')->from('viewings')->whereColumn('viewings.tmdb_id', 'movies.tmdb_id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('collections')->whereColumn('collections.id', 'movies.collection_id'))
            ->distinct()
            ->count('collection_id');
    }
}
