<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Link-Alchemy',
    'description' => 'Rewrite absolute URLs that point to internal pages/files, converting them to internal TYPO3 URLs',
    'category' => 'be',
    'author' => 'Wolfgang Klinger',
    'author_email' => 'office@plan2.net',
    'author_company' => 'plan2net GmbH',
    'state' => 'stable',
    'version' => '14.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '12.4.0-14.4.99',
            'php' => '8.1.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
