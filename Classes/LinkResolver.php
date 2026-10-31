<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\LinkHandling\Exception\UnknownLinkHandlerException;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Routing\RouteNotFoundException;
use TYPO3\CMS\Core\Routing\SiteMatcher;
use TYPO3\CMS\Core\Routing\SiteRouteResult;
use TYPO3\CMS\Core\Site\Entity\Site;

class LinkResolver
{
    public function __construct(
        private readonly SiteMatcher $siteMatcher,
        private readonly ResourceFactory $resourceFactory,
        private readonly LinkService $linkService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(string $absoluteUrl): ?ResolvedLink
    {
        try {
            // A stream is required; the memory stream is a placeholder
            /** @psalm-suppress InternalClass, InternalMethod */
            $request = new ServerRequest($absoluteUrl, 'GET', 'php://memory');
        } catch (\Throwable $exception) {
            // Probably an unsupported scheme (t3:, mailto:) or a broken URL
            $this->logger->warning($exception->getMessage(), [$absoluteUrl]);

            return null;
        }

        /**
         * @var SiteRouteResult $siteRouteResult
         *
         * @psalm-suppress InternalMethod
         */
        $siteRouteResult = $this->siteMatcher->matchRequest($request);
        $site = $siteRouteResult->getSite();
        if (!$site instanceof Site) {
            return null;
        }

        try {
            /** @var PageArguments $pageArguments */
            $pageArguments = $site->getRouter()->matchRequest($request, $siteRouteResult);

            return $this->pageLink($siteRouteResult, $pageArguments);
        } catch (RouteNotFoundException|UnknownLinkHandlerException $exception) {
            /** @psalm-suppress InternalMethod */
            return $this->resourceLink($request->getUri()->getPath(), $exception);
        }
    }

    /**
     * @throws UnknownLinkHandlerException
     */
    private function pageLink(SiteRouteResult $siteRouteResult, PageArguments $pageArguments): ResolvedLink
    {
        $language = $siteRouteResult->getLanguage()->getLanguageId();
        $language = $language !== 0 ? '_language=' . $language : '';
        $query = $siteRouteResult->getUri()->getQuery();
        $arguments = $pageArguments->getArguments();
        $linkInformation = [
            'type' => LinkService::TYPE_PAGE,
            'pageuid' => $pageArguments->getPageId(),
            'parameters' => $language
                . ($language && $query ? '&' : '') . $query
                . (($language || $query) && $arguments ? '&' : '') . http_build_query($arguments),
            'fragment' => $siteRouteResult->getUri()->getFragment(),
        ];
        if (!empty($pageArguments->getPageType())) {
            $linkInformation['pagetype'] = $pageArguments->getPageType();
        }

        $pageRecord = BackendUtility::getRecord('pages', $pageArguments->getPageId(), 'title');

        return new ResolvedLink(
            $this->linkService->asString($linkInformation),
            LinkService::TYPE_PAGE,
            $pageArguments->getPageId(),
            $pageRecord['title'],
        );
    }

    private function resourceLink(string $path, \Throwable $routingFailure): ?ResolvedLink
    {
        try {
            if (is_file(Environment::getPublicPath() . $path)) {
                $file = $this->resourceFactory->getFileObjectFromCombinedIdentifier($path);

                return $file === null ? null : new ResolvedLink(
                    $this->linkService->asString(['type' => LinkService::TYPE_FILE, 'file' => $file]),
                    LinkService::TYPE_FILE,
                    $file->getUid(),
                    $file->getName(),
                );
            }

            if (is_dir(Environment::getPublicPath() . $path)) {
                $folder = $this->resourceFactory->getFolderObjectFromCombinedIdentifier($path);

                return new ResolvedLink(
                    $this->linkService->asString(['type' => LinkService::TYPE_FOLDER, 'folder' => $folder]),
                    LinkService::TYPE_FOLDER,
                    $folder->getStorage()->getUid(),
                    $folder->getName(),
                );
            }
        } catch (\InvalidArgumentException $exception) {
            // The file or folder exists, but has no identifier
            $this->logger->warning($exception->getMessage(), [$path]);

            return null;
        }

        $this->logger->warning($routingFailure->getMessage(), [$path]);

        return null;
    }
}
