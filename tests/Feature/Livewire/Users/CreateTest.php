<?php

declare(strict_types = 1);

use App\Livewire\Users\Create;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseHas;

beforeEach(fn () => User::query()->delete());

it('renders the create user component', function (): void {
    Livewire::test(Create::class)
        ->assertOk()
        ->assertViewIs('livewire.users.create');
});

it('initializes with a new user', function (): void {
    Livewire::test(Create::class)
        ->assertSet('user', fn ($user): bool => $user instanceof User)
        ->assertSet('password', null)
        ->assertSet('password_confirmation', null);
});

it('validates user creation with valid data', function (): void {
    $data = [
        'user.name'             => 'John Doe',
        'user.email'            => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)
        ->set($data)
        ->call('save')
        ->assertHasNoErrors();

    assertDatabaseHas('users', [
        'name'  => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

it('requires name', function (): void {
    Livewire::test(Create::class)
        ->set('user.name', '')
        ->set('user.email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['user.name' => 'required']);
});

it('requires unique email', function (): void {
    User::create([
        'name'     => 'Existing User',
        'email'    => 'existing@example.com',
        'password' => bcrypt('password123'),
    ]);

    Livewire::test(Create::class)
        ->set('user.name', 'John Doe')
        ->set('user.email', 'existing@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['user.email' => 'unique']);
});

it('validates email format', function (): void {
    Livewire::test(Create::class)
        ->set('user.name', 'John Doe')
        ->set('user.email', 'invalid-email')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('save')
        ->assertHasErrors(['user.email' => 'email']);
});

it('requires password confirmation', function (): void {
    Livewire::test(Create::class)
        ->set('user.name', 'John Doe')
        ->set('user.email', 'john@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'different-password')
        ->call('save')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('requires minimum password length', function (): void {
    Livewire::test(Create::class)
        ->set('user.name', 'John Doe')
        ->set('user.email', 'john@example.com')
        ->set('password', 'short')
        ->set('password_confirmation', 'short')
        ->call('save')
        ->assertHasErrors(['password' => 'min']);
});

it('sets email verified at when creating user', function (): void {
    $data = [
        'user.name'             => 'John Doe',
        'user.email'            => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)
        ->set($data)
        ->call('save');

    $user = User::where('email', 'john@example.com')->first();

    expect($user->email_verified_at)->not()->toBeNull();
});

it('resets form after successful creation', function (): void {
    $data = [
        'user.name'             => 'John Doe',
        'user.email'            => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)
        ->set($data)
        ->call('save')
        ->assertSet('user', fn ($user): bool => $user instanceof User && null === $user->name)
        ->assertSet('password', null)
        ->assertSet('password_confirmation', null);
});

it('dispatches created event', function (): void {
    $data = [
        'user.name'             => 'John Doe',
        'user.email'            => 'john@example.com',
        'password'              => 'password123',
        'password_confirmation' => 'password123',
    ];

    Livewire::test(Create::class)
        ->set($data)
        ->call('save')
        ->assertDispatched('created');
});
