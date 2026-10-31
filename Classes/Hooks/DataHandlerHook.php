<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Hooks;

use Plan2net\LinkAlchemy\ChangeNotifier;
use Plan2net\LinkAlchemy\LinkResolver;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\MathUtility;

final class DataHandlerHook
{
    public function __construct(
        private readonly LinkResolver $linkResolver,
        private readonly ChangeNotifier $changeNotifier,
        private readonly SiteFinder $siteFinder,
        private readonly TypoLinkCodecService $typoLinkCodecService,
    ) {
    }

    /**
     * @psalm-suppress UnusedParam
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        foreach ($fieldArray as $field => $value) {
            if (!$this->isLinkField($table, $field) || !is_string($value)) {
                continue;
            }

            $typoLink = $this->typoLinkCodecService->decode($value);
            $url = $this->absoluteUrl($typoLink['url'], $table, $id, $fieldArray);
            if ($url === null) {
                continue;
            }

            $link = $this->linkResolver->resolve($url);
            if ($link === null) {
                continue;
            }

            $fieldArray[$field] = $this->typoLinkCodecService->encode(['url' => $link->uri] + $typoLink);
            $this->changeNotifier->notify($url, $link);
        }
    }

    private function isLinkField(string $table, string $field): bool
    {
        return ($GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] ?? '') === 'link';
    }

    private function absoluteUrl(string $url, string $table, int|string $id, array $fieldArray): ?string
    {
        if (str_starts_with($url, 'http')) {
            return $url;
        }

        if (!str_starts_with($url, '/')) {
            return null;
        }

        $baseUrl = $this->siteBaseUrl($this->pageId($table, $id, $fieldArray));

        return $baseUrl === null ? null : rtrim($baseUrl, '/') . $url;
    }

    private function pageId(string $table, int|string $id, array $fieldArray): int
    {
        // A new record has no uid yet, but DataHandler has already resolved its pid
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            return (int)($fieldArray['pid'] ?? 0);
        }

        if ($table === 'pages') {
            return (int)$id;
        }

        return (int)(BackendUtility::getRecord($table, $id, 'pid')['pid'] ?? 0);
    }

    private function siteBaseUrl(int $pageId): ?string
    {
        if ($pageId === 0) {
            return null;
        }

        try {
            return (string)$this->siteFinder->getSiteByPageId($pageId)->getBase();
        } catch (SiteNotFoundException) {
            return null;
        }
    }
}
