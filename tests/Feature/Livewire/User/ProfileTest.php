<?php

declare(strict_types = 1);

use App\Livewire\User\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();

    $this->actingAs($this->user);
});

it('renders successfully', function (): void {
    Livewire::test(Profile::class)
        ->assertStatus(200)
        ->assertViewIs('livewire.user.profile');
});

it('mounts with authenticated user data', function (): void {
    Livewire::test(Profile::class)
        ->assertSet('user.id', $this->user->id)
        ->assertSet('user.name', $this->user->name);
});

it('validates required name', function (): void {
    Livewire::test(Profile::class)
        ->set('user.name', '')
        ->call('save')
        ->assertHasErrors(['user.name' => 'required']);
});

it('validates maximum length of name', function (): void {
    Livewire::test(Profile::class)
        ->set('user.name', str_repeat('a', 256))
        ->call('save')
        ->assertHasErrors(['user.name' => 'max']);
});

it('validates password confirmation', function (): void {
    Livewire::test(Profile::class)
        ->set('password', 'newpassword')
        ->set('password_confirmation', 'wrongconfirmation')
        ->call('save')
        ->assertHasErrors(['password' => 'confirmed']);
});

it('allows updating name without changing password', function (): void {
    Livewire::test(Profile::class)
        ->set('user.name', 'Updated Name')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('updated');

    expect($this->user->refresh()->name)->toBe('Updated Name');
});

it('updates password when provided', function (): void {
    Hash::shouldReceive('isHashed')
        ->once()
        ->withAnyArgs()
        ->andReturn(false);

    Hash::shouldReceive('make')->andReturn('hashed-password');

    Livewire::test(Profile::class)
        ->set('password', 'newpassword')
        ->set('password_confirmation', 'newpassword')
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('updated');

    expect($this->user->refresh()->password)->toBe('hashed-password');
});

it('does not update password when null', function (): void {
    $originalPassword = $this->user->password;

    Livewire::test(Profile::class)
        ->set('password')
        ->call('save')
        ->assertHasNoErrors();

    expect($this->user->fresh()->password)->toBe($originalPassword);
});

it('dispatches success alert after saving', function (): void {
    Livewire::test(Profile::class)
        ->set('user.name', 'Updated Again')
        ->call('save')
        ->assertDispatched('updated')
        ->assertDispatched('tallstackui:dialog', fn (string $event, array $params): bool => 'tallstackui:dialog' === $event
            && 'success' === $params['type']
            && 'Done!' === $params['title']
            && 'Task completed successfully.' === $params['description']);
});

it('resets password fields after saving', function (): void {
    Livewire::test(Profile::class)
        ->set('password', 'newpassword')
        ->set('password_confirmation', 'newpassword')
        ->call('save')
        ->assertSet('password', null)
        ->assertSet('password_confirmation', null);
});
