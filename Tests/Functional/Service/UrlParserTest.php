<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\LinkAlchemy\Service\UrlParser;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class UrlParserTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['plan2net/link-alchemy'];

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(Environment::getConfigPath() . '/sites/main', 0o775, true);
        copy(__DIR__ . '/../Fixtures/Sites/main/config.yaml', Environment::getConfigPath() . '/sites/main/config.yaml');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database.csv');
        $this->setUpBackendUser(1);
        @mkdir(Environment::getPublicPath() . '/fileadmin/downloads', 0o775, true);
        file_put_contents(Environment::getPublicPath() . '/fileadmin/welcome.txt', 'Hello world');
    }

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
            'mail' => ['mailto:someone@example.com', null],
            'internal link' => ['t3://page?uid=2', null],
        ];
    }

    #[Test]
    #[DataProvider('urls')]
    public function resolvesAbsoluteUrls(string $url, ?string $expected): void
    {
        self::assertSame($expected, $this->urlParser()->parse($url));
    }

    #[Test]
    public function resolvesPathsAgainstBaseUrl(): void
    {
        self::assertSame('t3://page?uid=2', $this->urlParser()->parse('/about', 'https://link-alchemy.example/'));
    }

    private function urlParser(): UrlParser
    {
        return $this->get(UrlParser::class);
    }
}
