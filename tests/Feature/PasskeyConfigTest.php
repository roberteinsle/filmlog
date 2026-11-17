<?php

/**
 * Lädt config/passkeys.php mit einer bestimmten APP_URL neu.
 *
 * @return array<string, mixed>
 */
function passkeyConfigFor(string $appUrl): array
{
    config(['app.url' => $appUrl]);

    return require base_path('config/passkeys.php');
}

test('relying party and origin are derived from APP_URL, even when it is sloppy', function (string $appUrl, string $relyingPartyId, array $origins) {
    $config = passkeyConfigFor($appUrl);

    expect($config['relying_party_id'])->toBe($relyingPartyId)
        ->and($config['allowed_origins'])->toBe($origins);
})->with([
    'clean' => ['https://filmlog.einsle.cloud', 'filmlog.einsle.cloud', ['https://filmlog.einsle.cloud']],
    'trailing slash' => ['https://filmlog.einsle.cloud/', 'filmlog.einsle.cloud', ['https://filmlog.einsle.cloud']],
    'without scheme' => ['filmlog.einsle.cloud', 'filmlog.einsle.cloud', ['https://filmlog.einsle.cloud']],
    'local with port' => ['http://localhost:8080', 'localhost', ['http://localhost:8080']],
    'spaces and upper case scheme' => [' HTTPS://filmlog.einsle.cloud ', 'filmlog.einsle.cloud', ['https://filmlog.einsle.cloud']],
]);

test('relying party and origins can be set explicitly', function () {
    putenv('PASSKEYS_RELYING_PARTY_ID=einsle.cloud');
    putenv('PASSKEYS_ALLOWED_ORIGINS=https://filmlog.einsle.cloud, https://film.einsle.cloud');

    try {
        $config = passkeyConfigFor('https://filmlog.einsle.cloud');
    } finally {
        putenv('PASSKEYS_RELYING_PARTY_ID');
        putenv('PASSKEYS_ALLOWED_ORIGINS');
    }

    expect($config['relying_party_id'])->toBe('einsle.cloud')
        ->and($config['allowed_origins'])->toBe(['https://filmlog.einsle.cloud', 'https://film.einsle.cloud']);
});
