<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Hooks;

use Plan2net\LinkAlchemy\Service\UrlParser;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{
    protected UrlParser $urlParser;
    protected SiteFinder $siteFinder;

    public function __construct()
    {
        $this->urlParser = GeneralUtility::makeInstance(UrlParser::class);
        $this->siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
    }

    /**
     * Fill path_segment/slug field with title.
     *
     * @param string     $status
     * @param string     $table
     * @param string|int $id
     *
     * @psalm-suppress PossiblyUnusedParam
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function processDatamap_postProcessFieldArray($status, $table, $id, array &$fieldArray, DataHandler $parentObject): void
    {
        foreach ($fieldArray as $fieldName => $fieldValue) {
            if ($this->fieldShouldBeProcessed($table, $fieldName, $fieldValue)) {
                if (str_starts_with(trim($fieldValue), '/')) {
                    $baseUrl = $this->getSiteBaseUrl($table, $id);
                }

                $parsedUri = $this->urlParser->parse($fieldValue, $baseUrl ?? null);
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

        if (($GLOBALS['TCA'][$tableName]['columns'][$fieldName]['config']['type'] ?? '') === 'link') {
            $fieldValue = trim($fieldValue);
            if (str_starts_with($fieldValue, 'http') || str_starts_with($fieldValue, '/')) {
                return true;
            }
        }

        return false;
    }

    protected function getSiteBaseUrl(string $table, int $uid): ?string
    {
        if ($table === 'pages') {
            $pid = $uid;
        } else {
            $record = BackendUtility::getRecord($table, $uid, 'pid');
            if (isset($record['pid'])) {
                $pid = $record['pid'];
            } else {
                return null;
            }
        }

        try {
            $site = $this->siteFinder->getSiteByPageId($pid);

            return (string)$site->getBase();
        } catch (SiteNotFoundException) {
            return null;
        }
    }
}
