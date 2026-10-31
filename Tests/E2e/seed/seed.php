<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$instance = __DIR__ . '/../instance';

require $root . '/vendor/autoload.php';

$say = static fn(string $line): int => print $line . PHP_EOL;

@mkdir($root . '/config/system', 0o775, true);
@mkdir($root . '/var/e2e', 0o775, true);
copy($instance . '/settings.php', $root . '/config/system/settings.php');
copy($instance . '/additional.php', $root . '/config/system/additional.php');

$seed = $root . '/var/e2e/seed.sqlite';

// Code cached by another TYPO3 version breaks this one
exec('rm -rf ' . escapeshellarg($root . '/var/cache'));

foreach ([$seed, $seed . '-wal', $seed . '-shm'] as $gone) {
    @unlink($gone);
}

$say('Installing TYPO3');
exec(
    'cd ' . escapeshellarg($root) . ' && TYPO3_CONTEXT=Testing vendor/bin/typo3 setup'
    . ' --driver=sqlite --dbname=' . escapeshellarg($seed)
    . ' --admin-username=admin --admin-user-password=' . escapeshellarg('E2e-' . bin2hex(random_bytes(8)) . '!')
    . ' --admin-email=admin@example.com --project-name="Link alchemy"'
    . ' --server-type=other --force --no-interaction 2>&1',
    $told,
    $failed,
);

if (0 !== $failed) {
    $say(implode(PHP_EOL, $told));

    exit(1);
}

$installed = require $root . '/config/system/settings.php';
$installedDatabase = $installed['DB']['Connections']['Default']['path'];

// Keep the installer's encryption key
$encryptionKey = $installed['SYS']['encryptionKey'];
$settings = require $instance . '/settings.php';
$settings['SYS']['encryptionKey'] = $encryptionKey;
file_put_contents($root . '/config/system/settings.php', "<?php\n\nreturn " . var_export($settings, true) . ";\n");

@mkdir($root . '/config/sites/main', 0o775, true);
copy($instance . '/sites/main/config.yaml', $root . '/config/sites/main/config.yaml');
if ($installedDatabase !== $seed) {
    rename($installedDatabase, $seed);
}

$database = new PDO('sqlite:' . $seed, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$insert = static function (PDO $into, string $table, array $rows) use ($say): void {
    $columns = array_keys($rows[0]);
    $statement = $into->prepare('INSERT INTO "' . $table . '" ('
        . implode(', ', array_map(static fn(string $name): string => '"' . $name . '"', $columns))
        . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')');

    foreach ($rows as $row) {
        $statement->execute(array_values($row));
    }

    $say('  ' . str_pad($table, 18) . count($rows));
};

// TYPO3 14 creates _cli_ first, so admin is moved to uid 1
$say('Filling it');
$database->exec("DELETE FROM be_users WHERE username <> 'admin'");
$database->exec("UPDATE be_users SET uid = 1 WHERE username = 'admin'");
foreach (['pages', 'tt_content', 'sys_file_storage', 'sys_file', 'sys_file_metadata'] as $table) {
    $database->exec('DELETE FROM ' . $table);
}

@mkdir($root . '/public/fileadmin', 0o775, true);
file_put_contents($root . '/public/fileadmin/welcome.txt', 'Hello world');

foreach (require __DIR__ . '/records.php' as $table => $rows) {
    $insert($database, $table, $rows);
}

$database->exec("UPDATE be_users SET uc = '" . serialize([]) . "'");

// A stored backend session, so the tests never see the login form
$GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] = $encryptionKey;
$GLOBALS['EXEC_TIME'] = time();
$plainSessionId = bin2hex(random_bytes(32));

$database->prepare('INSERT INTO be_sessions (ses_id, ses_iplock, ses_userid, ses_tstamp, ses_data) VALUES (?, ?, ?, ?, ?)')
    ->execute([
        (new TYPO3\CMS\Core\Session\Backend\DatabaseSessionBackend())->hash($plainSessionId),
        '[DISABLED]',
        1,
        2147483647,
        serialize([]),
    ]);

file_put_contents(
    $root . '/var/e2e/session.json',
    json_encode([
        'name' => 'be_typo_user',
        'value' => TYPO3\CMS\Core\Session\UserSession::createNonFixated($plainSessionId)->getJwt(),
    ], JSON_PRETTY_PRINT),
);

$say('Seeded ' . $seed);
