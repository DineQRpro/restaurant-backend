<?php

return [

    /*
    | Shared development password for imported users. It is hashed before
    | storage and is refused outside the local and testing environments.
    */
    'dev_password' => env('LEGACY_MIGRATION_DEV_PASSWORD', 'test123'),

    'batch_size' => (int) env('LEGACY_MIGRATION_BATCH_SIZE', 100),

    /*
    | Test-only switch. It is not read from the environment, so a production
    | .env cannot turn a real refresh into a simulated failure.
    */
    'simulate_failure_after_import' => false,

];
