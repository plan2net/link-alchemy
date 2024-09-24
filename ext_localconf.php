<?php

defined('TYPO3') || exit('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SYS']['Objects'][\TYPO3\CMS\Core\Html\RteHtmlParser::class] = [
    'className' => \Plan2net\LinkAlchemy\Xclass\RteHtmlParser::class
];
