<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Env;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $owner = [
            'email' => Env::get('GYMCONTROL_BOOTSTRAP_OWNER_EMAIL'),
            'password' => Env::get('GYMCONTROL_BOOTSTRAP_OWNER_PASSWORD'),
        ];
        $reception = [
            'email' => Env::get('GYMCONTROL_BOOTSTRAP_RECEPTION_EMAIL'),
            'password' => Env::get('GYMCONTROL_BOOTSTRAP_RECEPTION_PASSWORD'),
        ];

        foreach ([$owner, $reception] as $operator) {
            $email = $operator['email'] ?? null;
            $password = $operator['password'] ?? null;

            if (
                ! is_string($email)
                || filter_var($email, FILTER_VALIDATE_EMAIL) === false
                || ! is_string($password)
                || strlen($password) < 16
            ) {
                throw new RuntimeException(
                    'Bootstrap operator credentials must be configured with valid emails and passwords of at least 16 characters.'
                );
            }
        }

        if (strtolower($owner['email']) === strtolower($reception['email'])) {
            throw new RuntimeException('Bootstrap operator email addresses must be different.');
        }

        foreach ([
            ['name' => 'Dueña', 'role' => 'admin', ...$owner],
            ['name' => 'Recepción', 'role' => 'recepcionista', ...$reception],
        ] as $operator) {
            User::firstOrCreate(
                ['email' => $operator['email']],
                [
                    'name' => $operator['name'],
                    'password' => $operator['password'],
                    'rol' => $operator['role'],
                ]
            );
        }
    }
}
