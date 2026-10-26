<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Login per Magic Link
    |--------------------------------------------------------------------------
    |
    | Filmlog hat genau ein Konto. Nur an diese Adresse wird ein Login-Link
    | verschickt; das Konto wird beim ersten Login automatisch angelegt.
    |
    */

    'admin_email' => env('FILMLOG_ADMIN_EMAIL', 'robert@einsle.com'),

    'login_link_minutes' => 15,

    'login_lifetime_days' => 7,

    /*
    |--------------------------------------------------------------------------
    | Build-Info
    |--------------------------------------------------------------------------
    |
    | Wird beim Image-Build von der GitHub Action gesetzt (APP_COMMIT,
    | APP_COMMIT_DATE als ISO-8601) und neben dem App-Namen angezeigt.
    |
    */

    'commit' => env('APP_COMMIT'),

    'commit_date' => env('APP_COMMIT_DATE'),

    /*
    |--------------------------------------------------------------------------
    | TMDB-Sprache und Region
    |--------------------------------------------------------------------------
    |
    | Sprache für Titel und Inhalt, Region für FSK (release_dates) und
    | Streaming-Anbieter (watch/providers).
    |
    */

    'language' => env('FILMLOG_LANGUAGE', 'de-DE'),

    'region' => env('FILMLOG_REGION', 'DE'),

    /*
    |--------------------------------------------------------------------------
    | Mindeststimmen für den TMDB-Filter
    |--------------------------------------------------------------------------
    |
    | Der Filter „TMDB ≥ X“ berücksichtigt nur Filme mit mindestens so vielen
    | Stimmen, damit Nischenfilme mit drei 10er-Bewertungen nicht oben landen.
    |
    */

    'tmdb_min_votes' => 500,

    /*
    |--------------------------------------------------------------------------
    | Cache-Dauer für Filmdaten
    |--------------------------------------------------------------------------
    |
    | Nach so vielen Tagen aktualisiert der nächtliche Job einen Film in der
    | Tabelle movies.
    |
    */

    'movie_cache_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | TMDB-Genres
    |--------------------------------------------------------------------------
    |
    | Deutsche Namen der TMDB-Filmgenres (/genre/movie/list). movies.genre_ids
    | speichert nur die IDs; Filter und Anzeige übersetzen sie hiermit.
    |
    */

    'genres' => [
        28 => 'Action',
        12 => 'Abenteuer',
        16 => 'Animation',
        35 => 'Komödie',
        80 => 'Krimi',
        99 => 'Dokumentarfilm',
        18 => 'Drama',
        10751 => 'Familie',
        14 => 'Fantasy',
        36 => 'Historie',
        27 => 'Horror',
        10402 => 'Musik',
        9648 => 'Mystery',
        10749 => 'Liebesfilm',
        878 => 'Science Fiction',
        10770 => 'TV-Film',
        53 => 'Thriller',
        10752 => 'Kriegsfilm',
        37 => 'Western',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stimmungen für „Heute Abend“
    |--------------------------------------------------------------------------
    |
    | Jede Stimmung verweist auf TMDB-Genre-IDs. Ein Film passt, wenn er
    | mindestens eines der Genres hat.
    |
    */

    'moods' => [
        'lachen' => ['label' => 'Lachen', 'genres' => [35]],                    // Komödie
        'spannung' => ['label' => 'Spannung', 'genres' => [53, 80, 28]],        // Thriller, Krimi, Action
        'gefuehl' => ['label' => 'Gefühl', 'genres' => [18, 10749]],            // Drama, Liebesfilm
        'kopfkino' => ['label' => 'Kopfkino', 'genres' => [878, 9648, 14]],     // Science-Fiction, Mystery, Fantasy
        'gemuetlich' => ['label' => 'Gemütlich', 'genres' => [10751, 16, 12]],  // Familie, Animation, Abenteuer
        'gruseln' => ['label' => 'Gruseln', 'genres' => [27]],                  // Horror
    ],

];
