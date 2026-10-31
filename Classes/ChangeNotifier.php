<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy;

use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ChangeNotifier
{
    public function __construct(
        private readonly FlashMessageService $flashMessageService,
    ) {
    }

    public function notify(string $url, ResolvedLink $link): void
    {
        $translationKey = match ($link->type) {
            LinkService::TYPE_PAGE => 'externalPageLinkChanged',
            LinkService::TYPE_FILE => 'externalFileLinkChanged',
            LinkService::TYPE_FOLDER => 'externalFolderLinkChanged',
        };

        $this->flashMessageService->getMessageQueueByIdentifier()->addMessage(new FlashMessage(
            (string)LocalizationUtility::translate($translationKey, 'link_alchemy', [$url, $link->label, $link->id]),
            '',
            ContextualFeedbackSeverity::INFO,
            true,
        ));
    }
}
