<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Hooks;

use Plan2net\LinkAlchemy\Service\UrlParser;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{
    protected UrlParser $urlParser;

    public function __construct()
    {
        $this->urlParser = GeneralUtility::makeInstance(UrlParser::class);
    }

    /**
     * Fill path_segment/slug field with title.
     *
     * @param string     $status
     * @param string     $table
     * @param string|int $id
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function processDatamap_postProcessFieldArray($status, $table, $id, array &$fieldArray, DataHandler $parentObject): void
    {
        foreach ($fieldArray as $fieldName => $fieldValue) {
            if ($this->fieldShouldBeProcessed($table, $fieldName, $fieldValue)) {
                $parsedUri = $this->urlParser->parse($fieldValue);
                if ($parsedUri !== null) {
                    $fieldArray[$fieldName] = $parsedUri;
                }
            }
        }
    }

    protected function fieldShouldBeProcessed(string $tableName, string $fieldName, $fieldValue): bool
    {
        if (empty($fieldValue) || !is_string($fieldValue)) {
            return false;
        }

        if (!isset($GLOBALS['TCA'][$tableName])) {
            return false;
        }
        if (!isset($GLOBALS['TCA'][$tableName]['columns'][$fieldName])) {
            return false;
        }

        if (($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['type'] ?? '') === 'link'
            && (str_starts_with($fieldValue, 'http') || str_starts_with($fieldValue, '/'))
        ) {
            return true;
        }

        return false;
    }
}
