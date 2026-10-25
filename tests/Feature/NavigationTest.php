<?php

use App\Models\User;

test('every screen renders for a logged-in user', function (string $routeName, string $heading) {
    $this->actingAs(User::factory()->create())
        ->get(route($routeName))
        ->assertOk()
        ->assertSee($heading);
})->with([
    ['tonight', 'Heute Abend'],
    ['search', 'Suche'],
    ['lists', 'Listen'],
    ['diary', 'Tagebuch'],
    ['review', 'Rückblick'],
]);

test('the health check responds without login', function () {
    $this->get('/up')->assertOk();
});
