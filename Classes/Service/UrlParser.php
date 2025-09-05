<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Service;

use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class UrlParser implements SingletonInterface
{
    public function parse(string $uri): ?string
    {
        $fakeHttpRequest = $this->getFakeHttpRequest($uri);
        if (!$fakeHttpRequest instanceof ServerRequest) {
            return null;
        }

        /** @psalm-suppress UndefinedInterfaceMethod */
        $siteRouteResult = $this->getMatchingSiteRouteResult($fakeHttpRequest);
        if (!$siteRouteResult instanceof SiteRouteResult) {
            return null;
        }

        $site = $siteRouteResult->getSite();
        if (!$site instanceof Site) {
            return null;
        }

        try {
            $pageUri = $this->getPageUri($site, $fakeHttpRequest, $siteRouteResult,
                $uri);
            if (null !== $pageUri) {
                return $pageUri;
            }
        } catch (RouteNotFoundException $e) {
        }

        return null;
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
        // don't add page type 0 as we don't want type=0 in the URL
        if ('' !== $pageResult->getPageType() && '0' !== $pageResult->getPageType()) {
            $linkInformation['pagetype'] = $pageResult->getPageType();
        }

        return GeneralUtility::makeInstance(LinkService::class)->asString($linkInformation);
    }

    private function informUserOfChange(string $url, int $id, string $type): void
    {
        $pageRecord = BackendUtility::getRecord('pages', $id, 'title');

        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(FlashMessage::class,
            LocalizationUtility::translate('externalLinkChanged', 'uri2link',
                [$url, $type, $pageRecord['title'], $id]),
            '',
            ContextualFeedbackSeverity::INFO,
            true
        );

        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $messageQueue = $flashMessageService->getMessageQueueByIdentifier();
        $messageQueue->addMessage($message);
    }

    private function getFakeHttpRequest(string $fieldValue): ?ServerRequest
    {
        $fakeHttpRequest = null;
        try {
            // We need a stream and use the memory stream as a placeholder
            /** @psalm-suppress InternalClass, InternalMethod */
            $fakeHttpRequest = new ServerRequest($fieldValue, 'GET', 'php://memory');
        } catch (\Throwable $e) {
            // Probably an unsupported protocol (eg t3: or mailto:) or a broken URL
            $this->logger->warning($e->getMessage(), [$fieldValue]);
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
        // Return no result for a NullSite (external URLs)
        if ('#NULL' !== $site->getIdentifier()) {
            return $siteRouteResult;
        }

        return null;
    }

    /**
     * @throws RouteNotFoundException
     */
    private function getPageUri(
        Site $site,
        ServerRequest $fakeHttpRequest,
        SiteRouteResult $siteRouteResult,
        string $fieldValue,
    ): ?string {
        /** @var PageRouter $pageRouter */
        $pageRouter = GeneralUtility::makeInstance(PageRouter::class, $site);
        /** @var PageArguments $pageRouteResult */
        $pageRouteResult = $pageRouter->matchRequest($fakeHttpRequest, $siteRouteResult);

        $this->informUserOfChange($fieldValue, $pageRouteResult->getPageId(),
            LinkService::TYPE_PAGE);

        return $this->buildPageUrl($siteRouteResult, $pageRouteResult);
    }
}
