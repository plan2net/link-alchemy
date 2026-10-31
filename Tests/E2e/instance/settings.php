<?php

declare(strict_types=1);

return [
    'BE' => [
        'debug' => true,
        'sessionTimeout' => 31536000,
    ],
    'DB' => [
        'Connections' => [
            'Default' => [
                'driver' => 'pdo_sqlite',
                'path' => 'var/e2e/seed.sqlite',
            ],
        ],
    ],
    'GFX' => [
        'processor_enabled' => false,
    ],
    'SYS' => [
        'devIPmask' => '*',
        'displayErrors' => 1,
        'features' => [
            'security.backend.enforceReferrer' => false,
        ],
        'sitename' => 'Link alchemy',
        'systemMaintainers' => [1],
        'trustedHostsPattern' => '.*',
    ],
];
