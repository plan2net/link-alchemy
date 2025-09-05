<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Xclass;

use Plan2net\LinkAlchemy\RteTransformation\InternalLinkTransformation;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RteHtmlParser extends \TYPO3\CMS\Core\Html\RteHtmlParser
{
    protected InternalLinkTransformation $internalLinkTransformation;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->internalLinkTransformation = GeneralUtility::makeInstance(InternalLinkTransformation::class);
        parent::__construct($eventDispatcher);
    }

    /**
     * Main entry point for transforming RTE content in the database so the Rich Text Editor can deal with
     * e.g. links.
     */
    public function transformTextForRichTextEditor(string $value, array $processingConfiguration): string
    {
        $this->setProcessingConfiguration($processingConfiguration);
        $modes = $this->resolveAppliedTransformationModes('rte');
        $value = $this->streamlineLineBreaksForProcessing($value);
        // If an entry HTML cleaner was configured, pass the content through the HTMLcleaner
        $value = $this->runHtmlParserIfConfigured($value, 'entryHTMLparser_rte');
        // Traverse modes
        foreach ($modes as $cmd) {
            switch ($cmd) {
                case 'detectbrokenlinks':
                    $value = $this->markBrokenLinks($value);
                    break;
                case 'css_transform':
                    $value = $this->TS_transform_rte($value);
                    break;
                default:
                    // Do nothing
            }
        }

        /**
         *  START customization
         */
        $value = $this->internalLinkTransformation->transform($value, $this);
        /**
         *  END customization
         */

        // If an exit HTML cleaner was configured, pass the content through the HTMLcleaner
        $value = $this->runHtmlParserIfConfigured($value, 'exitHTMLparser_rte');
        // Final clean up of linebreaks
        $value = $this->streamlineLineBreaksAfterProcessing($value);

        return $value;
    }

    // phpcs:ignore PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    public function TS_links_db(string $value): string
    {
        return parent::TS_links_db($value);
    }
}
