<?php

use Illuminate\Support\Facades\Queue;

test('every screen renders for a logged-in user', function (string $routeName, string $heading) {
    Queue::fake();
    actingAsLoggedInUser();

    $this->get(route($routeName))
        ->assertOk()
        ->assertSee($heading);
})->with([
    ['tonight', 'Heute Abend'],
    ['search', 'Suche'],
    ['lists', 'Listen'],
    ['charts', 'Kinocharts'],
    ['diary', 'Tagebuch'],
    ['review', 'Rückblick'],
    ['settings', 'Einstellungen'],
]);

test('the header shows the short commit and its date', function () {
    config([
        'filmlog.commit' => '39236581f0c2a8d7e6b5a4c3d2e1f0a9b8c7d6e5',
        'filmlog.commit_date' => '2026-10-25T23:30:00+00:00',
    ]);
    actingAsLoggedInUser();

    $this->get(route('tonight'))
        ->assertSee('3923658 · 26.10.2026')
        ->assertDontSee('39236581')
        ->assertDontSee('Commit');
});

test('the login page shows no build info', function () {
    config(['filmlog.commit' => '39236581f0c2a8d7e6b5a4c3d2e1f0a9b8c7d6e5']);

    $this->get(route('login'))->assertDontSee('3923658');
});

test('the health check responds without login', function () {
    $this->get('/up')->assertOk();
});
