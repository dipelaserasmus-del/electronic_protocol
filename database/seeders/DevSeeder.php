<?php

namespace Database\Seeders;

use App\Config;
use App\Role;
use App\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo users and settings for the local development / preview stack only.
 * Run with: ./dev.sh artisan db:seed --class=DevSeeder   (done automatically by ./dev.sh up)
 * Never run against production: it creates accounts with well-known passwords.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new \RuntimeException('DevSeeder must not run in production.');
        }

        $users = [
            ['admin', 'Διαχειριστής Δοκιμών', 'Διαχειριστής'],
            ['anathetis', 'Αναθέτων Δοκιμών', 'Αναθέτων'],
            ['writer', 'Συγγραφέας Δοκιμών', 'Συγγραφέας'],
            ['reader', 'Αναγνώστης Δοκιμών', 'Αναγνώστης'],
        ];
        foreach ($users as [$username, $name, $role]) {
            User::updateOrCreate(['username' => $username], [
                'name' => $name,
                'email' => "$username@school.test",
                'password' => Hash::make('password'),
                'role_id' => Role::whereRole($role)->value('id'),
                'active' => 1,
            ]);
        }

        // Point the email module at the mock IMAP mailbox
        Config::setConfigValueOf('defaultImapEmail', 'default');   // account key in config/imap.php
        Config::setConfigValueOf('daysToCheckEmailBack', 3650);
        Config::setConfigValueOf('ipiresiasName', 'Δημοτικό Σχολείο Δοκιμών');

        // Registration closed, as in a real installation after the first admin exists
        @file_put_contents(storage_path('conf/.denyregister'), 'dev');
    }
}
