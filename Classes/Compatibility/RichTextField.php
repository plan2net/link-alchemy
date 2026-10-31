<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Compatibility;

// TYPO3 12.4 has no TcaSchema. Delete this file with 12.4 support.
final class RichTextField
{
    public static function is(string $table, string $field, array $record): bool
    {
        $type = (string)($record[$GLOBALS['TCA'][$table]['ctrl']['type'] ?? ''] ?? '');
        $config = array_replace(
            $GLOBALS['TCA'][$table]['columns'][$field]['config'] ?? [],
            $GLOBALS['TCA'][$table]['types'][$type]['columnsOverrides'][$field]['config'] ?? [],
        );

        return ($config['type'] ?? '') === 'text' && (bool)($config['enableRichtext'] ?? false);
    }
}
