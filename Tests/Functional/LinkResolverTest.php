<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\LinkAlchemy\LinkResolver;

final class LinkResolverTest extends FunctionalTestCase
{
    /**
     * @return array<string, array{string, ?string}>
     */
    public static function urls(): array
    {
        return [
            'page' => ['https://link-alchemy.example/about', 't3://page?uid=2'],
            'page with query' => ['https://link-alchemy.example/about?foo=bar', 't3://page?uid=2&foo=bar'],
            'page with fragment' => ['https://link-alchemy.example/about#team', 't3://page?uid=2#team'],
            'page in second language' => ['https://link-alchemy.example/de/ueber-uns', 't3://page?uid=2&L=1'],
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
}
