<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\User;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class UserMigrator extends Migrator
{
    /** @var list<string> */
    public array $activationLines = [];

    public function key(): string
    {
        return 'users';
    }

    public function sourceTable(): string
    {
        return 'tbl_users';
    }

    public function primaryKey(): string
    {
        return 'user_id';
    }

    public function destinationTable(): string
    {
        return 'users';
    }

    public function migrate(MigrationContext $context, ?int $maxBatches = null): void
    {
        $this->assertPasswordStrategy();
        parent::migrate($context, $maxBatches);
    }

    protected function prepare(MigrationContext $context): void
    {
        if ($context->freshDestination) {
            return;
        }

        foreach (User::query()->get(['username', 'email', 'mobile']) as $user) {
            $context->claim($context->usernames, $user->username);
            $context->claim($context->emails, $user->email);
            $context->claim($context->mobiles, $user->mobile);
        }
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void
    {
        $this->assertPasswordStrategy();

        $code = is_numeric($row->role ?? null) ? (int) $row->role : null;
        $decision = $code === null ? null : ($context->roles[$code] ?? null);

        if ($decision === null) {
            throw RowRejected::failed('Role is not supported.');
        }

        if ($decision['action'] === 'skip') {
            throw RowRejected::skipped($decision['message']);
        }

        if ($decision['action'] !== 'map' || ! isset($decision['role_id'])) {
            throw RowRejected::failed($decision['message'] ?? 'Role is not supported.');
        }

        $status = (string) ($row->user_status ?? '');

        if (! in_array($status, ['1', '2'], true)) {
            throw RowRejected::failed('User status must be 1 or 2.');
        }

        $name = Values::required($row->fullname ?? null, 'Name', 255);
        $username = Values::required($row->username ?? null, 'Username', 255);
        $email = Values::email($row->email ?? null);
        $mobile = Values::phone($row->phone ?? null);
        $createdAt = Values::timestamp($row->created_at ?? null, 'Created at');
        $updatedAt = Values::timestamp($row->updated_at ?? null, 'Updated at');

        $this->claimIdentity($context, $username, $email, $mobile);

        if (! $context->persist) {
            $this->remember($context, $sourceId, null);

            return;
        }

        $password = $this->password();
        $user = new User([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $password,
            'role_id' => $decision['role_id'],
            'is_active' => $status === '1',
            'must_change_password' => true,
        ]);
        $user->created_at = $createdAt;
        $user->updated_at = $updatedAt;
        $user->save();

        if ($email !== null && ! app()->environment(['local', 'testing'])) {
            $token = Password::broker()->createToken($user);
            $this->activationLines[] = $user->username."\t".$token;
        }

        $this->remember($context, $sourceId, $user->id);
    }

    private function claimIdentity(MigrationContext $context, string $username, ?string $email, ?string $mobile): void
    {
        $claimed = [];

        try {
            if (! $context->claim($context->usernames, $username)) {
                throw RowRejected::failed('Username already exists.');
            }
            $claimed[] = ['usernames', $username];

            if (! $context->claim($context->emails, $email)) {
                throw RowRejected::failed('Email already exists.');
            }
            $claimed[] = ['emails', $email];

            if (! $context->claim($context->mobiles, $mobile)) {
                throw RowRejected::failed('Mobile already exists.');
            }
        } catch (RowRejected $rejected) {
            foreach ($claimed as [$bucket, $value]) {
                if ($bucket === 'usernames') {
                    $context->release($context->usernames, $value);
                }
                if ($bucket === 'emails') {
                    $context->release($context->emails, $value);
                }
            }

            throw $rejected;
        }
    }

    private function assertPasswordStrategy(): void
    {
        if (app()->environment(['local', 'testing'])) {
            if ((string) config('legacy-migration.dev_password') === '') {
                throw new RuntimeException('The shared development password is empty.');
            }

            return;
        }

        if (! app()->environment('production')) {
            throw new RuntimeException('Legacy import is refused in this environment.');
        }
    }

    private function password(): string
    {
        if (app()->environment(['local', 'testing'])) {
            return (string) config('legacy-migration.dev_password');
        }

        return Str::password(40);
    }
}
