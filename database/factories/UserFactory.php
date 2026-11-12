<?php

namespace Database\Factories;

use App\Role;
use App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        static $password;

        return [
            'name' => fake('el_GR')->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => $password ??= Hash::make('password'),
            'role_id' => fn () => Role::whereRole('Συγγραφέας')->value('id'),
            'active' => 1,
        ];
    }

    /** Role by its Greek name: Διαχειριστής, Αναθέτων, Συγγραφέας, Αναγνώστης */
    public function role(string $role): static
    {
        return $this->state(fn () => ['role_id' => Role::whereRole($role)->value('id')]);
    }

    public function admin(): static { return $this->role('Διαχειριστής'); }
    public function assigner(): static { return $this->role('Αναθέτων'); }
    public function writer(): static { return $this->role('Συγγραφέας'); }
    public function reader(): static { return $this->role('Αναγνώστης'); }

    public function inactive(): static
    {
        return $this->state(fn () => ['active' => null]);
    }
}
