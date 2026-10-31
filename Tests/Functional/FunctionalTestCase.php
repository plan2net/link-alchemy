<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Tests\Functional;

use TYPO3\CMS\Core\Core\Environment;

abstract class FunctionalTestCase extends \TYPO3\TestingFramework\Core\Functional\FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['plan2net/link-alchemy'];

    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(Environment::getConfigPath() . '/sites/main', 0o775, true);
        copy(__DIR__ . '/Fixtures/Sites/main/config.yaml', Environment::getConfigPath() . '/sites/main/config.yaml');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database.csv');
        @mkdir(Environment::getPublicPath() . '/fileadmin/downloads', 0o775, true);
        file_put_contents(Environment::getPublicPath() . '/fileadmin/welcome.txt', 'Hello world');
    }
}
