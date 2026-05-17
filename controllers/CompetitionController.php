<?php

namespace humhub\modules\kickoff\controllers;

use humhub\components\Controller;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\Participation;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\SpecialBetTip;
use humhub\modules\kickoff\models\Tip;
use humhub\modules\kickoff\Module;
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

    public function actionView($slug, $matchday = null)
    {
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $upcomingAll = Game::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['<>', 'status', Game::STATUS_FINISHED])
            ->andWhere(['<>', 'status', Game::STATUS_CANCELLED])
            ->andWhere(['>', 'kickoff_at', date('Y-m-d H:i:s')])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_ASC])
            ->all();

        $matchdayDates = [];
        foreach ($upcomingAll as $g) {
            $d = substr($g->kickoff_at, 0, 10);
            if (!in_array($d, $matchdayDates, true)) {
                $matchdayDates[] = $d;
            }
        }

        $selectedMatchday = is_string($matchday) ? $matchday : null;
        if ($selectedMatchday === null || !in_array($selectedMatchday, $matchdayDates, true)) {
            $selectedMatchday = $matchdayDates[0] ?? null;
        }

        $upcomingGames = $selectedMatchday === null ? [] : array_values(array_filter(
            $upcomingAll,
            fn($g) => substr($g->kickoff_at, 0, 10) === $selectedMatchday,
        ));

        $prevMatchday = $nextMatchday = null;
        if ($selectedMatchday !== null) {
            $idx = array_search($selectedMatchday, $matchdayDates, true);
            if ($idx !== false) {
                if ($idx > 0) {
                    $prevMatchday = $matchdayDates[$idx - 1];
                }
                if ($idx < count($matchdayDates) - 1) {
                    $nextMatchday = $matchdayDates[$idx + 1];
                }
            }
        }

        $finishedGames = Game::find()
            ->where(['competition_id' => $competition->id, 'status' => Game::STATUS_FINISHED])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_DESC])
            ->limit(5)
            ->all();

        $tipsByGame = $this->loadTipsByGameId($userId, array_merge($upcomingGames, $finishedGames));

        $openSpecialBets = SpecialBet::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['IS', 'resolved_value', null])
            ->andWhere(['>', 'deadline_at', date('Y-m-d H:i:s')])
            ->orderBy(['deadline_at' => SORT_ASC])
            ->all();

        $resolvedSpecialBets = SpecialBet::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['IS NOT', 'resolved_value', null])
            ->orderBy(['resolved_at' => SORT_DESC])
            ->all();

        $specialBetTipsByBet = $this->loadSpecialBetTips(
            $userId,
            array_merge($openSpecialBets, $resolvedSpecialBets),
        );

        $leaderboard = (new LeaderboardService($competition))->compute(10);

        $participation = Participation::findOne(['competition_id' => $competition->id, 'user_id' => $userId]);

        return $this->render('view', [
            'competition' => $competition,
            'upcomingGames' => $upcomingGames,
            'finishedGames' => $finishedGames,
            'tipsByGame' => $tipsByGame,
            'openSpecialBets' => $openSpecialBets,
            'resolvedSpecialBets' => $resolvedSpecialBets,
            'specialBetTipsByBet' => $specialBetTipsByBet,
            'leaderboard' => $leaderboard,
            'isParticipating' => $participation !== null,
            'matchdayDates' => $matchdayDates,
            'selectedMatchday' => $selectedMatchday,
            'prevMatchday' => $prevMatchday,
            'nextMatchday' => $nextMatchday,
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

        $matchday = (string) Yii::$app->request->post('matchday', '');
        $params = ['view', 'slug' => $competition->slug];
        if ($matchday !== '') {
            $params['matchday'] = $matchday;
        }
        return $this->redirect($params);
    }

    /**
     * Single-tip endpoint for autosave. Returns JSON.
     */
    public function actionTip($slug)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $gameId = (int) Yii::$app->request->post('game_id');
        $home = Yii::$app->request->post('home_score');
        $away = Yii::$app->request->post('away_score');

        if (!is_numeric($home) || !is_numeric($away)) {
            Yii::$app->response->statusCode = 400;
            return $this->asJson(['ok' => false, 'error' => 'invalid_input']);
        }

        $game = Game::findOne(['id' => $gameId, 'competition_id' => $competition->id]);
        if ($game === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['ok' => false, 'error' => 'game_not_found']);
        }
        if ($game->isKickoffPassed()) {
            Yii::$app->response->statusCode = 409;
            return $this->asJson(['ok' => false, 'error' => 'kickoff_passed']);
        }

        $this->ensureParticipation($competition, $userId);

        $tip = Tip::findOne(['game_id' => $game->id, 'user_id' => $userId])
            ?? new Tip(['game_id' => $game->id, 'user_id' => $userId]);
        $tip->home_score = (int) $home;
        $tip->away_score = (int) $away;
        if (!$tip->save()) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson(['ok' => false, 'error' => 'save_failed', 'details' => $tip->getFirstErrors()]);
        }

        return $this->asJson(['ok' => true]);
    }

    public function actionSpecialBetTips($slug)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $this->ensureParticipation($competition, $userId);

        $registry = Module::instance()->getSpecialBetTypeRegistry();
        $input = (array) Yii::$app->request->post('special_bets', []);
        $saved = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($input as $betId => $value) {
            $value = is_string($value) ? trim($value) : '';
            if ($value === '') {
                continue;
            }

            $bet = SpecialBet::findOne(['id' => (int) $betId, 'competition_id' => $competition->id]);
            if ($bet === null) {
                continue;
            }
            if ($bet->isDeadlinePassed() || $bet->isResolved()) {
                $skipped++;
                continue;
            }

            $type = $registry->get($bet->type);
            if ($type !== null && !$type->validateValue($value, $bet)) {
                $errors++;
                continue;
            }

            $tip = SpecialBetTip::findOne(['special_bet_id' => $bet->id, 'user_id' => $userId])
                ?? new SpecialBetTip(['special_bet_id' => $bet->id, 'user_id' => $userId]);
            $tip->value = $value;
            if ($tip->save()) {
                $saved++;
            } else {
                $errors++;
            }
        }

        if ($saved > 0) {
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                '{n} special bet tip(s) saved.',
                ['n' => $saved],
            ));
        }
        if ($skipped > 0) {
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                '{n} special bet tip(s) skipped — deadline already passed.',
                ['n' => $skipped],
            ));
        }
        if ($errors > 0) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                '{n} special bet tip(s) could not be saved.',
                ['n' => $errors],
            ));
        }

        return $this->redirect(['view', 'slug' => $competition->slug]);
    }

    /**
     * @param SpecialBet[] $bets
     * @return array<int, SpecialBetTip> special_bet_id => SpecialBetTip
     */
    private function loadSpecialBetTips(int $userId, array $bets): array
    {
        $betIds = array_map(fn(SpecialBet $b) => $b->id, $bets);
        if ($betIds === []) {
            return [];
        }
        $tips = SpecialBetTip::find()
            ->where(['user_id' => $userId, 'special_bet_id' => $betIds])
            ->all();
        $byBet = [];
        foreach ($tips as $tip) {
            $byBet[$tip->special_bet_id] = $tip;
        }
        return $byBet;
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
