<?php

namespace kickoff;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\notifications\TipDeadlineReminder;
use humhub\modules\kickoff\services\KickoffTime;
use humhub\modules\kickoff\services\NotificationDispatcher;
use humhub\modules\notification\components\NotificationContext;
use humhub\modules\notification\components\NotificationManager;
use humhub\modules\notification\models\Notification;
use humhub\modules\notification\targets\MailTarget;
use tests\codeception\_support\HumHubDbTestCase;
use Yii;

/**
 * Tip deadline reminders: who gets one, once per day, and how it is rendered.
 *
 * Needs the database (competition, games, participations), so the rows are created
 * here and removed again in {@see _after()}.
 */
class NotificationDispatcherTest extends HumHubDbTestCase
{
    private const SLUG = 'notification-dispatcher-test';

    private Competition $competition;

    /** @var int[] participating users: [0] has no tip yet, [1] has already tipped */
    private array $userIds;

    private int $gameId;

    /** @var int[] */
    private array $teamIds = [];

    private ?int $schemeId = null;

    protected function _before(): void
    {
        // Leftovers of an aborted earlier run
        $this->cleanUp();

        $db = Yii::$app->db;
        $db->createCommand()->insert('kickoff_scoring_scheme', ['name' => 'Test scheme'])->execute();
        $schemeId = $this->schemeId = (int)$db->getLastInsertID();
        $db->createCommand()->insert('kickoff_team', ['name' => 'Team A'])->execute();
        $homeId = $this->teamIds[] = (int)$db->getLastInsertID();
        $db->createCommand()->insert('kickoff_team', ['name' => 'Team B'])->execute();
        $awayId = $this->teamIds[] = (int)$db->getLastInsertID();

        $db->createCommand()->insert('kickoff_competition', [
            'name' => 'Test <Cup>',
            'slug' => self::SLUG,
            'type' => 'league',
            'scoring_scheme_id' => $schemeId,
            'status' => 'active',
        ])->execute();
        $this->competition = Competition::findOne(['slug' => self::SLUG]);

        $db->createCommand()->insert('kickoff_game', [
            'competition_id' => $this->competition->id,
            'home_team_id' => $homeId,
            'away_team_id' => $awayId,
            'kickoff_at' => KickoffTime::dbAt(time() + 5 * 3600),
            'stage' => 'group',
            'status' => 'scheduled',
        ])->execute();
        $this->gameId = (int)$db->getLastInsertID();

        $this->userIds = array_map('intval', \humhub\modules\user\models\User::find()
            ->select('id')->orderBy('id')->limit(2)->column());

        foreach ($this->userIds as $userId) {
            $db->createCommand()->insert('kickoff_participation', [
                'competition_id' => $this->competition->id,
                'user_id' => $userId,
                'joined_at' => KickoffTime::nowDb(),
            ])->execute();
        }

        // The second user has already tipped the game
        $db->createCommand()->insert('kickoff_tip', [
            'game_id' => $this->gameId,
            'user_id' => $this->userIds[1],
            'home_score' => 1,
            'away_score' => 0,
            'submitted_at' => KickoffTime::nowDb(),
        ])->execute();

        Yii::$app->cache->flush();
    }

    protected function _after(): void
    {
        $this->cleanUp();
        Yii::$app->cache->flush();
    }

    public function testRemindsOnlyUsersWithMissingTips(): void
    {
        $this->assertSame(1, (new NotificationDispatcher())->sendDeadlineReminders($this->competition));

        $this->assertSame(1, $this->countReminders($this->userIds[0]));
        $this->assertSame(0, $this->countReminders($this->userIds[1]));
    }

    public function testRemindsOncePerUserAndDay(): void
    {
        $dispatcher = new NotificationDispatcher();

        $this->assertSame(1, $dispatcher->sendDeadlineReminders($this->competition));
        $this->assertSame(0, $dispatcher->sendDeadlineReminders($this->competition));
        $this->assertSame(1, $this->countReminders($this->userIds[0]));
    }

    public function testRemindsAgainOnTheNextDay(): void
    {
        $dispatcher = new NotificationDispatcher();
        $dispatcher->sendDeadlineReminders($this->competition);

        // A new day: the per-day cache key is gone. The core's own de-duplication must not hold the reminder back.
        Yii::$app->cache->flush();
        $this->assertSame(1, $dispatcher->sendDeadlineReminders($this->competition));
        $this->assertSame(2, $this->countReminders($this->userIds[0]));
    }

    public function testNoReminderWithoutGameInTheNext24Hours(): void
    {
        Yii::$app->db->createCommand()
            ->update('kickoff_game', ['kickoff_at' => KickoffTime::dbAt(time() + 3 * 86400)], ['id' => $this->gameId])
            ->execute();

        $this->assertSame(0, (new NotificationDispatcher())->sendDeadlineReminders($this->competition));
        $this->assertSame(0, $this->countReminders($this->userIds[0]));
    }

    public function testRendering(): void
    {
        (new NotificationDispatcher())->sendDeadlineReminders($this->competition);

        $record = Notification::find()
            ->where(['user_id' => $this->userIds[0], 'class' => TipDeadlineReminder::class])
            ->one();
        $this->assertNotNull($record);

        $notification = NotificationManager::load($record);

        $this->assertSame('kickoff', $notification::category()->id);
        $this->assertSame(
            'Don\'t forget to place your tips in <strong>Test &lt;Cup&gt;</strong> — kickoff in less than 24 hours.',
            $notification->asWeb(),
        );
        $this->assertSame('Tip deadline approaching: “Test <Cup>”', $notification->asMailSubject());
        $this->assertStringContainsString(self::SLUG, $notification->getUrl(true));

        $blocks = $notification->getRenderedBlocks(new NotificationContext(MailTarget::ID));
        $this->assertSame(
            '<p>You have pending tips in <strong>Test &lt;Cup&gt;</strong> and at least one game kicks off within the next 24 hours.</p>',
            $blocks[0]->getHtml(),
        );
        $this->assertSame(
            'You have pending tips in Test <Cup> and at least one game kicks off within the next 24 hours.',
            $blocks[0]->getText(),
        );
        $this->assertSame('Place your tips before kickoff:', $blocks[1]->getText());
    }

    private function countReminders(int $userId): int
    {
        return (int)Notification::find()
            ->where(['user_id' => $userId, 'class' => TipDeadlineReminder::class])
            ->count();
    }

    private function cleanUp(): void
    {
        $db = Yii::$app->db;
        $competitionId = $db->createCommand('SELECT id FROM kickoff_competition WHERE slug = :slug', [':slug' => self::SLUG])->queryScalar();
        if ($competitionId === false) {
            return;
        }

        $gameIds = $db->createCommand('SELECT id FROM kickoff_game WHERE competition_id = :id', [':id' => $competitionId])->queryColumn();
        if ($gameIds !== []) {
            $db->createCommand()->delete('kickoff_tip', ['game_id' => $gameIds])->execute();
        }
        $db->createCommand()->delete('kickoff_game', ['competition_id' => $competitionId])->execute();
        $db->createCommand()->delete('kickoff_participation', ['competition_id' => $competitionId])->execute();

        // Notifications about the competition are removed with their source record (cascade)
        $db->createCommand()->delete('kickoff_competition', ['id' => $competitionId])->execute();

        if ($this->teamIds !== []) {
            $db->createCommand()->delete('kickoff_team', ['id' => $this->teamIds])->execute();
        }
        if ($this->schemeId !== null) {
            $db->createCommand()->delete('kickoff_scoring_scheme', ['id' => $this->schemeId])->execute();
        }
    }
}
