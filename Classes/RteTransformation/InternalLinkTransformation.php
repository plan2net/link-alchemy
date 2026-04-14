<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\RteTransformation;

use Plan2net\LinkAlchemy\Service\UrlParser;
use Plan2net\LinkAlchemy\Xclass\RteHtmlParser;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class InternalLinkTransformation
{
    private UrlParser $urlParser;

    public function __construct()
    {
        $this->urlParser = GeneralUtility::makeInstance(UrlParser::class);
    }

    /**
     * Transform an external URL that links to a page into an internal link of the form t3://page.
     */
    public function transform(string $value, RteHtmlParser $parser): string
    {
        $contentBlocks = $parser->splitIntoBlock('A', $value);

        foreach ($contentBlocks as $index => $anchorTag) {
            // Every second array element is a link
            if (!((int)$index % 2)) {
                continue;
            }

            [$tagAttributes] = $parser->get_tag_attributes($parser->getFirstTag($anchorTag), true);
            if (!isset($tagAttributes['href']) || !$this->hasProtocol($tagAttributes['href'])) {
                continue;
            }

            $parsedUri = $this->urlParser->parse($tagAttributes['href']);
            if ($parsedUri !== null) {
                $tagAttributes['href'] = $parsedUri;
                $contentBlocks[$index] = $this->generateAttribute($tagAttributes, $parser, $anchorTag);
            }
        }

        return implode('', $contentBlocks);
    }

    private function generateAttribute(array $tagAttributes, RteHtmlParser $parser, string $contentBlock): string
    {
        return '<a ' . GeneralUtility::implodeAttributes($tagAttributes, true) . '>'
            . $parser->TS_links_db($parser->removeFirstAndLastTag($contentBlock)) . '</a>';
    }

    private function hasProtocol(string $href): bool
    {
        return (bool)preg_match('|^[a-z]+://|', $href);
    }
}
