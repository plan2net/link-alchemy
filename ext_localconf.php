<?php

use Plan2net\LinkAlchemy\Hooks\DataHandlerHook;

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['link-alchemy'] =
    DataHandlerHook::class;
