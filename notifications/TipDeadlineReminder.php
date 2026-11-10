<?php

namespace humhub\modules\kickoff\notifications;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\notification\components\BaseNotification;
use humhub\modules\notification\components\MessageParam;
use humhub\modules\notification\components\NotificationBlock;
use humhub\modules\notification\components\NotificationCategory;
use humhub\modules\notification\components\NotificationContext;
use Yii;
use yii\helpers\Html;
use yii\helpers\Url;

final class TipDeadlineReminder extends BaseNotification
{
    public static function category(): NotificationCategory
    {
        return new NotificationCategory(
            'kickoff',
            Yii::t('KickoffModule.base', 'Kickoff'),
            Yii::t('KickoffModule.base', 'Tip deadline reminders from Kickoff competitions.'),
            icon: 'ti-ball-football',
        );
    }

    protected function getMessage(array $params): string
    {
        return Yii::t(
            'KickoffModule.base',
            'Don\'t forget to place your tips in {competition} — kickoff in less than 24 hours.',
            $params,
        );
    }

    protected function getMessageParams(): array
    {
        return ['competition' => MessageParam::emphasis($this->getCompetition()?->name ?? '')];
    }

    protected function getMailSubject(array $params): string
    {
        return Yii::t('KickoffModule.base', 'Tip deadline approaching: {competition}', $params);
    }

    /**
     * Text shown under the sentence in a mail about this notification (followed by the "View online" button).
     */
    public function getBlocks(NotificationContext $context): array
    {
        $pending = Yii::t(
            'KickoffModule.base',
            'You have pending tips in <strong>{competition}</strong> and at least one game kicks off within the next 24 hours.',
            ['competition' => Html::encode($this->getCompetition()?->name ?? '')],
        );

        return [
            NotificationBlock::html('<p>' . $pending . '</p>', html_entity_decode(strip_tags($pending), ENT_QUOTES)),
            NotificationBlock::text(Yii::t('KickoffModule.base', 'Place your tips before kickoff:')),
        ];
    }

    public function getUrl(bool $scheme = false): ?string
    {
        $competition = $this->getCompetition();

        if ($competition === null) {
            return Url::to(['/kickoff'], $scheme);
        }

        return Url::to(['/kickoff/competition/view', 'slug' => $competition->slug], $scheme);
    }

    private function getCompetition(): ?Competition
    {
        return $this->sourceRecord instanceof Competition ? $this->sourceRecord : null;
    }
}
