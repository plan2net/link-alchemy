<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Service;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\PageRouter;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Site\Entity\NullSite;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class UrlParser implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    private readonly ResourceFactory $resourceFactory;

    public function __construct()
    {
        $this->resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
    }

    public function parse(string $uri, ?string $baseUrl = null): ?string
    {
        $uri = trim($uri);

        $splitUri = explode(' ', $uri);
        if (count($splitUri) > 1) {
            $uri = $splitUri[0];
        }

        if ($baseUrl !== null) {
            $baseUrl = rtrim($baseUrl, '/');
            $uri = "{$baseUrl}{$uri}";
        }

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
            $pageUri = $this->getPageUri(
                $site,
                $fakeHttpRequest,
                $siteRouteResult,
                $uri
            );
            if ($pageUri !== null) {
                return $pageUri;
            }
        } catch (RouteNotFoundException|UnknownLinkHandlerException $e) {
            /** @psalm-suppress InternalMethod */
            $pathToResource = $fakeHttpRequest->getUri()->getPath();

            /** @psalm-suppress InternalMethod */
            if ($this->fileExists($pathToResource)) {
                /** @psalm-suppress InternalMethod */
                $fileResourceUri = $this->getFileResourceUri(
                    $pathToResource,
                    $uri,
                );

                if ($fileResourceUri !== null) {
                    return $fileResourceUri;
                }
            } elseif ($this->folderExists($pathToResource)) {
                $folderResourceUri = $this->getFolderResourceUri(
                    $pathToResource,
                    $uri,
                );

                if ($folderResourceUri !== null) {
                    return $folderResourceUri;
                }
            } else {
                /** @psalm-suppress InternalMethod */
                $this->logger->warning($e->getMessage(), [$fakeHttpRequest->getUri()->getPath()]);
            }
        }

        return null;
    }

    /**
     * @throws UnknownLinkHandlerException
     */
    private function buildPageUrl(SiteRouteResult $routeResult, PageArguments $pageResult): string
    {
        $language = $routeResult->getLanguage()->getLanguageId();
        $language = ($language !== 0 ? 'L=' . $language : '');
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

        /** @var LinkService $linkService */
        $linkService = GeneralUtility::makeInstance(LinkService::class);

        return $linkService->asString($linkInformation);
    }

    private function informUserOfChange(string $url, int $id, string $type, string $internalResourceName): void
    {
        $messageTranslationKey = match ($type) {
            LinkService::TYPE_PAGE => 'externalPageLinkChanged',
            LinkService::TYPE_FILE => 'externalFileLinkChanged',
            LinkService::TYPE_FOLDER => 'externalFolderLinkChanged',
        };

        /** @var FlashMessage $message */
        $message = GeneralUtility::makeInstance(FlashMessage::class,
            LocalizationUtility::translate(
                $messageTranslationKey,
                'link_alchemy',
                [$url, $internalResourceName, $id]
            ),
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

        // Return no result for a NullSite (external URL)
        if ($site instanceof NullSite) {
            return null;
        }

        return $siteRouteResult;
    }

    /**
     * @throws RouteNotFoundException|UnknownLinkHandlerException
     */
    private function getPageUri(
        Site $site,
        ServerRequest $fakeHttpRequest,
        SiteRouteResult $siteRouteResult,
        string $url,
    ): ?string {
        /** @var PageRouter $pageRouter */
        $pageRouter = GeneralUtility::makeInstance(PageRouter::class, $site);
        /** @var PageArguments $pageRouteResult */
        $pageRouteResult = $pageRouter->matchRequest($fakeHttpRequest, $siteRouteResult);

        $pageRecord = BackendUtility::getRecord('pages', $pageRouteResult->getPageId(), 'title');

        $this->informUserOfChange(
            $url,
            $pageRouteResult->getPageId(),
            LinkService::TYPE_PAGE,
            $pageRecord['title'],
        );

        return $this->buildPageUrl($siteRouteResult, $pageRouteResult);
    }

    private function getFileResourceUri(
        string $pathToResource,
        string $url,
    ): ?string {
        try {
            $fileResource = $this->resourceFactory->getFileObjectFromCombinedIdentifier($pathToResource);

            if ($fileResource === null) {
                return null;
            }

            $this->informUserOfChange(
                $url,
                $fileResource->getUid(),
                LinkService::TYPE_FILE,
                $fileResource->getName()
            );

            return GeneralUtility::makeInstance(LinkService::class)->asString([
                'type' => LinkService::TYPE_FILE,
                'file' => $fileResource
            ]);
        } catch (\InvalidArgumentException $e) {
            // File exists, but doesn't have identifier.
            /** @psalm-suppress InternalMethod */
            $this->logger->warning($e->getMessage(), [$pathToResource]);

            return null;
        }
    }

    private function getFolderResourceUri(
        string $pathToResource,
        string $url,
    ): ?string {
        try {
            $folderResource = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($pathToResource);

            /** @psalm-suppress TypeDoesNotContainNull */
            if ($folderResource === null) {
                return null;
            }

            $this->informUserOfChange(
                $url,
                $folderResource->getStorage()->getUid(),
                LinkService::TYPE_FOLDER,
                $folderResource->getName()
            );

            return GeneralUtility::makeInstance(LinkService::class)->asString([
                'type' => LinkService::TYPE_FOLDER,
                'folder' => $folderResource
            ]);
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
        return is_file(Environment::getPublicPath() . $pathToResource);
    }

    private function folderExists(string $pathToResource): bool
    {
        /** @psalm-suppress InternalMethod */
        return is_dir(Environment::getPublicPath() . $pathToResource);
    }
}
