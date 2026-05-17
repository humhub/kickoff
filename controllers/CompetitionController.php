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
use humhub\modules\user\models\User;
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

        $allGames = Game::find()
            ->where(['competition_id' => $competition->id])
            ->andWhere(['<>', 'status', Game::STATUS_CANCELLED])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_ASC])
            ->all();

        $allSpecialBets = SpecialBet::find()
            ->where(['competition_id' => $competition->id])
            ->orderBy(['deadline_at' => SORT_ASC])
            ->all();

        $openSpecialBets = [];
        $awaitingSpecialBets = [];
        $resolvedSpecialBets = [];
        foreach ($allSpecialBets as $bet) {
            if ($bet->isResolved()) {
                $resolvedSpecialBets[] = $bet;
            } elseif ($bet->isDeadlinePassed()) {
                $awaitingSpecialBets[] = $bet;
            } else {
                $openSpecialBets[] = $bet;
            }
        }

        $matchdayEntries = $this->buildMatchdayEntries($competition, $allGames);
        $bonusExists = $allSpecialBets !== [];
        if ($bonusExists) {
            array_unshift($matchdayEntries, [
                'id' => 'bonus',
                'label' => Yii::t('KickoffModule.base', 'Bonus'),
                'games' => [],
                'isPlaceholder' => false,
                'isBonus' => true,
            ]);
        }
        $selectedMatchday = is_string($matchday) ? $matchday : '';

        $selectedEntry = null;
        $selectedIdx = null;
        foreach ($matchdayEntries as $idx => $entry) {
            if ($entry['id'] === $selectedMatchday) {
                $selectedEntry = $entry;
                $selectedIdx = $idx;
                break;
            }
        }
        if ($selectedEntry === null) {
            $defaultId = $bonusExists && $this->shouldDefaultToBonus($openSpecialBets, $userId)
                ? 'bonus'
                : $this->pickDefaultMatchday($matchdayEntries);
            foreach ($matchdayEntries as $idx => $entry) {
                if ($entry['id'] === $defaultId) {
                    $selectedEntry = $entry;
                    $selectedIdx = $idx;
                    $selectedMatchday = $entry['id'];
                    break;
                }
            }
        }

        $matchdayGames = $selectedEntry['games'] ?? [];
        $selectedIsPlaceholder = $selectedEntry['isPlaceholder'] ?? false;
        $selectedIsBonus = $selectedEntry['isBonus'] ?? false;

        $prevEntry = $selectedIdx !== null && $selectedIdx > 0 ? $matchdayEntries[$selectedIdx - 1] : null;
        $nextEntry = $selectedIdx !== null && $selectedIdx < count($matchdayEntries) - 1
            ? $matchdayEntries[$selectedIdx + 1] : null;

        $finishedGames = Game::find()
            ->where(['competition_id' => $competition->id, 'status' => Game::STATUS_FINISHED])
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_DESC])
            ->limit(5)
            ->all();

        $tipsByGame = $this->loadTipsByGameId($userId, array_merge($matchdayGames, $finishedGames));

        $specialBetTipsByBet = $this->loadSpecialBetTips($userId, $allSpecialBets);

        $leaderboardService = new LeaderboardService($competition);

        $matchdayGameIds = array_map(fn($g) => $g->id, $matchdayGames);
        $matchdayLeaderboard = $selectedIsBonus || $matchdayGameIds === []
            ? []
            : $leaderboardService->computeForGames($matchdayGameIds, 10);

        $overallTop = $leaderboardService->compute(10);
        $userOverallRow = $leaderboardService->findUserRank($userId);

        $participation = Participation::findOne(['competition_id' => $competition->id, 'user_id' => $userId]);

        return $this->render('view', [
            'competition' => $competition,
            'matchdayGames' => $matchdayGames,
            'finishedGames' => $finishedGames,
            'tipsByGame' => $tipsByGame,
            'openSpecialBets' => $openSpecialBets,
            'awaitingSpecialBets' => $awaitingSpecialBets,
            'resolvedSpecialBets' => $resolvedSpecialBets,
            'specialBetTipsByBet' => $specialBetTipsByBet,
            'isParticipating' => $participation !== null,
            'matchdayEntries' => $matchdayEntries,
            'selectedMatchday' => $selectedMatchday,
            'selectedEntry' => $selectedEntry,
            'selectedIsPlaceholder' => $selectedIsPlaceholder,
            'selectedIsBonus' => $selectedIsBonus,
            'prevEntry' => $prevEntry,
            'nextEntry' => $nextEntry,
            'matchdayLeaderboard' => $matchdayLeaderboard,
            'overallTop' => $overallTop,
            'userOverallRow' => $userOverallRow,
        ]);
    }

    /**
     * @param Game[] $allGames  ALL games for the competition (past + upcoming, excl. cancelled).
     * @return list<array{id:string, label:string, games:Game[], isPlaceholder:bool}>
     */
    private function buildMatchdayEntries(Competition $competition, array $allGames): array
    {
        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        $expectedStages = $adapter !== null
            ? $adapter->getExpectedStages()
            : [Game::STAGE_GROUP];

        $gamesByDate = [];
        $stageOfDate = [];
        foreach ($allGames as $g) {
            $date = substr($g->kickoff_at, 0, 10);
            $gamesByDate[$date][] = $g;
            $stageOfDate[$date] = $g->stage;
        }

        $formatter = Yii::$app->formatter;
        $entries = [];

        foreach ($expectedStages as $stage) {
            $dates = [];
            foreach ($gamesByDate as $date => $_) {
                if ($stageOfDate[$date] === $stage) {
                    $dates[] = $date;
                }
            }

            if ($stage === Game::STAGE_GROUP) {
                foreach ($dates as $idx => $date) {
                    $entries[] = [
                        'id' => $date,
                        'label' => Yii::t('KickoffModule.base', 'Matchday {n} · {date}', [
                            'n' => $idx + 1,
                            'date' => $formatter->asDate($date, 'EEE, d. MMM'),
                        ]),
                        'games' => $gamesByDate[$date],
                        'isPlaceholder' => false,
                    ];
                }
                continue;
            }

            if ($dates === []) {
                $estimated = $adapter !== null ? $adapter->getEstimatedStageDate($competition, $stage) : null;
                $label = $this->stageLabel($stage) . ' · ';
                $label .= $estimated !== null
                    ? Yii::t('KickoffModule.base', '~ {date}', ['date' => $formatter->asDate($estimated, 'EEE, d. MMM')])
                    : Yii::t('KickoffModule.base', 'not drawn yet');
                $entries[] = [
                    'id' => 'stage:' . $stage,
                    'label' => $label,
                    'games' => [],
                    'isPlaceholder' => true,
                ];
                continue;
            }

            foreach ($dates as $idx => $date) {
                $label = $this->stageLabel($stage);
                if (count($dates) > 1) {
                    $label .= ' · ' . Yii::t('KickoffModule.base', 'Day {n}', ['n' => $idx + 1]);
                }
                $label .= ' · ' . $formatter->asDate($date, 'EEE, d. MMM');
                $entries[] = [
                    'id' => $date,
                    'label' => $label,
                    'games' => $gamesByDate[$date],
                    'isPlaceholder' => false,
                ];
            }
        }

        return $entries;
    }

    /**
     * @param list<array{id:string, isPlaceholder:bool}> $entries
     */
    /**
     * Bonus is the natural landing when the user still owes a tip on any open
     * bonus bet, or when all bonus deadlines have passed (the user can then
     * review the resolved bets right away).
     *
     * @param \humhub\modules\kickoff\models\SpecialBet[] $openSpecialBets
     */
    private function shouldDefaultToBonus(array $openSpecialBets, int $userId): bool
    {
        if ($openSpecialBets === []) {
            return true;
        }
        $openIds = array_map(fn($b) => $b->id, $openSpecialBets);
        $tippedCount = (int) SpecialBetTip::find()
            ->where(['user_id' => $userId, 'special_bet_id' => $openIds])
            ->count();
        return $tippedCount < count($openIds);
    }

    private function pickDefaultMatchday(array $entries): ?string
    {
        $today = date('Y-m-d');
        $isDate = fn(string $id): bool => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $id);

        foreach ($entries as $entry) {
            if ($isDate($entry['id']) && $entry['id'] === $today) {
                return $entry['id'];
            }
        }
        foreach ($entries as $entry) {
            if ($isDate($entry['id']) && $entry['id'] > $today) {
                return $entry['id'];
            }
        }
        $past = null;
        foreach ($entries as $entry) {
            if ($isDate($entry['id']) && $entry['id'] < $today) {
                $past = $entry['id'];
            }
        }
        if ($past !== null) {
            return $past;
        }
        return $entries[0]['id'] ?? null;
    }

    private function stageLabel(string $stage): string
    {
        return match ($stage) {
            Game::STAGE_ROUND_OF_32 => Yii::t('KickoffModule.base', 'Round of 32'),
            Game::STAGE_ROUND_OF_16 => Yii::t('KickoffModule.base', 'Round of 16'),
            Game::STAGE_QUARTER => Yii::t('KickoffModule.base', 'Quarter-finals'),
            Game::STAGE_SEMI => Yii::t('KickoffModule.base', 'Semi-finals'),
            Game::STAGE_THIRD_PLACE => Yii::t('KickoffModule.base', 'Third place'),
            Game::STAGE_FINAL => Yii::t('KickoffModule.base', 'Final'),
            default => ucfirst($stage),
        };
    }

    public function actionInfo($slug)
    {
        $competition = $this->findCompetition($slug);
        if (!$competition->hasInfoPage()) {
            throw new NotFoundHttpException();
        }
        return $this->render('info', ['competition' => $competition]);
    }

    public function actionRules($slug)
    {
        $competition = $this->findCompetition($slug);
        $specialBets = SpecialBet::find()
            ->where(['competition_id' => $competition->id])
            ->orderBy(['deadline_at' => SORT_ASC])
            ->all();
        return $this->render('rules', [
            'competition' => $competition,
            'scheme' => $competition->scoringScheme,
            'specialBets' => $specialBets,
        ]);
    }

    public function actionLeaderboard($slug, $page = 1)
    {
        $competition = $this->findCompetition($slug);
        $all = (new LeaderboardService($competition))->compute();

        $perPage = 50;
        $totalCount = count($all);
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        $page = min(max(1, (int) $page), $totalPages);
        $rows = array_slice($all, ($page - 1) * $perPage, $perPage);

        return $this->render('leaderboard', [
            'competition' => $competition,
            'rows' => $rows,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    public function actionMatchTips($slug, $gameId, $page = 1)
    {
        $competition = $this->findCompetition($slug);
        $game = Game::findOne(['id' => (int) $gameId, 'competition_id' => $competition->id]);
        if ($game === null) {
            throw new NotFoundHttpException();
        }

        if (!$competition->tipsVisibleForGame($game)) {
            return $this->renderPartial('_match_tips_locked', ['game' => $game]);
        }

        $perPage = 25;
        $page = max(1, (int) $page);
        $totalCount = (int) Tip::find()->where(['game_id' => $game->id])->count();
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $tips = Tip::find()
            ->where(['game_id' => $game->id])
            ->joinWith(['user'])
            ->orderBy(['kickoff_tip.points' => SORT_DESC, 'kickoff_tip.id' => SORT_ASC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return $this->renderPartial('_match_tips', [
            'competition' => $competition,
            'game' => $game,
            'tips' => $tips,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    public function actionUserHistory($slug, $userId, $page = 1)
    {
        $competition = $this->findCompetition($slug);
        $targetUserId = (int) $userId;
        $user = User::findOne($targetUserId);
        if ($user === null) {
            throw new NotFoundHttpException();
        }

        $perPage = 25;
        $page = max(1, (int) $page);

        $baseTipQuery = Tip::find()
            ->joinWith(['game' => function ($q) use ($competition) {
                $q->andWhere(['kickoff_game.competition_id' => $competition->id]);
            }])
            ->andWhere(['user_id' => $targetUserId])
            ->andWhere(['IS NOT', 'kickoff_tip.points', null]);

        $totalTipCount = (int) (clone $baseTipQuery)->count();
        $totalPages = max(1, (int) ceil($totalTipCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $tips = (clone $baseTipQuery)
            ->orderBy(['kickoff_game.kickoff_at' => SORT_DESC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        // Special bets are typically a handful — show all of them, not paginated.
        $specialBetTips = SpecialBetTip::find()
            ->joinWith(['specialBet' => function ($q) use ($competition) {
                $q->andWhere(['kickoff_special_bet.competition_id' => $competition->id]);
            }])
            ->andWhere(['user_id' => $targetUserId])
            ->orderBy(['kickoff_special_bet.resolved_at' => SORT_DESC])
            ->all();

        return $this->renderPartial('_user_history', [
            'competition' => $competition,
            'user' => $user,
            'tips' => $tips,
            'specialBetTips' => $specialBetTips,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalTipCount' => $totalTipCount,
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

        return $this->redirectToMatchdayView($competition);
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

        return $this->redirectToMatchdayView($competition);
    }

    private function redirectToMatchdayView(Competition $competition)
    {
        $matchday = (string) Yii::$app->request->post('matchday', '');
        $params = ['view', 'slug' => $competition->slug];
        if ($matchday !== '') {
            $params['matchday'] = $matchday;
        }
        return $this->redirect($params);
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
