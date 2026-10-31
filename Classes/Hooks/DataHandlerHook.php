<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Hooks;

use Plan2net\LinkAlchemy\ChangeNotifier;
use Plan2net\LinkAlchemy\LinkResolver;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\MathUtility;

class DataHandlerHook
{
    public function __construct(
        protected readonly LinkResolver $linkResolver,
        protected readonly ChangeNotifier $changeNotifier,
        protected readonly SiteFinder $siteFinder,
    ) {
    }

    /**
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
            if (!$this->fieldShouldBeProcessed($table, $fieldName, $fieldValue)) {
                continue;
            }

            $url = explode(' ', trim($fieldValue))[0];
            if (str_starts_with($url, '/')) {
                $url = rtrim($this->getSiteBaseUrl($table, $id) ?? '', '/') . $url;
            }

            $link = $this->linkResolver->resolve($url);
            if ($link !== null) {
                $fieldArray[$fieldName] = $link->uri;
                $this->changeNotifier->notify($url, $link);
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

    protected function getSiteBaseUrl(string $table, int|string $uid): ?string
    {
        if (!MathUtility::canBeInterpretedAsInteger($uid)) {
            return null;
        }

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
