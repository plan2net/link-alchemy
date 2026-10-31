<?php

declare(strict_types=1);

$now = time();

$page = static fn(int $uid, int $pid, string $title, string $slug, bool $root = false): array => [
    'uid' => $uid,
    'pid' => $pid,
    'tstamp' => $now,
    'crdate' => $now,
    'title' => $title,
    'slug' => $slug,
    'doktype' => 1,
    'is_siteroot' => (int)$root,
    'perms_userid' => 1,
    'perms_groupid' => 0,
    'perms_user' => 31,
    'perms_group' => 27,
    'perms_everybody' => 0,
];

$element = static fn(int $uid, string $header, string $bodytext = ''): array => [
    'uid' => $uid,
    'pid' => 1,
    'tstamp' => $now,
    'crdate' => $now,
    'sorting' => $uid * 256,
    'CType' => 'text',
    'colPos' => 0,
    'header' => $header,
    'bodytext' => $bodytext,
];

return [
    'pages' => [
        $page(1, 0, 'Home', '/', true),
        $page(2, 1, 'About', '/about'),
    ],
    'tt_content' => [
        $element(1, 'Header link'),
        $element(2, 'Rich text', '<p><a href="https://link-alchemy.example/about">About us</a></p>'),
    ],
    'sys_file_storage' => [[
        'uid' => 1,
        'pid' => 0,
        'tstamp' => $now,
        'crdate' => $now,
        'name' => 'fileadmin',
        'driver' => 'Local',
        'configuration' => '<?xml version="1.0" encoding="utf-8" standalone="yes" ?>'
            . '<T3FlexForms><data><sheet index="sDEF"><language index="lDEF">'
            . '<field index="basePath"><value index="vDEF">fileadmin/</value></field>'
            . '<field index="pathType"><value index="vDEF">relative</value></field>'
            . '</language></sheet></data></T3FlexForms>',
        'is_default' => 1,
        'is_browsable' => 1,
        'is_public' => 1,
        'is_writable' => 1,
        'is_online' => 1,
    ]],
];
