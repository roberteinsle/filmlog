<?php

use App\Livewire\Settings;
use App\Models\Companion;
use App\Models\Movie;
use App\Models\Viewing;
use Livewire\Livewire;

beforeEach(function () {
    actingAsLoggedInUser();
});

test('the settings page is linked from the header', function () {
    $this->get(route('tonight'))->assertSee(route('settings'));

    $this->get(route('settings'))
        ->assertOk()
        ->assertSee('<title>Einstellungen · Filmlog</title>', false)
        ->assertSee('Begleitungen');
});

test('companions are listed in their order with FSK limit and usage', function () {
    $family = Companion::factory()->create(['name' => 'Familie', 'max_fsk' => 12, 'sort' => 2]);
    Companion::factory()->create(['name' => 'Jasmin', 'max_fsk' => null, 'sort' => 1]);
    Viewing::factory()->for(Movie::factory()->create())->create()->companions()->attach($family);

    $this->get(route('settings'))
        ->assertSeeInOrder(['Jasmin', 'ohne FSK-Grenze', '0 Sichtungen', 'Familie', 'bis FSK 12', '1 Sichtung']);
});

test('a companion can be added at the end', function () {
    Companion::factory()->create(['name' => 'Jasmin', 'sort' => 4]);

    Livewire::test(Settings::class)
        ->set('name', ' Familie ')
        ->set('maxFsk', '12')
        ->call('addCompanion')
        ->assertHasNoErrors()
        ->assertSet('name', '')
        ->assertSee('Familie');

    expect(Companion::firstWhere('name', 'Familie'))
        ->max_fsk->toBe(12)
        ->sort->toBe(5);
});

test('adding validates name and FSK', function (array $input, string $errorField) {
    Companion::factory()->create(['name' => 'Jasmin']);

    Livewire::test(Settings::class)
        ->set($input)
        ->call('addCompanion')
        ->assertHasErrors($errorField);

    expect(Companion::count())->toBe(1);
})->with([
    'empty name' => [['name' => ''], 'name'],
    'duplicate name' => [['name' => 'Jasmin'], 'name'],
    'name too long' => [['name' => str_repeat('x', 51)], 'name'],
    'unknown FSK' => [['name' => 'Familie', 'maxFsk' => '14'], 'maxFsk'],
]);

test('a companion can be renamed and its FSK limit changed', function () {
    $companion = Companion::factory()->create(['name' => 'Famile', 'max_fsk' => 12]);

    Livewire::test(Settings::class)
        ->call('startEditing', $companion->id)
        ->assertSet('editName', 'Famile')
        ->assertSet('editMaxFsk', '12')
        ->set('editName', 'Familie')
        ->set('editMaxFsk', '')
        ->call('saveCompanion')
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    expect($companion->refresh())
        ->name->toBe('Familie')
        ->max_fsk->toBeNull();
});

test('renaming rejects a name that is already taken', function () {
    Companion::factory()->create(['name' => 'Jasmin']);
    $companion = Companion::factory()->create(['name' => 'Tom']);

    Livewire::test(Settings::class)
        ->call('startEditing', $companion->id)
        ->set('editName', 'Jasmin')
        ->call('saveCompanion')
        ->assertHasErrors('editName');

    expect($companion->refresh()->name)->toBe('Tom');
});

test('deleting a companion keeps its viewings', function () {
    $companion = Companion::factory()->create();
    $viewing = Viewing::factory()->for(Movie::factory()->create())->create();
    $viewing->companions()->attach($companion);

    Livewire::test(Settings::class)->call('deleteCompanion', $companion->id);

    expect(Companion::count())->toBe(0)
        ->and($viewing->fresh())->not->toBeNull()
        ->and($viewing->fresh()->companions)->toBeEmpty();
});

test('companions can be reordered by drag and drop', function () {
    $jasmin = Companion::factory()->create(['name' => 'Jasmin', 'sort' => 1]);
    $family = Companion::factory()->create(['name' => 'Familie', 'sort' => 2]);
    $tom = Companion::factory()->create(['name' => 'Tom', 'sort' => 3]);

    Livewire::test(Settings::class)->call('moveCompanion', (string) $tom->id, 0);

    expect(Companion::query()->ordered()->pluck('id')->all())->toBe([$tom->id, $jasmin->id, $family->id]);

    Livewire::test(Settings::class)->call('moveCompanion', $tom->id, 5);

    expect(Companion::query()->ordered()->pluck('id')->all())->toBe([$jasmin->id, $family->id, $tom->id]);
});
