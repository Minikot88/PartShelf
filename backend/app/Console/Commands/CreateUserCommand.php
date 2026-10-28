<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class CreateUserCommand extends Command
{
    protected $signature = 'partshelf:user:create {name?} {email?} {role?}';

    protected $description = 'Create a PartShelf user with a hidden, confirmed password prompt';

    public function handle(): int
    {
        $name = trim((string) ($this->argument('name') ?? $this->ask('Name')));
        $email = mb_strtolower(trim((string) ($this->argument('email') ?? $this->ask('Email'))));
        $role = strtolower(trim((string) ($this->argument('role') ?? $this->choice('Role', UserRole::values(), 'staff'))));

        $validator = Validator::make(
            compact('name', 'email', 'role'),
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:254', 'unique:users,email'],
                'role' => ['required', Rule::in(UserRole::values())],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $password = $this->secret('Password (12+ characters, mixed case and a number)');
        $passwordValidator = Validator::make(
            ['password' => $password],
            ['password' => ['required', 'string', Password::min(12)->letters()->mixedCase()->numbers()]],
        );

        if ($passwordValidator->fails()) {
            foreach ($passwordValidator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        if (strlen((string) $password) > 72) {
            $this->components->error('Password must not exceed 72 bytes.');

            return self::FAILURE;
        }

        if (! hash_equals((string) $password, (string) $this->secret('Confirm password'))) {
            $this->components->error('Passwords do not match.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($name, $email, $role, $password): void {
            $user = User::create(compact('name', 'email') + ['password' => $password]);
            $user->role = UserRole::from($role);
            $user->save();

            DB::table('audit_logs')->insert([
                'user_id' => null,
                'action' => 'user.created',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'after_data' => json_encode([
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                ], JSON_THROW_ON_ERROR),
                'reason' => 'Provisioned through local CLI.',
                'created_at' => now(),
            ]);
        });

        $this->components->info("Created {$role} user {$email}.");

        return self::SUCCESS;
    }
}
