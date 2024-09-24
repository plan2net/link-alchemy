<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\RteTransformation;

use Plan2net\LinkAlchemy\Xclass\RteHtmlParser;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class InternalLinkTransformation implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private readonly ResourceFactory $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    }

    /**
     * Transform an external URL that links to a page into an internal link of the form t3://page
     */
    public function transform(string $value, RteHtmlParser $parser): string
    {
        $contentBlocks = $parser->splitIntoBlock('A', $value);

        foreach ($contentBlocks as $index => $anchorTag) {
            // Every second array element is a link
            if (!((int) $index % 2)) {
                continue;
            }

            [$tagAttributes] = $parser->get_tag_attributes($parser->getFirstTag($anchorTag), true);
            if (!isset($tagAttributes['href']) || !$this->hasProtocol($tagAttributes['href'])) {
                continue;
            }

            $fakeHttpRequest = $this->getFakeHttpRequest($tagAttributes);
            if (!$fakeHttpRequest instanceof ServerRequest) {
                continue;
            }

            /** @psalm-suppress UndefinedInterfaceMethod */
            $siteRouteResult = $this->getMatchingSiteRouteResult($fakeHttpRequest);
            if (!$siteRouteResult instanceof SiteRouteResult) {
                continue;
            }

            $site = $siteRouteResult->getSite();
            if (!$site instanceof Site) {
                continue;
            }

            try {
                $pageAttribute = $this->getPageAttribute(
                    $site,
                    $fakeHttpRequest,
                    $siteRouteResult,
                    $tagAttributes['href'],
                    $parser,
                    $anchorTag
                );
                if (null !== $pageAttribute) {
                    $contentBlocks[$index] = $pageAttribute;
                }
            } catch (RouteNotFoundException $e) {
                /** @psalm-suppress InternalMethod */
                if (!$this->fileExists($fakeHttpRequest->getUri()->getPath())) {
                    /** @psalm-suppress InternalMethod */
                    $this->logger->warning($e->getMessage(), [$fakeHttpRequest->getUri()->getPath()]);
                }

                /** @psalm-suppress InternalMethod */
                $fileResourceAttribute = $this->getFileResourceAttribute(
                    $fakeHttpRequest->getUri()->getPath(),
                    $tagAttributes['href'],
                    $parser,
                    $anchorTag
                );

                if (null !== $fileResourceAttribute) {
                    $contentBlocks[$index] = $fileResourceAttribute;
                }
            }
        }

        return implode('', $contentBlocks);
    }

    private function buildPageUrl(SiteRouteResult $routeResult, PageArguments $pageResult): string
    {
        $language = $routeResult->getLanguage()->getLanguageId();
        $language = (0 !== $language ? 'L=' . $language : '');
        $query = $routeResult->getUri()->getQuery();
        $arguments = $pageResult->getArguments();
        $linkInformation = [
            'type' => LinkService::TYPE_PAGE,
            'pageuid' => $pageResult->getPageId(),
            'parameters' => $language
                . ($language && $query ? '&' : '') . $query
                . (($language || $query) && $arguments ? '&' : '') . http_build_query($arguments),
            'fragment' => $routeResult->getUri()->getFragment(),
        ];
        if (!empty($pageResult->getPageType())) {
            $linkInformation['pagetype'] = $pageResult->getPageType();
        }

        return GeneralUtility::makeInstance(LinkService::class)->asString($linkInformation);
    }

    private function generateAttribute(array $tagAttributes, RteHtmlParser $parser, string $contentBlock): string
    {
        return '<a ' . GeneralUtility::implodeAttributes($tagAttributes, true) . '>'
            . $parser->TS_links_db($parser->removeFirstAndLastTag($contentBlock)) . '</a>';
    }

    private function informUserOfChange(string $url, int $id, string $type): void
    {
        $pageRecord = BackendUtility::getRecord('pages', $id, 'title');

        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(FlashMessage::class,
            LocalizationUtility::translate('externalLinkChanged', 'link_alchemy', [$url, $type, $pageRecord['title'], $id]),
            '',
            ContextualFeedbackSeverity::INFO,
            true
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

    private function getFakeHttpRequest(array $tagAttributes): ?ServerRequest
    {
        $fakeHttpRequest = null;
        try {
            // We need a stream and use the memory stream as a placeholder
            /** @psalm-suppress InternalClass, InternalMethod */
            $fakeHttpRequest = new ServerRequest($tagAttributes['href'], 'GET', 'php://memory');
        } catch (\Throwable $e) {
            // Probably an unsupported protocol (eg t3: or mailto:) or a broken URL
            $this->logger->warning($e->getMessage(), [$tagAttributes]);
        }

        return $fakeHttpRequest;
    }

    private function getMatchingSiteRouteResult(ServerRequest $fakeHttpRequest): ?SiteRouteResult
    {
        /** @var SiteMatcher $matcher */
        $matcher = GeneralUtility::makeInstance(SiteMatcher::class);
        /** @var SiteRouteResult $siteRouteResult */
        /** @psalm-suppress InternalMethod */
        $siteRouteResult = $matcher->matchRequest($fakeHttpRequest);
        /** @psalm-suppress UndefinedInterfaceMethod */
        $site = $siteRouteResult->getSite();
        // Return no result for a NullSite (external URL)
        if ($site instanceof NullSite) {
            return null;
        }

        return $siteRouteResult;
    }

    /**
     * @throws RouteNotFoundException
     */
    private function getPageAttribute(
        Site $site,
        ServerRequest $fakeHttpRequest,
        SiteRouteResult $siteRouteResult,
        string $href,
        RteHtmlParser $parser,
        string $anchorTag
    ): ?string {
        /** @var PageRouter $pageRouter */
        $pageRouter = GeneralUtility::makeInstance(PageRouter::class, $site);
        /** @var PageArguments $pageRouteResult */
        $pageRouteResult = $pageRouter->matchRequest($fakeHttpRequest, $siteRouteResult);

        $this->informUserOfChange($href, $pageRouteResult->getPageId(),
            LinkService::TYPE_PAGE);

        $tagAttributes['href'] = $this->buildPageUrl($siteRouteResult, $pageRouteResult);

        return $this->generateAttribute($tagAttributes, $parser, $anchorTag);
    }

    private function getFileResourceAttribute(
        string $pathToResource,
        string $href,
        RteHtmlParser $parser,
        string $anchorTag
    ): ?string {
        try {
            $fileResource = $this->resourceFactory->getFileObjectFromCombinedIdentifier($pathToResource);
            $this->informUserOfChange($href, $fileResource->getUid(),
                LinkService::TYPE_FILE);
            $tagAttributes['href'] = GeneralUtility::makeInstance(LinkService::class)->asString([
                'type' => LinkService::TYPE_FILE,
                'file' => $fileResource
            ]);

            return $this->generateAttribute($tagAttributes, $parser, $anchorTag);
        } catch (\InvalidArgumentException $e) {
            // File exists, but doesn't have identifier.
            /** @psalm-suppress InternalMethod */
            $this->logger->warning($e->getMessage(), [$pathToResource]);

            return null;
        }
    }

    private function fileExists(string $pathToResource): bool
    {
        /** @psalm-suppress InternalMethod */
        return file_exists(Environment::getPublicPath() . $pathToResource);
    }

    private function hasProtocol(string $href): bool
    {
        return (bool) preg_match('|^[a-z]+://|', $href);
    }
}
