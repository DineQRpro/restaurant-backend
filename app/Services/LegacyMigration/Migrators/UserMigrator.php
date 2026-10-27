<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\User;
use App\Services\LegacyMigration\MigrationContext;
use App\Services\LegacyMigration\RowRejected;
use App\Services\LegacyMigration\Values;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use RuntimeException;

class UserMigrator extends Migrator
{
    /** @var list<string> */
    public array $activationLines = [];

    /** @var array<int, true> */
    private array $reservedIds = [];

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
        $this->reservedIds = [];
        $this->assertPasswordStrategy();
        parent::migrate($context, $maxBatches);

        if ($context->persist) {
            $this->advanceUserSequence();
        }
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

        $legacyId = (int) $sourceId;
        $warnings = [];
        $name = $this->nameFor($legacyId, $row->fullname ?? null, $warnings);
        $username = $this->usernameFor($context, $legacyId, $row->username ?? null, $warnings);
        [$email, $legacyEmail] = $this->emailFor($context, $legacyId, $row->email ?? null, $warnings);
        [$mobile, $legacyMobile] = $this->mobileFor($context, $row->phone ?? null, $warnings);
        $createdAt = $this->timestampFor($row->created_at ?? null, 'Created at', $warnings);
        $updatedAt = $this->timestampFor($row->updated_at ?? $row->created_at ?? null, 'Updated at', $warnings);
        [$destinationId, $idWarning] = $this->destinationIdFor($context, $legacyId);

        if ($idWarning !== null) {
            $warnings[] = $idWarning;
        }

        foreach ($warnings as $warning) {
            $this->warn($context, $sourceId, $warning);
        }

        $metadata = [
            'legacy_role' => (int) ($row->role ?? 0),
            'legacy_email' => $legacyEmail,
            'legacy_mobile' => $legacyMobile,
            'legacy_username' => Values::nullableText($row->username ?? null),
        ];

        if (! $context->persist) {
            $this->remember($context, $sourceId, null, $metadata);

            return;
        }

        $user = new User([
            'name' => $name,
            'username' => $username,
            'email' => $email,
            'mobile' => $mobile,
            'password' => $this->password(),
            'role_id' => $decision['role_id'],
            'is_active' => (string) ($row->user_status ?? '') === '1',
            'must_change_password' => true,
        ]);
        $user->id = $destinationId;
        $user->timestamps = false;
        $user->created_at = $createdAt;
        $user->updated_at = $updatedAt;
        $user->save();

        if ($this->canReceiveActivation($email) && ! app()->environment(['local', 'testing'])) {
            $token = Password::broker()->createToken($user);
            $this->activationLines[] = $user->username."\t".$token;
        }

        $this->remember($context, $sourceId, $user->id, $metadata);
    }

    /**
     * @param  list<string>  $warnings
     */
    private function nameFor(int $legacyId, mixed $raw, array &$warnings): string
    {
        $name = Values::nullableText($raw);

        if ($name === null) {
            $warnings[] = 'Name was replaced with a migration placeholder.';

            return 'Legacy User '.$legacyId;
        }

        return mb_substr($name, 0, 255);
    }

    /**
     * @param  list<string>  $warnings
     */
    private function usernameFor(MigrationContext $context, int $legacyId, mixed $raw, array &$warnings): string
    {
        $username = Values::nullableText($raw);

        if ($username !== null && $context->claim($context->usernames, $username)) {
            return mb_substr($username, 0, 255);
        }

        $warnings[] = 'Username was replaced with a migration placeholder.';
        $candidate = 'legacy-user-'.$legacyId;
        $suffix = 2;

        while (! $context->claim($context->usernames, $candidate)) {
            $candidate = 'legacy-user-'.$legacyId.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    /**
     * @param  list<string>  $warnings
     * @return array{0: string, 1: ?string}
     */
    private function emailFor(MigrationContext $context, int $legacyId, mixed $raw, array &$warnings): array
    {
        $original = Values::nullableText($raw);
        $valid = $original !== null && filter_var($original, FILTER_VALIDATE_EMAIL) !== false ? $original : null;

        if ($valid !== null && $context->claim($context->emails, $valid)) {
            return [$valid, $original];
        }

        $warnings[] = 'Email was replaced with a migration placeholder.';
        $candidate = 'legacy-user-'.$legacyId.'@invalid.example';
        $suffix = 2;

        while (! $context->claim($context->emails, $candidate)) {
            $candidate = 'legacy-user-'.$legacyId.'-'.$suffix.'@invalid.example';
            $suffix++;
        }

        return [$candidate, $original];
    }

    /**
     * @param  list<string>  $warnings
     * @return array{0: ?string, 1: ?string}
     */
    private function mobileFor(MigrationContext $context, mixed $raw, array &$warnings): array
    {
        $original = Values::nullableText($raw);
        $normalized = $this->normalizeMobile($original);

        if ($normalized !== null && $context->claim($context->mobiles, $normalized)) {
            if ($original !== $normalized) {
                $warnings[] = 'Mobile was normalized.';
            }

            return [$normalized, $original];
        }

        $warnings[] = $normalized === null
            ? 'Mobile was missing or invalid and was left empty.'
            : 'Mobile duplicated another user and was left empty.';

        return [null, $original];
    }

    private function normalizeMobile(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (preg_match('/^\d{10}$/', $digits) === 1) {
            return $digits;
        }

        if (preg_match('/^91(\d{10})$/', $digits, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^0(\d{10})$/', $digits, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param  list<string>  $warnings
     */
    private function timestampFor(mixed $raw, string $label, array &$warnings): ?\Carbon\CarbonInterface
    {
        if (Values::nullableText($raw) === null) {
            return null;
        }

        try {
            return Values::timestamp($raw, $label);
        } catch (RowRejected) {
            $warnings[] = $label.' was missing or invalid and was left empty.';

            return null;
        }
    }

    /**
     * @return array{0: int, 1: ?string}
     */
    private function destinationIdFor(MigrationContext $context, int $legacyId): array
    {
        $occupied = ! $context->freshDestination && (isset($this->reservedIds[$legacyId]) || User::query()->whereKey($legacyId)->exists());

        if (! $occupied) {
            $this->reservedIds[$legacyId] = true;

            return [$legacyId, null];
        }

        $candidate = max($legacyId, (int) User::query()->max('id'), $this->reservedIds === [] ? 0 : max(array_keys($this->reservedIds)));

        do {
            $candidate++;
        } while (isset($this->reservedIds[$candidate]) || User::query()->whereKey($candidate)->exists());

        $this->reservedIds[$candidate] = true;

        return [$candidate, 'Legacy user id is already used, so a new id was assigned.'];
    }

    private function warn(MigrationContext $context, string $sourceId, string $message): void
    {
        $context->run->errors()->create([
            'source_table' => $this->sourceTable(),
            'source_id' => $sourceId,
            'message' => $message,
            'context' => ['migrator' => $this->key()],
            'status' => 'warning',
        ]);
    }

    private function canReceiveActivation(?string $email): bool
    {
        return $email !== null && ! str_ends_with(mb_strtolower($email), '@invalid.example');
    }

    private function advanceUserSequence(): void
    {
        $max = (int) User::query()->max('id');

        if ($max < 1) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $updated = DB::table('sqlite_sequence')->where('name', 'users')->update(['seq' => $max]);

            if ($updated === 0) {
                DB::table('sqlite_sequence')->insert(['name' => 'users', 'seq' => $max]);
            }

            return;
        }

        DB::statement('ALTER TABLE users AUTO_INCREMENT = '.($max + 1));
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
