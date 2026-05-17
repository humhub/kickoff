<?php

namespace humhub\modules\kickoff\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\kickoff\adapters\FootballDataOrgAdapter;
use humhub\modules\kickoff\adapters\SyncReport;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\Game;
use humhub\modules\kickoff\models\ScoringScheme;
use humhub\modules\kickoff\models\SpecialBet;
use humhub\modules\kickoff\Module;
use humhub\modules\kickoff\services\ScoringService;
use humhub\modules\kickoff\services\SpecialBetResolver;
use Yii;
use yii\web\NotFoundHttpException;

class AdminController extends Controller
{
    public function actionIndex()
    {
        $competitions = Competition::find()
            ->orderBy(['is_test' => SORT_ASC, 'starts_at' => SORT_DESC, 'id' => SORT_DESC])
            ->all();
        return $this->render('index', ['competitions' => $competitions]);
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
            return $this->redirect(['view', 'id' => $competition->id]);
        }

        return $this->render('create', ['competition' => $competition]);
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
        $competition->updateAttributes(['last_synced_at' => date('Y-m-d H:i:s')]);
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
        $competition->updateAttributes(['last_synced_at' => date('Y-m-d H:i:s')]);
        $this->flashReport($report, Yii::t('KickoffModule.base', 'Results sync'));

        if ($report->isSuccess() && $report->updated > 0) {
            $tipCount = (new ScoringService($competition))->scoreAllFinishedGames();
            Yii::$app->session->setFlash('info', Yii::t(
                'KickoffModule.base',
                '{n} tip(s) scored.',
                ['n' => $tipCount],
            ));
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
        $pastTimestamp = date('Y-m-d H:i:s', time() - 120 * 60);
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
        }
        Yii::$app->session->setFlash('success', Yii::t(
            'KickoffModule.base',
            'Advanced matchday {date}: {count} game(s) finished, {scored} tip(s) scored.',
            ['date' => $matchday, 'count' => $report->updated, 'scored' => $scored],
        ));

        return $this->redirect(['view', 'id' => $competition->id]);
    }

    public function actionRecomputePoints($id)
    {
        $this->forcePostRequest();
        $competition = $this->findCompetition($id);
        $service = new ScoringService($competition);
        $tipUpdates = $service->scoreAllFinishedGames();
        $specialUpdates = $service->scoreAllResolvedSpecialBets();
        Yii::$app->session->setFlash('success', Yii::t(
            'KickoffModule.base',
            'Recomputed: {tips} tip(s), {special} special bet tip(s) updated.',
            ['tips' => $tipUpdates, 'special' => $specialUpdates],
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
        $bet->deadline_at = $competition->starts_at ?: date('Y-m-d H:i:s');

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
                $bet->resolved_at = date('Y-m-d H:i:s');
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
        $fallbackDeadline = $proto->deadline_at ?: ($competition->starts_at ?: date('Y-m-d H:i:s'));
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
