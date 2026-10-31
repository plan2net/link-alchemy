<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\LinkAlchemy\LinkResolver;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class LinkResolverTest extends FunctionalTestCase
{
    protected array $configurationToUseInTestInstance = [
        'FE' => ['cacheHash' => ['excludedParameters' => ['link']]],
    ];

    /**
     * @return array<string, array{string, ?string}>
     */
    public static function urls(): array
    {
        return [
            'page' => ['https://link-alchemy.example/about', 't3://page?uid=2'],
            'page with query' => ['https://link-alchemy.example/about?foo=bar', 't3://page?uid=2&foo=bar'],
            'page with fragment' => ['https://link-alchemy.example/about#team', 't3://page?uid=2#team'],
            'page in second language' => ['https://link-alchemy.example/de/ueber-uns', 't3://page?uid=2&_language=1'],
            'file' => ['https://link-alchemy.example/fileadmin/welcome.txt', 't3://file?uid=1'],
            'folder' => ['https://link-alchemy.example/fileadmin/downloads/', 't3://folder?storage=1&identifier=%2Fdownloads%2F'],
            'foreign host' => ['https://elsewhere.example/about', null],
            'unknown path' => ['https://link-alchemy.example/nowhere', null],
            'deleted page' => ['https://link-alchemy.example/gone', null],
            'mail' => ['mailto:someone@example.com', null],
            'internal link' => ['t3://page?uid=2', null],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function resolvesAbsoluteUrlsWithoutBackendUser(string $url, ?string $expected): void
    {
        self::assertSame($expected, $this->get(LinkResolver::class)->resolve($url)?->uri);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pageUrls(): array
    {
        return [
            'page' => ['https://link-alchemy.example/about'],
            'page with fragment' => ['https://link-alchemy.example/about#team'],
            'page in second language' => ['https://link-alchemy.example/de/ueber-uns'],
        ];
    }

    #[Test]
    #[DataProvider('pageUrls')]
    public function resolvedPageLinkLeadsToTheSameUrlInTheFrontend(string $url): void
    {
        $link = $this->get(LinkResolver::class)->resolve($url)?->uri;
        $this->setUpFrontendRootPage(1, ['EXT:link_alchemy/Tests/Functional/Fixtures/Frontend/RenderLink.typoscript']);

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://link-alchemy.example/'))->withQueryParameters(['link' => $link]),
        );

        self::assertSame($url, trim((string)$response->getBody()));
    }
}
