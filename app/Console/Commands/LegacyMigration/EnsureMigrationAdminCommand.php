<?php

namespace App\Console\Commands\LegacyMigration;

use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

class EnsureMigrationAdminCommand extends Command
{
    protected $signature = 'legacy:migration:admin
        {username : Admin username}
        {--name= : Display name}
        {--email= : Email address}';

    protected $description = 'Create or recover a local or production admin account';

    public function handle(): int
    {
        $password = (string) env('LEGACY_ADMIN_PASSWORD', '');

        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('Admin password');
        }

        if (strlen($password) < 12) {
            $this->error('Set LEGACY_ADMIN_PASSWORD to a unique password of at least 12 characters. Do not pass it as a command argument.');

            return self::FAILURE;
        }

        $role = Role::query()->where('slug', 'admin')->first();

        if (! $role) {
            $this->error('The admin role does not exist. Seed roles before creating this account.');

            return self::FAILURE;
        }

        $username = (string) $this->argument('username');
        $user = User::query()->where('username', $username)->first() ?? new User;
        $user->fill([
            'name' => (string) ($this->option('name') ?: $user->name ?: $username),
            'username' => $username,
            'email' => $this->option('email') ?: $user->email,
            'password' => $password,
            'role_id' => $role->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $user->save();

        $this->info('Admin account '.$username.' is ready.');

        return self::SUCCESS;
    }
}
