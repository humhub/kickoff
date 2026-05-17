<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Participation;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\notifications\PointsAwarded;
use humhub\modules\kickoff\notifications\TipDeadlineReminder;
use humhub\modules\user\models\User;
use Yii;

class NotificationDispatcher
{
    /**
     * Sends a tip-deadline reminder to each participating user who has missing tips
     * for any game kicking off in the next 24 hours. Deduplicates so each user receives
     * at most one reminder per competition per calendar day.
     */
    public function sendDeadlineReminders(Competition $competition): int
    {
        $now = time();
        $cutoff = date('Y-m-d H:i:s', $now + 86400);

        $upcomingGames = Game::find()
            ->select(['id'])
            ->where([
                'competition_id' => $competition->id,
                'status' => Game::STATUS_SCHEDULED,
            ])
            ->andWhere(['>', 'kickoff_at', date('Y-m-d H:i:s', $now)])
            ->andWhere(['<=', 'kickoff_at', $cutoff])
            ->column();

        if ($upcomingGames === []) {
            return 0;
        }

        $participations = Participation::find()
            ->where(['competition_id' => $competition->id])
            ->all();

        $today = date('Y-m-d', $now);
        $sent = 0;
        foreach ($participations as $p) {
            $cacheKey = "kickoff.deadline_sent.{$p->user_id}.{$competition->id}.{$today}";
            if (Yii::$app->cache->get($cacheKey) !== false) {
                continue;
            }

            $missingCount = $this->countMissingTips($p->user_id, $upcomingGames);
            if ($missingCount === 0) {
                continue;
            }

            $user = User::findOne($p->user_id);
            if ($user === null) {
                continue;
            }

            $notification = new TipDeadlineReminder();
            $notification->source = $competition;
            $notification->send($user);

            Yii::$app->cache->set($cacheKey, true, 86400 * 2);
            $sent++;
        }
        return $sent;
    }

    /**
     * Sends a points-awarded digest to each user whose tip(s) were scored
     * for games in this competition since the dispatcher last ran.
     * Tracks per-user last-notified timestamp in module settings.
     */
    public function sendPointsAwarded(Competition $competition): int
    {
        $settings = \humhub\modules\kickoff\Module::instance()->settings;
        $key = "points_notify.{$competition->id}";
        $since = (string) ($settings->get($key) ?? date('Y-m-d H:i:s', time() - 86400));
        $now = date('Y-m-d H:i:s');

        $rows = Tip::find()
            ->select(['kickoff_tip.user_id', 'COUNT(*) as game_count', 'SUM(kickoff_tip.points) as total'])
            ->innerJoin('kickoff_game g', 'g.id = kickoff_tip.game_id')
            ->where(['g.competition_id' => $competition->id])
            ->andWhere(['>', 'g.last_synced_at', $since])
            ->andWhere(['IS NOT', 'kickoff_tip.points', null])
            ->groupBy('kickoff_tip.user_id')
            ->asArray()
            ->all();

        $sent = 0;
        foreach ($rows as $row) {
            if ((int) $row['total'] <= 0) {
                continue;
            }
            $user = User::findOne((int) $row['user_id']);
            if ($user === null) {
                continue;
            }
            $notification = new PointsAwarded();
            $notification->source = $competition;
            $notification->send($user);
            $sent++;
        }

        $settings->set($key, $now);
        return $sent;
    }

    /**
     * @param int[] $gameIds
     */
    private function countMissingTips(int $userId, array $gameIds): int
    {
        if ($gameIds === []) {
            return 0;
        }
        $tipped = (int) Tip::find()
            ->where(['user_id' => $userId, 'game_id' => $gameIds])
            ->count();
        return count($gameIds) - $tipped;
    }
}
