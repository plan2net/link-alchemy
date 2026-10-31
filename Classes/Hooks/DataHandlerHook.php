<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Hooks;

use Plan2net\LinkAlchemy\ChangeNotifier;
use Plan2net\LinkAlchemy\Compatibility\RichTextField;
use Plan2net\LinkAlchemy\LinkResolver;
use Plan2net\LinkAlchemy\ResolvedLink;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Html\HtmlParser;
use TYPO3\CMS\Core\LinkHandling\TypoLinkCodecService;
use TYPO3\CMS\Core\Schema\Field\TextFieldType;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

final class DataHandlerHook
{
    public function __construct(
        private readonly LinkResolver $linkResolver,
        private readonly ChangeNotifier $changeNotifier,
        private readonly SiteFinder $siteFinder,
        private readonly TypoLinkCodecService $typoLinkCodecService,
        private readonly HtmlParser $htmlParser,
    ) {
    }

    /**
     * Runs before DataHandler drops unchanged fields, so saving old content unchanged still fixes its links.
     *
     * @psalm-suppress UnusedParam
     */
    // phpcs:ignore PSR1.Methods.CamelCapsMethodName
    public function processDatamap_preProcessFieldArray(array &$fieldArray, string $table, int|string $id, DataHandler $dataHandler): void
    {
        foreach ($fieldArray as $field => $value) {
            if (!is_string($value)) {
                continue;
            }

            $fieldType = $GLOBALS['TCA'][$table]['columns'][$field]['config']['type'] ?? '';
            if ($fieldType === 'link') {
                $fieldArray[$field] = $this->rewriteLinkField($value, $table, $id, $fieldArray);
            } elseif ($fieldType === 'text' && str_contains($value, '<a') && $this->isRichTextField($table, $field, $id, $fieldArray)) {
                $fieldArray[$field] = $this->rewriteAnchors($value);
            }
        }
    }

    private function rewriteLinkField(string $value, string $table, int|string $id, array $fieldArray): string
    {
        $typoLink = $this->typoLinkCodecService->decode($value);
        $url = $this->absoluteUrl($typoLink['url'], $table, $id, $fieldArray);
        $link = $url === null ? null : $this->resolveAndNotify($url);

        return $link === null ? $value : $this->typoLinkCodecService->encode(['url' => $link->uri] + $typoLink);
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
        // A new record has no uid yet; a negative pid places it after the record with that uid
        if (!MathUtility::canBeInterpretedAsInteger($id)) {
            $pid = (int)($fieldArray['pid'] ?? 0);

            return $pid >= 0 ? $pid : (int)(BackendUtility::getRecord($table, abs($pid), 'pid')['pid'] ?? 0);
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

    private function resolveAndNotify(string $url): ?ResolvedLink
    {
        $link = $this->linkResolver->resolve($url);
        if ($link !== null) {
            $this->changeNotifier->notify($url, $link);
        }

        return $link;
    }

    private function isRichTextField(string $table, string $field, int|string $id, array $fieldArray): bool
    {
        $record = $fieldArray + (MathUtility::canBeInterpretedAsInteger($id) ? BackendUtility::getRecord($table, $id) ?? [] : []);

        // Delete with 12.4 support, and inject TcaSchemaFactory
        if (!class_exists(TcaSchemaFactory::class)) {
            return RichTextField::is($table, $field, $record);
        }

        $schema = GeneralUtility::makeInstance(TcaSchemaFactory::class)->get($table);
        if ($schema->supportsSubSchema()) {
            $type = (string)($record[$schema->getSubSchemaTypeInformation()->getFieldName()] ?? '');
            $schema = $schema->hasSubSchema($type) ? $schema->getSubSchema($type) : $schema;
        }
        if (!$schema->hasField($field)) {
            return false;
        }
        $fieldType = $schema->getField($field);

        return $fieldType instanceof TextFieldType && $fieldType->isRichText();
    }

    private function rewriteAnchors(string $html): string
    {
        $blocks = $this->htmlParser->splitIntoBlock('a', $html);
        foreach ($blocks as $index => $anchor) {
            // Odd blocks are the anchors, even ones the text between them
            if ((int)$index % 2 === 0) {
                continue;
            }

            [$attributes] = $this->htmlParser->get_tag_attributes($this->htmlParser->getFirstTag($anchor), true);
            if (!preg_match('#^https?://#', $attributes['href'] ?? '')) {
                continue;
            }

            $link = $this->resolveAndNotify($attributes['href']);
            if ($link === null) {
                continue;
            }

            $attributes['href'] = $link->uri;
            $blocks[$index] = '<a ' . GeneralUtility::implodeAttributes($attributes, true) . '>'
                . $this->htmlParser->removeFirstAndLastTag($anchor) . '</a>';
        }

        return implode('', $blocks);
    }
}
