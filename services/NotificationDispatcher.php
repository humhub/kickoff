<?php

namespace humhub\modules\kickoff\services;

use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Participation;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\notifications\TipDeadlineReminder;
use humhub\modules\kickoff\services\KickoffTime;
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
        // `kickoff_at` is stored in UTC; compare against UTC bounds, not the
        // server's local-time `date(...)` (which would shift the window by
        // the server-tz offset whenever the server isn't UTC).
        $cutoff = KickoffTime::dbAt($now + 86400);

        $upcomingGames = Game::find()
            ->select(['id'])
            ->where([
                'competition_id' => $competition->id,
                'status' => Game::STATUS_SCHEDULED,
            ])
            ->andWhere(['>', 'kickoff_at', KickoffTime::dbAt($now)])
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
