<?php

declare(strict_types=1);

$named = $_SERVER['HTTP_X_LINK_ALCHEMY_DATABASE'] ?? getenv('LINK_ALCHEMY_DATABASE');
$itsDatabase = is_string($named) && 1 === preg_match('/^\d+$/', $named)
    ? 'typo3-' . $named . '.sqlite'
    : 'seed.sqlite';

// Absolute, because a relative path depends on the request's directory
$GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['path']
    = TYPO3\CMS\Core\Core\Environment::getProjectPath() . '/var/e2e/' . $itsDatabase;
