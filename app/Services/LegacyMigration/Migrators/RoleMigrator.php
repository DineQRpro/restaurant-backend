<?php

namespace App\Services\LegacyMigration\Migrators;

use App\Models\LegacyMigrationMapping;
use App\Models\Role;
use App\Services\LegacyMigration\MigrationContext;

class RoleMigrator extends Migrator
{
    public function key(): string
    {
        return 'roles';
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
        return 'roles';
    }

    public function assign(MigrationContext $context): void
    {
        $this->migrate($context);
    }

    public function migrate(MigrationContext $context, ?int $maxBatches = null): void
    {
        $definitions = [
            1 => ['action' => 'map', 'slug' => 'admin', 'note' => 'Legacy admin uses the existing admin role.'],
            2 => ['action' => 'map', 'slug' => 'waiter', 'note' => 'Legacy kitchen uses the existing waiter role.'],
            3 => ['action' => 'map', 'slug' => 'waiter', 'note' => 'Legacy counter uses the existing waiter role.'],
            4 => ['action' => 'map', 'slug' => 'waiter', 'note' => 'Legacy staff uses the existing waiter role.'],
            5 => ['action' => 'skip', 'message' => 'Store users are excluded.', 'note' => 'D3. Store is skipped.'],
        ];

        foreach ($definitions as $code => $definition) {
            if ($definition['action'] === 'map') {
                $role = Role::query()->where('slug', $definition['slug'])->first();

                if (! $role) {
                    $context->roles[$code] = [
                        'action' => 'fail',
                        'message' => 'Laravel role '.$definition['slug'].' does not exist.',
                    ];
                    $this->record($code, 'rejected', $definition['note'].' Destination role is missing.');

                    continue;
                }

                $context->roles[$code] = [
                    'action' => 'map',
                    'role_id' => $role->id,
                ];
                $this->record($code, 'approved', $definition['note']);

                continue;
            }

            $context->roles[$code] = [
                'action' => 'skip',
                'message' => $definition['message'],
            ];
            $this->record($code, 'approved', $definition['note']);
        }
    }

    protected function import(MigrationContext $context, object $row, string $sourceId): void {}

    private function record(int $code, string $state, string $notes): void
    {
        LegacyMigrationMapping::query()->updateOrCreate(
            [
                'source_table' => 'tbl_users.role',
                'destination_table' => 'roles',
                'decision_key' => (string) $code,
            ],
            [
                'state' => $state,
                'notes' => $notes,
                'approved_at' => $state === 'approved' ? now() : null,
            ],
        );
    }
}
