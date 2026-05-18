<?php

namespace humhub\modules\kickoff\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\kickoff\adapters\FootballDataOrgAdapter;
use humhub\modules\kickoff\adapters\HumHubApiAdapter;
use humhub\modules\kickoff\adapters\SyncReport;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\models\Team;
use humhub\modules\kickoff\Module;
use humhub\modules\kickoff\services\KickoffTime;
use humhub\modules\kickoff\services\MatchdayBonusService;
use humhub\modules\kickoff\services\ScoringService;
use humhub\modules\kickoff\services\SpecialBetResolver;
use Yii;
use yii\web\NotFoundHttpException;

class AdminController extends Controller
{
    public function actionIndex($tests = null)
    {
        $showTests = (string) $tests === '1';

        // Always count tests so the toggle in the view can show "Show tests (N)"
        // / "Hide tests" without an extra render-time query.
        $testCount = (int) Competition::find()->where(['is_test' => 1])->count();

        $competitions = Competition::find()
            ->where(['is_test' => $showTests ? 1 : 0])
            ->orderBy(['starts_at' => SORT_DESC, 'id' => SORT_DESC])
            ->all();

        // WM-2026 banner is a production concern — never surface it in the
        // test view, where it'd be a confusing CTA.
        $wm2026 = $showTests ? null : $this->findWm2026Competition($competitions);

        return $this->render('index', [
            'competitions' => $competitions,
            'wm2026Competition' => $wm2026,
            'showTests' => $showTests,
            'testCount' => $testCount,
        ]);
    }

    public function actionSettings()
    {
        $settings = Module::instance()->settings;
        if (Yii::$app->request->isPost) {
            $token = trim((string) Yii::$app->request->post('football_data_token', ''));
            $settings->set(FootballDataOrgAdapter::SETTING_TOKEN, $token !== '' ? $token : null);
            $this->view->saved();
            return $this->redirect(['settings']);
        }
        return $this->render('settings', [
            'footballDataToken' => (string) ($settings->get(FootballDataOrgAdapter::SETTING_TOKEN) ?? ''),
        ]);
    }

    /**
     * One-click setup for the FIFA World Cup 2026. Idempotent: if the
     * competition doesn't exist, create it and run the full sync; if it
     * already exists, just top up any missing fixtures and metadata (useful
     * for repairing a half-completed setup, e.g. when football-data's draw
     * arrived late or a previous run got interrupted mid-flight).
     *
     * Metadata application runs even when fixtures had partial errors —
     * losing the special-bet rows because one team failed to save would be
     * worse than the half-good state we're recovering from.
     */
    public function actionSetupWm2026()
    {
        $this->forcePostRequest();

        $registry = Module::instance()->getAdapterRegistry();
        $adapter = $registry->get(HumHubApiAdapter::KEY);
        if (!$adapter instanceof HumHubApiAdapter) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                'HumHub data service adapter is not available.',
            ));
            return $this->redirect(['index']);
        }

        $competition = $this->findWm2026Competition();
        $isNew = $competition === null;

        if ($isNew) {
            $defaultScheme = ScoringScheme::find()->orderBy(['id' => SORT_ASC])->one();
            if ($defaultScheme === null) {
                Yii::$app->session->setFlash('error', Yii::t(
                    'KickoffModule.base',
                    'No scoring scheme exists yet. Create one before running the WM 2026 setup.',
                ));
                return $this->redirect(['index']);
            }

            $competition = new Competition();
            $competition->name = 'FIFA World Cup 2026';
            $competition->slug = $this->ensureUniqueSlug('wm2026');
            $competition->type = Competition::TYPE_TOURNAMENT;
            $competition->season = '2026';
            $competition->status = Competition::STATUS_ACTIVE;
            $competition->ko_scoring_mode = Competition::KO_REGULAR_TIME;
            $competition->data_source = HumHubApiAdapter::KEY;
            $competition->setDataSourceConfig([
                'external_competition_id' => HumHubApiAdapter::COMPETITION_WM2026,
            ]);
            $competition->scoring_scheme_id = $defaultScheme->id;
            $competition->is_test = 0;
            $competition->starts_at = '2026-06-11 00:00:00';
            $competition->ends_at = '2026-07-19 23:59:59';
            $competition->show_in_main_menu = 1;
            $competition->tips_visible_before_kickoff = 0;
            if (!Yii::$app->user->isGuest) {
                $competition->created_by = Yii::$app->user->id;
            }

            if (!$competition->save()) {
                Yii::$app->session->setFlash('error', Yii::t(
                    'KickoffModule.base',
                    'Could not create competition: {errors}',
                    ['errors' => implode(', ', $competition->getFirstErrors())],
                ));
                return $this->redirect(['index']);
            }
        }

        $fixturesReport = $adapter->syncFixtures($competition);
        $competition->updateAttributes(['last_synced_at' => KickoffTime::nowDb()]);

        $metadataReport = $adapter->applyMetadata($competition);

        $type = $fixturesReport->isSuccess() && $metadataReport->isSuccess() ? 'success' : 'warning';
        $message = Yii::t(
            'KickoffModule.base',
            'FIFA World Cup 2026 setup ran. Fixtures: {fixtures}. Ratings + special bets: {metadata}.',
            [
                'fixtures' => $fixturesReport->summary(),
                'metadata' => $metadataReport->summary(),
            ],
        );
        $errors = array_merge($fixturesReport->errors, $metadataReport->errors);
        if ($errors !== []) {
            $message .= ' — ' . implode('; ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $message .= ' (+' . (count($errors) - 5) . ' more — see PHP error log)';
            }
        }
        Yii::$app->session->setFlash($type, $message);
        return $this->redirect(['view', 'id' => $competition->id]);
    }

    /**
     * Finds the WM-2026 competition (humhub-api source with the canonical
     * external_competition_id). Used both to render the setup button only when
     * the competition doesn't yet exist and to short-circuit the setup action
     * when re-clicked. Accepts an optional pre-loaded list to avoid a second
     * DB roundtrip when the caller already has all competitions in memory.
     *
     * @param Competition[]|null $preloaded
     */
    private function findWm2026Competition(?array $preloaded = null): ?Competition
    {
        $candidates = $preloaded ?? Competition::find()
            ->where(['data_source' => HumHubApiAdapter::KEY])
            ->all();
        foreach ($candidates as $competition) {
            if ($competition->data_source !== HumHubApiAdapter::KEY) {
                continue;
            }
            $config = $competition->getDataSourceConfig();
            if (($config['external_competition_id'] ?? '') === HumHubApiAdapter::COMPETITION_WM2026) {
                return $competition;
            }
        }
        return null;
    }

    /**
     * Returns the requested slug if it's free, otherwise appends a suffix until
     * a unique value is found. Lets the WM-setup action stay idempotent even if
     * an admin manually created (and deleted, then recreated) competitions
     * with the canonical slug.
     */
    private function ensureUniqueSlug(string $base): string
    {
        $slug = $base;
        $i = 2;
        while (Competition::find()->where(['slug' => $slug])->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }
        return $slug;
    }

    public function actionView($id, $page = 1)
    {
        $competition = $this->findCompetition($id);

        $perPage = 50;
        $page = max(1, (int) $page);
        $gameQuery = $competition->getGames();
        $totalCount = (int) (clone $gameQuery)->count();
        $totalPages = max(1, (int) ceil($totalCount / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $games = $gameQuery
            ->with(['homeTeam', 'awayTeam'])
            ->orderBy(['kickoff_at' => SORT_ASC])
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->all();

        return $this->render('view', [
            'competition' => $competition,
            'games' => $games,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    public function actionCreate()
    {
        $competition = new Competition();
        $competition->status = Competition::STATUS_DRAFT;
        $competition->ko_scoring_mode = Competition::KO_REGULAR_TIME;
        $competition->data_source = Competition::DATA_SOURCE_MANUAL;
        $competition->type = Competition::TYPE_TOURNAMENT;
        $competition->is_test = 0;

        $defaultScheme = ScoringScheme::find()->orderBy(['id' => SORT_ASC])->one();
        if ($defaultScheme !== null) {
            $competition->scoring_scheme_id = $defaultScheme->id;
        }
        if (!Yii::$app->user->isGuest) {
            $competition->created_by = Yii::$app->user->id;
        }

        if ($competition->load(Yii::$app->request->post()) && $competition->save()) {
            $this->view->saved();
            $this->autoLoadInitialSchedule($competition);
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        return $this->render('create', ['competition' => $competition]);
    }

    /**
     * Runs an initial fixture sync right after a competition is created so
     * admins don't have to chase a separate button for the first import. No-op
     * for the manual adapter; warnings (not errors) on failure so a transient
     * upstream problem doesn't block the create flow — the admin can retry via
     * the "Check for schedule changes" button.
     */
    private function autoLoadInitialSchedule(Competition $competition): void
    {
        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        if ($adapter === null) {
            return;
        }
        try {
            $report = $adapter->syncFixtures($competition);
            $competition->updateAttributes(['last_synced_at' => KickoffTime::nowDb()]);
        } catch (\Throwable $e) {
            Yii::error('Initial schedule sync failed: ' . $e->getMessage(), __METHOD__);
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                'Could not load the schedule automatically: {error}. Use "Check for schedule changes" to retry.',
                ['error' => $e->getMessage()],
            ));
            return;
        }
        if ($report->created > 0) {
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                'Schedule loaded: {summary}',
                ['summary' => $report->summary()],
            ));
        } elseif (!$report->isSuccess()) {
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                'Schedule auto-load reported: {summary} — {errors}',
                ['summary' => $report->summary(), 'errors' => implode('; ', $report->errors)],
            ));
        }
    }

    public function actionUpdate($id)
    {
        $competition = $this->findCompetition($id);

        if ($competition->load(Yii::$app->request->post()) && $competition->save()) {
            $this->view->saved();
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        return $this->render('update', ['competition' => $competition]);
    }

    public function actionDelete($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);
        $wasTest = $competition->isTest();
        $competition->delete();
        Yii::$app->session->setFlash('success', $wasTest
            ? Yii::t('KickoffModule.base', 'Test competition deleted.')
            : Yii::t('KickoffModule.base', 'Competition deleted.'));
        return $this->redirect(['index']);
    }

    public function actionSyncFixtures($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);
        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        if ($adapter === null) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                'No adapter registered for data source "{src}".',
                ['src' => $competition->data_source],
            ));
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        $report = $adapter->syncFixtures($competition);
        $competition->updateAttributes(['last_synced_at' => KickoffTime::nowDb()]);
        $this->flashReport($report, Yii::t('KickoffModule.base', 'Schedule sync'));
        return $this->redirect(['view', 'id' => $competition->id]);
    }

    public function actionSyncResults($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);
        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        if ($adapter === null) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                'No adapter registered for data source "{src}".',
                ['src' => $competition->data_source],
            ));
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        $report = $adapter->syncResults($competition);
        $competition->updateAttributes(['last_synced_at' => KickoffTime::nowDb()]);
        $this->flashReport($report, Yii::t('KickoffModule.base', 'Results sync'));

        if ($report->isSuccess() && $report->updated > 0) {
            $tipCount = (new ScoringService($competition))->scoreAllFinishedGames();
            $awarded = (new MatchdayBonusService($competition))->awardForCompleteMatchdays();
            $msg = Yii::t('KickoffModule.base', '{n} tip(s) scored.', ['n' => $tipCount]);
            if ($awarded > 0) {
                $msg .= ' ' . Yii::t('KickoffModule.base', '{n} matchday-winner bonus(es) awarded.', ['n' => $awarded]);
            }
            Yii::$app->session->setFlash('info', $msg);
        }

        return $this->redirect(['view', 'id' => $competition->id]);
    }

    /**
     * Test-only: advances the next pending matchday by setting its games' kickoff_at
     * to the past and immediately running results sync + scoring.
     */
    public function actionFastForward($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);

        if (!$competition->isTest()) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                'Fast-forward is only available on test competitions.',
            ));
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        $adapter = Module::instance()->getAdapterRegistry()->forCompetition($competition);
        if ($adapter === null) {
            Yii::$app->session->setFlash('error', Yii::t(
                'KickoffModule.base',
                'No adapter registered for data source "{src}".',
                ['src' => $competition->data_source],
            ));
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        $nextKickoff = Game::find()
            ->where(['competition_id' => $competition->id, 'status' => Game::STATUS_SCHEDULED])
            ->min('kickoff_at');

        if ($nextKickoff === null) {
            // No scheduled games left — try to advance the bracket by running syncResults
            // (which calls advanceBracket internally even when nothing is scored).
            $report = $adapter->syncResults($competition);
            if ($report->created > 0) {
                Yii::$app->session->setFlash('success', Yii::t(
                    'KickoffModule.base',
                    'Advanced bracket: {summary}',
                    ['summary' => $report->summary()],
                ));
            } else {
                Yii::$app->session->setFlash('info', Yii::t(
                    'KickoffModule.base',
                    'No more games to advance.',
                ));
            }
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        $matchday = substr((string) $nextKickoff, 0, 10);
        $games = Game::find()
            ->where(['competition_id' => $competition->id, 'status' => Game::STATUS_SCHEDULED])
            ->andWhere(['between', 'kickoff_at', $matchday . ' 00:00:00', $matchday . ' 23:59:59'])
            ->all();

        // Push kickoff well past the live window (~115 min) so the mock jumps
        // directly to FINISHED on the next sync instead of going through LIVE.
        $pastTimestamp = KickoffTime::dbAt(time() - 120 * 60);
        foreach ($games as $game) {
            $game->updateAttributes([
                'kickoff_at' => $pastTimestamp,
                'status' => Game::STATUS_SCHEDULED,
            ]);
        }

        $report = $adapter->syncResults($competition);
        $scored = 0;
        if ($report->isSuccess() && $report->updated > 0) {
            $scored = (new ScoringService($competition))->scoreAllFinishedGames();
            (new MatchdayBonusService($competition))->awardForCompleteMatchdays();
        }
        Yii::$app->session->setFlash('success', Yii::t(
            'KickoffModule.base',
            'Advanced matchday {date}: {count} game(s) finished, {scored} tip(s) scored.',
            ['date' => $matchday, 'count' => $report->updated, 'scored' => $scored],
        ));

        return $this->redirect(['view', 'id' => $competition->id]);
    }

    /**
     * Populates fifa_points / elo_rating on this competition's teams from the
     * bundled WM 2026 snapshot. Each snapshot entry can match multiple
     * country-code variants (ISO-2, ISO-3, FIFA-style) since adapters differ.
     * Skips teams already rated so manual overrides stick.
     */
    public function actionApplyDefaultRatings($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);

        $snapshot = require Yii::getAlias('@humhub/modules/kickoff/data/wm2026_ratings.php');
        if (!is_array($snapshot) || $snapshot === []) {
            Yii::$app->session->setFlash('error', 'Ratings snapshot not found.');
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        // Flatten the snapshot to a code → entry lookup so a team's country
        // code can be looked up in O(1) regardless of variant.
        $lookup = [];
        foreach ($snapshot as $entry) {
            foreach ((array) ($entry['codes'] ?? []) as $code) {
                $lookup[strtoupper((string) $code)] = $entry;
            }
        }

        $teams = Team::find()
            ->innerJoin('kickoff_competition_team ct', 'ct.team_id = kickoff_team.id')
            ->where(['ct.competition_id' => $competition->id])
            ->all();

        $matched = 0;
        $unmatchedCodes = [];
        foreach ($teams as $team) {
            $code = strtoupper((string) $team->country_code);
            if ($code === '' || !isset($lookup[$code])) {
                if ($code !== '') {
                    $unmatchedCodes[$code] = ($unmatchedCodes[$code] ?? 0) + 1;
                }
                continue;
            }
            $entry = $lookup[$code];
            $changed = false;
            if ($team->fifa_points === null && isset($entry['fifa'])) {
                $team->fifa_points = (int) $entry['fifa'];
                $changed = true;
            }
            if ($team->elo_rating === null && isset($entry['elo'])) {
                $team->elo_rating = (int) $entry['elo'];
                $changed = true;
            }
            if ($changed && $team->save()) {
                $matched++;
            }
        }

        Yii::$app->session->setFlash('success', Yii::t(
            'KickoffModule.base',
            'Applied default ratings to {n} team(s).',
            ['n' => $matched],
        ));
        if ($unmatchedCodes !== []) {
            ksort($unmatchedCodes);
            $codesList = implode(', ', array_keys($unmatchedCodes));
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                'No snapshot entry for country code(s): {codes}. Set ratings manually on those teams or extend data/wm2026_ratings.php.',
                ['codes' => $codesList],
            ));
        }
        return $this->redirect(['view', 'id' => $competition->id]);
    }

    public function actionRecomputePoints($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);
        $service = new ScoringService($competition);
        $tipUpdates = $service->scoreAllFinishedGames();
        $specialUpdates = $service->scoreAllResolvedSpecialBets();
        // Matchday bonuses live on top of per-tip scoring. A full recompute
        // wipes and re-awards them so a changed scheme value or a corrected
        // tip propagates cleanly into the leaderboard.
        $bonusAwarded = (new MatchdayBonusService($competition))->recompute();
        Yii::$app->session->setFlash('success', Yii::t(
            'KickoffModule.base',
            'Recomputed: {tips} tip(s), {special} special bet tip(s), {bonus} matchday-winner bonus(es).',
            ['tips' => $tipUpdates, 'special' => $specialUpdates, 'bonus' => $bonusAwarded],
        ));
        return $this->redirect(['view', 'id' => $competition->id]);
    }

    public function actionSpecialBetCreate($competitionId)
    {
        $competition = $this->findCompetition($competitionId);
        $bet = new SpecialBet();
        $bet->competition_id = $competition->id;
        $bet->type = SpecialBet::TYPE_WINNER;
        $bet->points = Module::instance()->getSpecialBetTypeRegistry()
            ->requireType(SpecialBet::TYPE_WINNER)->getDefaultPoints();
        $bet->deadline_at = $competition->starts_at ?: KickoffTime::nowDb();

        if ($this->loadAndSaveSpecialBet($bet, $competition)) {
            return $this->redirect(['special-bets', 'id' => $competition->id]);
        }
        return $this->render('special-bet/create', ['bet' => $bet, 'competition' => $competition]);
    }

    public function actionSpecialBetUpdate($id)
    {
        $bet = $this->findSpecialBet($id);
        $competition = $bet->competition;

        if ($this->loadAndSaveSpecialBet($bet, $competition)) {
            return $this->redirect(['special-bets', 'id' => $competition->id]);
        }
        return $this->render('special-bet/update', ['bet' => $bet, 'competition' => $competition]);
    }

    public function actionSpecialBets($id)
    {
        $competition = $this->findCompetition($id);
        $specialBets = $competition->getSpecialBets()
            ->orderBy(['deadline_at' => SORT_ASC])
            ->all();
        return $this->render('special-bets', [
            'competition' => $competition,
            'specialBets' => $specialBets,
        ]);
    }

    public function actionSpecialBetAutoResolve($competitionId)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($competitionId);
        $resolved = (new SpecialBetResolver())->autoResolveAll($competition);
        if ($resolved > 0) {
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                'Auto-resolved {n} special bet(s).',
                ['n' => $resolved],
            ));
        } else {
            Yii::$app->session->setFlash('info', Yii::t(
                'KickoffModule.base',
                'No special bets could be auto-resolved yet — preconditions not met (e.g. group stage still running or final still tied).',
            ));
        }
        return $this->redirect(['special-bets', 'id' => $competition->id]);
    }

    public function actionSpecialBetDelete($id)
    {
        $this->forcePostRequest();
        $bet = $this->findSpecialBet($id);
        $competitionId = $bet->competition_id;

        // Group-winner bets are managed as a set (one per group). Deleting one
        // would leave a confusing partial set behind — wipe all siblings.
        if ($bet->type === SpecialBet::TYPE_GROUP_WINNER) {
            $siblings = SpecialBet::find()
                ->where(['competition_id' => $competitionId, 'type' => SpecialBet::TYPE_GROUP_WINNER])
                ->all();
            $n = 0;
            foreach ($siblings as $sibling) {
                if ($sibling->delete()) {
                    $n++;
                }
            }
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                'Deleted {n} group-winner bet(s).',
                ['n' => $n],
            ));
        } else {
            $bet->delete();
            Yii::$app->session->setFlash('success', Yii::t('KickoffModule.base', 'Special bet deleted.'));
        }

        return $this->redirect(['special-bets', 'id' => $competitionId]);
    }

    public function actionSpecialBetResolve($id)
    {
        $bet = $this->findSpecialBet($id);
        $competition = $bet->competition;

        if (Yii::$app->request->isPost) {
            $value = trim((string) Yii::$app->request->post('resolved_value', ''));
            if ($value === '') {
                Yii::$app->session->setFlash('error', Yii::t(
                    'KickoffModule.base',
                    'Pick a resolution value before saving.',
                ));
            } else {
                $bet->resolved_value = $value;
                $bet->resolved_at = KickoffTime::nowDb();
                if ($bet->save()) {
                    $scored = (new ScoringService($competition))->scoreSpecialBet($bet);
                    Yii::$app->session->setFlash('success', Yii::t(
                        'KickoffModule.base',
                        'Special bet resolved. {n} tip(s) scored.',
                        ['n' => $scored],
                    ));
                    return $this->redirect(['special-bets', 'id' => $competition->id]);
                }
            }
        }

        return $this->render('special-bet/resolve', ['bet' => $bet, 'competition' => $competition]);
    }

    private function loadAndSaveSpecialBet(SpecialBet $bet, Competition $competition): bool
    {
        if (!$bet->load(Yii::$app->request->post())) {
            return false;
        }
        $registry = Module::instance()->getSpecialBetTypeRegistry();
        $type = $registry->get($bet->type);

        // Group winners are always bulk-created: one bet per group, skipping
        // existing groups so we never duplicate.
        if ($bet->isNewRecord && $bet->type === SpecialBet::TYPE_GROUP_WINNER) {
            return $this->bulkCreateGroupWinnerBets($competition, $bet);
        }

        // Singleton type (Tournament winner): refuse a second one.
        if ($bet->isNewRecord && $bet->type === SpecialBet::TYPE_WINNER) {
            $exists = SpecialBet::find()
                ->where(['competition_id' => $competition->id, 'type' => $bet->type])
                ->exists();
            if ($exists) {
                Yii::$app->session->setFlash('error', Yii::t(
                    'KickoffModule.base',
                    'A "{label}" bet already exists for this competition.',
                    ['label' => $type !== null ? $type->getLabel() : $bet->type],
                ));
                return false;
            }
        }

        if ($type !== null) {
            if (!$type->needsGroupLabel()) {
                $bet->group_label = null;
            }
            $bet->setOptions($type->buildOptions($competition, $bet));
        }
        if ($bet->save()) {
            // Group-winner bets are a set with shared semantics — keep points
            // in lockstep so the admin only edits one row, not 12.
            if ($bet->type === SpecialBet::TYPE_GROUP_WINNER) {
                SpecialBet::updateAll(
                    ['points' => (int) $bet->points],
                    [
                        'and',
                        ['competition_id' => $competition->id, 'type' => SpecialBet::TYPE_GROUP_WINNER],
                        ['<>', 'id', $bet->id],
                    ],
                );
            }
            $this->view->saved();
            return true;
        }
        return false;
    }

    /**
     * Creates one Group-winner bet per group that doesn't have one yet, using
     * each group's first kickoff as the per-bet deadline. Returns true so the
     * caller redirects back to the list — the flash carries the outcome.
     */
    private function bulkCreateGroupWinnerBets(Competition $competition, SpecialBet $proto): bool
    {
        // Source the group list from games — it's the authoritative signal for
        // "this competition actually has a group X" and works even if
        // CompetitionTeam.group_label hasn't been populated yet (e.g. older
        // imports before the football-data adapter learned to propagate it).
        $groups = (new \yii\db\Query())
            ->select('group_label')
            ->from('kickoff_game')
            ->where([
                'competition_id' => $competition->id,
                'stage' => Game::STAGE_GROUP,
            ])
            ->andWhere(['IS NOT', 'group_label', null])
            ->andWhere(['<>', 'group_label', ''])
            ->distinct()
            ->orderBy('group_label')
            ->column();

        if ($groups === []) {
            Yii::$app->session->setFlash('warning', Yii::t(
                'KickoffModule.base',
                'No groups defined for this competition.',
            ));
            return true;
        }

        $existing = array_flip(array_filter(SpecialBet::find()
            ->select('group_label')
            ->where(['competition_id' => $competition->id, 'type' => SpecialBet::TYPE_GROUP_WINNER])
            ->column()));

        $registry = Module::instance()->getSpecialBetTypeRegistry();
        $type = $registry->get(SpecialBet::TYPE_GROUP_WINNER);
        $fallbackDeadline = $proto->deadline_at ?: ($competition->starts_at ?: KickoffTime::nowDb());
        $points = (int) $proto->points > 0
            ? (int) $proto->points
            : ($type !== null ? $type->getDefaultPoints() : 5);

        $created = 0;
        foreach ($groups as $groupLabel) {
            if (isset($existing[$groupLabel])) {
                continue;
            }
            $firstGame = Game::find()
                ->where([
                    'competition_id' => $competition->id,
                    'stage' => Game::STAGE_GROUP,
                    'group_label' => $groupLabel,
                ])
                ->orderBy(['kickoff_at' => SORT_ASC])
                ->one();

            $bet = new SpecialBet();
            $bet->competition_id = $competition->id;
            $bet->type = SpecialBet::TYPE_GROUP_WINNER;
            $bet->group_label = $groupLabel;
            $bet->points = $points;
            $bet->deadline_at = $firstGame !== null ? $firstGame->kickoff_at : $fallbackDeadline;
            if ($type !== null) {
                $bet->setOptions($type->buildOptions($competition, $bet));
            }
            if ($bet->save()) {
                $created++;
            }
        }

        if ($created > 0) {
            Yii::$app->session->setFlash('success', Yii::t(
                'KickoffModule.base',
                'Created {n} group-winner bet(s).',
                ['n' => $created],
            ));
        } else {
            Yii::$app->session->setFlash('info', Yii::t(
                'KickoffModule.base',
                'All groups already have a group-winner bet.',
            ));
        }
        return true;
    }

    private function findSpecialBet(int|string $id): SpecialBet
    {
        $bet = SpecialBet::findOne((int) $id);
        if ($bet === null) {
            throw new NotFoundHttpException();
        }
        return $bet;
    }

    private function findCompetition(int|string $id): Competition
    {
        $competition = Competition::findOne((int) $id);
        if ($competition === null) {
            throw new NotFoundHttpException();
        }
        return $competition;
    }

    private function flashReport(SyncReport $report, string $label): void
    {
        $message = $label . ': ' . $report->summary();
        if ($report->isSuccess()) {
            Yii::$app->session->setFlash('success', $message);
        } else {
            Yii::$app->session->setFlash('error', $message . ' — ' . implode('; ', $report->errors));
        }
    }
}
