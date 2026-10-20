<?php

namespace App\Services\LegacyMigration;

use App\Models\Floor;

class FloorResolver
{
    public function resolve(MigrationContext $context): void
    {
        $floors = Floor::query()->get();
        $idOne = $floors->firstWhere('id', 1);
        $named = $floors->first(
            fn (Floor $floor): bool => mb_strtolower(trim($floor->name)) === 'ground floor',
        );

        if ($idOne instanceof Floor && mb_strtolower(trim($idOne->name)) === 'ground floor') {
            $context->floorId = 1;
            $context->floorBlocked = false;
            $context->floorMessage = 'Ground Floor id 1 is reused.';

            return;
        }

        if ($named instanceof Floor && (int) $named->id !== 1) {
            $context->floorBlocked = true;
            $context->floorId = null;
            $context->floorMessage = 'Ground Floor exists at id '.$named->id.' instead of id 1.';

            return;
        }

        if ($idOne instanceof Floor) {
            $context->floorBlocked = true;
            $context->floorId = null;
            $context->floorMessage = 'Floor id 1 is "'.$idOne->name.'" and was not changed.';

            return;
        }

        if ($context->persist) {
            $floor = new Floor;
            $floor->id = 1;
            $floor->name = 'Ground Floor';
            $floor->code = 'ground-floor';
            $floor->sort_order = 1;
            $floor->is_active = true;
            $floor->save();
            $context->floorMessage = 'Ground Floor id 1 was created.';
        } else {
            $context->floorMessage = 'Ground Floor id 1 would be created.';
        }

        $context->floorId = 1;
        $context->floorBlocked = false;
    }
}
