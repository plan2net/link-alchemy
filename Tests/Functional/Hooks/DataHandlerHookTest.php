<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy\Tests\Functional\Hooks;

use PHPUnit\Framework\Attributes\Test;
use Plan2net\LinkAlchemy\Tests\Functional\FunctionalTestCase;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessageService;

final class DataHandlerHookTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Content.csv');
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($this->setUpBackendUser(1));
    }

    #[Test]
    public function savesAbsoluteUrlInLinkFieldAsPageLink(): void
    {
        $this->save(['header_link' => 'https://link-alchemy.example/about']);

        self::assertSame('t3://page?uid=2', BackendUtility::getRecord('tt_content', 1)['header_link']);
    }

    #[Test]
    public function savesPathInLinkFieldAsPageLink(): void
    {
        $this->save(['header_link' => '/about']);

        self::assertSame('t3://page?uid=2', BackendUtility::getRecord('tt_content', 1)['header_link']);
    }

    #[Test]
    public function savesPathInLinkFieldOfNewRecordAsPageLink(): void
    {
        $dataHandler = $this->process(['NEW1' => ['pid' => 1, 'CType' => 'text', 'header_link' => '/about']]);

        self::assertSame('t3://page?uid=2', BackendUtility::getRecord('tt_content', $dataHandler->substNEWwithIDs['NEW1'])['header_link']);
    }

    #[Test]
    public function keepsTargetClassAndTitleOfLink(): void
    {
        $this->save(['header_link' => 'https://link-alchemy.example/about _blank button "About us"']);

        self::assertSame('t3://page?uid=2 _blank button "About us"', BackendUtility::getRecord('tt_content', 1)['header_link']);
    }

    #[Test]
    public function savesAbsoluteUrlInRichTextAsPageLink(): void
    {
        $this->save(['bodytext' => '<p><a href="https://link-alchemy.example/about" target="_blank">About us</a></p>']);

        self::assertStringContainsString(
            '<a href="t3://page?uid=2" target="_blank">About us</a>',
            BackendUtility::getRecord('tt_content', 1)['bodytext'],
        );
    }

    #[Test]
    public function savesUnchangedRichTextWithPageLink(): void
    {
        $this->process([2 => ['bodytext' => '<p><a href="https://link-alchemy.example/about">About us</a></p>']]);

        self::assertStringContainsString('href="t3://page?uid=2"', BackendUtility::getRecord('tt_content', 2)['bodytext']);
    }

    #[Test]
    public function savesPathInLinkFieldOfRecordAddedAfterAnotherAsPageLink(): void
    {
        $dataHandler = $this->process(['NEW1' => ['pid' => -1, 'CType' => 'text', 'header_link' => '/about']]);

        self::assertSame('t3://page?uid=2', BackendUtility::getRecord('tt_content', $dataHandler->substNEWwithIDs['NEW1'])['header_link']);
    }

    #[Test]
    public function leavesHtmlWithoutRichTextEditorAlone(): void
    {
        $html = '<a href="https://link-alchemy.example/about">About us</a>';

        $this->save(['CType' => 'html', 'bodytext' => $html]);

        self::assertSame($html, BackendUtility::getRecord('tt_content', 1)['bodytext']);
    }

    #[Test]
    public function leavesOtherFieldsAlone(): void
    {
        $this->save(['header' => 'https://link-alchemy.example/about']);

        self::assertSame('https://link-alchemy.example/about', BackendUtility::getRecord('tt_content', 1)['header']);
    }

    #[Test]
    public function tellsTheEditorAboutTheChange(): void
    {
        $this->save(['header_link' => 'https://link-alchemy.example/about']);

        self::assertCount(1, $this->get(FlashMessageService::class)->getMessageQueueByIdentifier()->getAllMessages());
    }

    /**
     * @param array<string, string> $fields
     */
    private function save(array $fields): void
    {
        $this->process([1 => $fields]);
    }

    /**
     * @param array<int|string, array<string, int|string>> $records
     */
    private function process(array $records): DataHandler
    {
        $dataHandler = $this->get(DataHandler::class);
        $dataHandler->start(['tt_content' => $records], []);
        $dataHandler->process_datamap();
        self::assertSame([], $dataHandler->errorLog);

        return $dataHandler;
    }
}
