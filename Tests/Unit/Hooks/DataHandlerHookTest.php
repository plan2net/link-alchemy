<?php

declare(strict_types=1);

namespace FriendsOfTypo3\TtAddress\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plan2net\LinkAlchemy\Hooks\DataHandlerHook;
use Plan2net\LinkAlchemy\Service\UrlParser;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DataHandlerHookTest extends UnitTestCase
{
    protected function setUp(): void
    {
        $GLOBALS['TCA']['fakeTable1']['columns']['field_1']['config']['type'] = 'link';
        $GLOBALS['TCA']['fakeTable2']['columns']['field_3']['config']['type'] = 'link';

        parent::setUp();
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['TCA']['fakeTable1'], $GLOBALS['TCA']['fakeTable2']);
        parent::tearDown();
    }

    #[DataProvider('fieldProcessingWorksDataProvider')]
    #[Test]
    public function fieldProcessingWorks(string $tableName, string $fieldName, $fieldValue, bool $expected): void
    {
        $subject = $this->getAccessibleMock(DataHandlerHook::class, null, [], '', false);
        $this->assertEquals($expected, $subject->_call('fieldShouldBeProcessed', $tableName, $fieldName, $fieldValue));
    }

    public static function fieldProcessingWorksDataProvider(): array
    {
        return [
            'link field' => ['fakeTable1', 'field_1', 'http://domain.tld', true],
            'link field with https' => ['fakeTable1', 'field_1', 'https://domain.tld', true],
            'link field with / at beginning' => ['fakeTable1', 'field_1', '/', true],
            'link field with email link' => ['fakeTable1', 'field_1', 'fo@bar.com', false],
            'link field with empty link' => ['fakeTable1', 'field_1', '', false],
            'link field with different type' => ['fakeTable1', 'field_1', 123, false],
            'none existing table' => ['fakeTable3', 'field_1', 123, false],
            'none existing field' => ['fakeTable1', 'field_9', 'http://domain.tld', false],
            'wrong type' => ['fakeTable1', 'field_2', 'http://domain.tld', false],
        ];
    }

    #[Test]
    public function urlParserIsCalled(): void
    {
        $mockedUrlParser = $this->getAccessibleMock(UrlParser::class, ['parse'], [], '', false);
        $mockedUrlParser->expects($this->once())->method('parse');
        $mockedDataHandler = $this->getAccessibleMock(DataHandler::class, null, [], '', false);

        $subject = $this->getAccessibleMock(DataHandlerHook::class, null, [], '', false);
        $subject->_set('urlParser', $mockedUrlParser);

        $fields = [
            'field_1' => 'http://domain.tld/fo'
        ];
        $subject->processDatamap_postProcessFieldArray('update', 'fakeTable1', 123, $fields, $mockedDataHandler);
    }
}
