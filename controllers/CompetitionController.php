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
use humhub\modules\kickoff\services\MatchdayEntries;
use humhub\modules\user\models\User;
use Yii;
use yii\web\ForbiddenHttpException;
use yii\web\NotFoundHttpException;

class CompetitionController extends Controller
{
    /**
     * Read-only actions. Every other action writes tips/bets. The per-competition
     * gate in beforeAction() requires the view tier for the former and the
     * participate tier for the latter — relative to the competition being
     * accessed: public competitions are open to all logged-in members, only
     * restricted ones consult the permissions. Listing the read actions makes
     * the default fail closed — an unlisted new action requires the participate
     * tier rather than silently allowing view-only users to write.
     */
    private const VIEW_ACTIONS = ['view', 'info', 'rules', 'leaderboard', 'match-tips', 'user-history'];

    private ?Competition $competition = null;

    public function getAccessRules()
    {
        // Access is decided per competition in beforeAction() — a competition
        // can be public or restricted — so the controller only requires login.
        return [
            ['login'],
        ];
    }

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Every front-end action is scoped to a competition via its slug. Public
        // competitions are open to all logged-in members; restricted ones require
        // the view tier (read actions) or participate tier (write actions).
        $slug = Yii::$app->request->get('slug');
        if (is_string($slug) && $slug !== '') {
            $competition = Competition::findOne(['slug' => $slug]);
            if ($competition === null) {
                throw new NotFoundHttpException();
            }
            $this->competition = $competition;

            $allowed = in_array($action->id, self::VIEW_ACTIONS, true)
                ? $competition->canView()
                : $competition->canParticipate();
            if (!$allowed) {
                throw new ForbiddenHttpException(
                    Yii::t('KickoffModule.base', 'You don’t have access to this competition.'),
                );
            }

            // Set the title on the view directly: the controller's pageTitle is
            // copied to the view in parent::beforeAction(), which has already run.
            $this->view->setPageTitle($competition->getMenuLabel());
        }

        return true;
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

        $matchdayEntries = MatchdayEntries::build($competition, $allGames);
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

        $tipsByGame = $this->loadTipsByGameId($userId, $matchdayGames);

        $specialBetTipsByBet = $this->loadSpecialBetTips($userId, $allSpecialBets);

        $leaderboardService = new LeaderboardService($competition);

        $matchdayGameIds = array_map(fn($g) => $g->id, $matchdayGames);
        $matchdayLeaderboard = $selectedIsBonus || $matchdayGameIds === []
            ? []
            : $leaderboardService->computeForGames($matchdayGameIds, 10);

        $bonusLeaderboard = $selectedIsBonus && $resolvedSpecialBets !== []
            ? $leaderboardService->computeForSpecialBets(10)
            : [];

        $overallTop = $leaderboardService->compute(10);
        $userOverallRow = $leaderboardService->findUserRank($userId);

        $participation = Participation::findOne(['competition_id' => $competition->id, 'user_id' => $userId]);

        return $this->render('view', [
            'competition' => $competition,
            'matchdayGames' => $matchdayGames,
            'tipsByGame' => $tipsByGame,
            'openSpecialBets' => $openSpecialBets,
            'awaitingSpecialBets' => $awaitingSpecialBets,
            'resolvedSpecialBets' => $resolvedSpecialBets,
            'specialBetTipsByBet' => $specialBetTipsByBet,
            'isParticipating' => $participation !== null,
            'canParticipate' => $competition->canParticipate(),
            'matchdayEntries' => $matchdayEntries,
            'selectedMatchday' => $selectedMatchday,
            'selectedEntry' => $selectedEntry,
            'selectedIsPlaceholder' => $selectedIsPlaceholder,
            'selectedIsBonus' => $selectedIsBonus,
            'prevEntry' => $prevEntry,
            'nextEntry' => $nextEntry,
            'matchdayLeaderboard' => $matchdayLeaderboard,
            'bonusLeaderboard' => $bonusLeaderboard,
            'overallTop' => $overallTop,
            'userOverallRow' => $userOverallRow,
        ]);
    }

    /**
     * Bonus is the natural landing only when the user still has work to do
     * there: at least one bet is still open (deadline not passed) AND the user
     * hasn't tipped all of them yet. If nothing is actionable — every open bet
     * is already tipped, or all deadlines have passed — fall through to a
     * playable matchday instead. The bonus entry stays available in the
     * matchday dropdown for review.
     *
     * @param \humhub\modules\kickoff\models\SpecialBet[] $openSpecialBets
     */
    private function shouldDefaultToBonus(array $openSpecialBets, int $userId): bool
    {
        if ($openSpecialBets === []) {
            return false;
        }
        $openIds = array_map(fn($b) => $b->id, $openSpecialBets);
        $tippedCount = (int) SpecialBetTip::find()
            ->where(['user_id' => $userId, 'special_bet_id' => $openIds])
            ->count();
        return $tippedCount < count($openIds);
    }

    private function pickDefaultMatchday(array $entries): ?string
    {
        $isDated = function (array $entry): bool {
            return $entry['games'] !== []
                && empty($entry['isPlaceholder'])
                && empty($entry['isBonus']);
        };

        // Earliest matchday that still has at least one game whose kickoff is
        // in the future — i.e. the next-best one the user can still tip.
        foreach ($entries as $entry) {
            if (!$isDated($entry)) {
                continue;
            }
            foreach ($entry['games'] as $game) {
                if (!$game->isKickoffPassed()) {
                    return $entry['id'];
                }
            }
        }

        // Nothing tippable left — fall back to the most recent past matchday
        // so the user lands on something with content instead of an empty
        // placeholder.
        $past = null;
        foreach ($entries as $entry) {
            if (!$isDated($entry)) {
                continue;
            }
            $past = $entry['id'];
        }
        if ($past !== null) {
            return $past;
        }
        return $entries[0]['id'] ?? null;
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

    public function actionLeaderboard($slug, $page = 1, $matchday = null)
    {
        $competition = $this->findCompetition($slug);
        $service = new LeaderboardService($competition);

        // Only matchdays with actually-scheduled games are pickable as
        // leaderboard filters — placeholder rounds (TBD KO stages) have no
        // games yet, so a per-matchday leaderboard would always be empty.
        $allEntries = MatchdayEntries::forCompetition($competition);
        $matchdayOptions = array_values(array_filter(
            $allEntries,
            fn(array $e) => empty($e['isPlaceholder']) && $e['games'] !== [],
        ));

        $selectedMatchday = null;
        $selectedGameIds = [];
        if ($matchday !== null && $matchday !== '') {
            foreach ($matchdayOptions as $entry) {
                if ($entry['id'] === $matchday) {
                    $selectedMatchday = $entry;
                    $selectedGameIds = array_map(fn($g) => $g->id, $entry['games']);
                    break;
                }
            }
        }

        $perPage = 50;
        if ($selectedMatchday !== null) {
            $totalCount = $service->countParticipantsForGames($selectedGameIds);
        } else {
            $totalCount = $service->countParticipants();
        }
        $totalPages = max(1, (int) ceil(max(1, $totalCount) / $perPage));
        $page = min(max(1, (int) $page), $totalPages);

        // Aggregation runs over every participation regardless — competition
        // ranks need the global order. What we skip is hydrating User
        // objects for every entry: only the 50 rows we'll actually render
        // get their User model loaded.
        if ($selectedMatchday !== null) {
            $rows = $service->computeForGames($selectedGameIds, $perPage, ($page - 1) * $perPage);
        } else {
            $rows = $service->compute($perPage, ($page - 1) * $perPage);
        }

        return $this->render('leaderboard', [
            'competition' => $competition,
            'rows' => $rows,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'matchdayOptions' => $matchdayOptions,
            'selectedMatchday' => $selectedMatchday,
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
        // `points IS NOT NULL` filter mirrors the match-tip query above: we only
        // expose tips that have already been scored, i.e. their corresponding
        // bet is resolved. Without this clause, anyone could call
        // `/c/<slug>/user/<id>` and pull back another participant's still-secret
        // Weltmeister/group-winner tip — the view filters by points in PHP, but
        // defense in depth says filter at the source.
        $specialBetTips = SpecialBetTip::find()
            ->joinWith(['specialBet' => function ($q) use ($competition) {
                $q->andWhere(['kickoff_special_bet.competition_id' => $competition->id]);
            }])
            ->andWhere(['user_id' => $targetUserId])
            ->andWhere(['IS NOT', 'kickoff_special_bet_tip.points', null])
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
            // Reject non-numeric strings explicitly. `(int) "abc"` would cast to
            // 0 and silently turn into a 0:0 tip, which is technically valid
            // and would pass model validation (range 0–99) — the user would
            // see "saved" and not realize their input was discarded.
            if (!is_numeric($home) || !is_numeric($away)) {
                $errors++;
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

        // Only record participation if the user actually managed to save at
        // least one tip — keeps the participation table free of ghost rows
        // created by users who clicked submit on an all-past-deadline form.
        if ($saved > 0) {
            $this->ensureParticipation($competition, $userId);
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
     * Single special-bet endpoint for autosave. Empty value clears the tip.
     */
    public function actionSpecialBetTip($slug)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

        $betId = (int) Yii::$app->request->post('bet_id');
        $value = trim((string) Yii::$app->request->post('value', ''));

        $bet = SpecialBet::findOne(['id' => $betId, 'competition_id' => $competition->id]);
        if ($bet === null) {
            Yii::$app->response->statusCode = 404;
            return $this->asJson(['ok' => false, 'error' => 'bet_not_found']);
        }
        if ($bet->isDeadlinePassed() || $bet->isResolved()) {
            Yii::$app->response->statusCode = 409;
            return $this->asJson(['ok' => false, 'error' => 'deadline_passed']);
        }

        if ($value === '') {
            SpecialBetTip::deleteAll(['special_bet_id' => $bet->id, 'user_id' => $userId]);
            return $this->asJson(['ok' => true, 'cleared' => true]);
        }

        $type = Module::instance()->getSpecialBetTypeRegistry()->get($bet->type);
        if ($type !== null && !$type->validateValue($value, $bet)) {
            Yii::$app->response->statusCode = 400;
            return $this->asJson(['ok' => false, 'error' => 'invalid_value']);
        }

        $tip = SpecialBetTip::findOne(['special_bet_id' => $bet->id, 'user_id' => $userId])
            ?? new SpecialBetTip(['special_bet_id' => $bet->id, 'user_id' => $userId]);
        $tip->value = $value;
        if (!$tip->save()) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson(['ok' => false, 'error' => 'save_failed', 'details' => $tip->getFirstErrors()]);
        }

        // Record participation only after the save lands — earlier placement
        // would leave a ghost participation if the save errored.
        $this->ensureParticipation($competition, $userId);

        return $this->asJson(['ok' => true]);
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

        $tip = Tip::findOne(['game_id' => $game->id, 'user_id' => $userId])
            ?? new Tip(['game_id' => $game->id, 'user_id' => $userId]);
        $tip->home_score = (int) $home;
        $tip->away_score = (int) $away;
        if (!$tip->save()) {
            Yii::$app->response->statusCode = 422;
            return $this->asJson(['ok' => false, 'error' => 'save_failed', 'details' => $tip->getFirstErrors()]);
        }

        // Record participation only after the save lands — earlier placement
        // would leave a ghost participation if the save errored.
        $this->ensureParticipation($competition, $userId);

        return $this->asJson(['ok' => true]);
    }

    public function actionSpecialBetTips($slug)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($slug);
        $userId = (int) Yii::$app->user->id;

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

        // Same as the bulk tip endpoint: only create a Participation when the
        // user actually got at least one bet through.
        if ($saved > 0) {
            $this->ensureParticipation($competition, $userId);
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
        // beforeAction() already resolved and access-checked the competition for
        // this slug — reuse it instead of querying again.
        if ($this->competition !== null && $this->competition->slug === $slug) {
            return $this->competition;
        }
        $competition = Competition::findOne(['slug' => $slug]);
        if ($competition === null) {
            throw new NotFoundHttpException();
        }
        return $competition;
    }
}
