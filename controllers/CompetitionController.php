<?php

namespace humhub\modules\kickoff\controllers;

use humhub\components\Controller;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Participation;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\services\LeaderboardService;
use Yii;
use yii\web\NotFoundHttpException;

class CompetitionController extends Controller
{
    public function getAccessRules()
    {
        return [
            ['login'],
        ];
    }

    public function actionView($slug)
    {
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $upcomingGames = Game::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['<>', 'status', Game::STATUS_FINISHED])
            ->andWhere(['<>', 'status', Game::STATUS_CANCELLED])
            ->andWhere(['>', 'kickoff_at', date('Y-m-d H:i:s')])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_ASC])
            ->all();

        $finishedGames = Game::find()
            ->where(['competition_id' => $competition->id, 'status' => Game::STATUS_FINISHED])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_DESC])
            ->limit(5)
            ->all();

        $tipsByGame = $this->loadTipsByGameId($userId, array_merge($upcomingGames, $finishedGames));

        $leaderboard = (new LeaderboardService($competition))->compute(10);

        $participation = Participation::findOne(['competition_id' => $competition->id, 'user_id' => $userId]);

        return $this->render('view', [
            'competition' => $competition,
            'upcomingGames' => $upcomingGames,
            'finishedGames' => $finishedGames,
            'tipsByGame' => $tipsByGame,
            'leaderboard' => $leaderboard,
            'isParticipating' => $participation !== null,
        ]);
    }

    public function actionLeaderboard($slug)
    {
        $competition = $this->findCompetition($slug);
        $leaderboard = (new LeaderboardService($competition))->compute();
        return $this->render('leaderboard', [
            'competition' => $competition,
            'leaderboard' => $leaderboard,
        ]);
    }

    public function actionTips($slug)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $this->ensureParticipation($competition, $userId);

        $input = (array) Yii::$app->request->post('tips', []);
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($input as $gameId => $values) {
            if (!is_array($values) || !isset($values['home'], $values['away'])) {
                continue;
            }
            $home = trim((string) $values['home']);
            $away = trim((string) $values['away']);
            if ($home === '' || $away === '') {
                continue;
            }

            $game = Game::findOne(['id' => (int) $gameId, 'competition_id' => $competition->id]);
            if ($game === null) {
                continue;
            }
            if ($game->isKickoffPassed()) {
                $skipped++;
                continue;
            }

            $tip = Tip::findOne(['game_id' => $game->id, 'user_id' => $userId])
                ?? new Tip(['game_id' => $game->id, 'user_id' => $userId]);
            $tip->home_score = (int) $home;
            $tip->away_score = (int) $away;
            if ($tip->save()) {
                $saved++;
            } else {
                $errors++;
            }
        }

        if ($saved > 0) {
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                '{n} tip(s) saved.',
                ['n' => $saved],
            ));
        }
        if ($skipped > 0) {
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                '{n} tip(s) skipped — kickoff already passed.',
                ['n' => $skipped],
            ));
        }
        if ($errors > 0) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                '{n} tip(s) could not be saved.',
                ['n' => $errors],
            ));
        }

        return $this->redirect(['view', 'slug' => $competition->slug]);
    }

    private function ensureParticipation(Competition $competition, int $userId): void
    {
        $existing = Participation::findOne(['competition_id' => $competition->id, 'user_id' => $userId]);
        if ($existing !== null) {
            return;
        }
        $participation = new Participation();
        $participation->competition_id = $competition->id;
        $participation->user_id = $userId;
        $participation->save();
    }

    /**
     * @param Game[] $games
     * @return array<int, Tip>  game_id => Tip
     */
    private function loadTipsByGameId(int $userId, array $games): array
    {
        $gameIds = array_map(fn(Game $g) => $g->id, $games);
        if ($gameIds === []) {
            return [];
        }
        $tips = Tip::find()
            ->where(['user_id' => $userId, 'game_id' => $gameIds])
            ->all();
        $byGame = [];
        foreach ($tips as $tip) {
            $byGame[$tip->game_id] = $tip;
        }
        return $byGame;
    }

    private function findCompetition(string $slug): Competition
    {
        $competition = Competition::findOne(['slug' => $slug]);
        if ($competition === null) {
            throw new NotFoundHttpException();
        }
        return $competition;
    }
}
