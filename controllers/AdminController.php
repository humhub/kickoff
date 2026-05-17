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
use Yii;
use yii\web\ForbiddenHttpException;
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

    public function actionView($id)
    {
        return $this->render('view', ['competition' => $this->findCompetition($id)]);
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
        if (!$competition->isTest()) {
            throw new ForbiddenHttpException(
                Yii::t('KickoffModule.base', 'Only test competitions can be deleted. Archive others instead.'),
            );
        }
        $competition->delete();
        Yii::$app->session->setFlash('success', Yii::t('KickoffModule.base', 'Test competition deleted.'));
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
        $this->flashReport($report, Yii::t('KickoffModule.base', 'Fixtures sync'));
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

        $pastTimestamp = date('Y-m-d H:i:s', time() - 60);
        foreach ($games as $game) {
            $game->updateAttributes(['kickoff_at' => $pastTimestamp]);
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
            return $this->redirect(['view', 'id' => $competition->id]);
        }
        return $this->render('special-bet/create', ['bet' => $bet, 'competition' => $competition]);
    }

    public function actionSpecialBetUpdate($id)
    {
        $bet = $this->findSpecialBet($id);
        $competition = $bet->competition;

        if ($this->loadAndSaveSpecialBet($bet, $competition)) {
            return $this->redirect(['view', 'id' => $competition->id]);
        }
        return $this->render('special-bet/update', ['bet' => $bet, 'competition' => $competition]);
    }

    public function actionSpecialBetDelete($id)
    {
        $this->forcePostRequest();
        $bet = $this->findSpecialBet($id);
        $competitionId = $bet->competition_id;
        $bet->delete();
        Yii::$app->session->setFlash('success', Yii::t('KickoffModule.base', 'Special bet deleted.'));
        return $this->redirect(['view', 'id' => $competitionId]);
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
                    return $this->redirect(['view', 'id' => $competition->id]);
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
        if ($type !== null) {
            if (!$type->needsGroupLabel()) {
                $bet->group_label = null;
            }
            $bet->setOptions($type->buildOptions($competition, $bet));
        }
        if ($bet->save()) {
            $this->view->saved();
            return true;
        }
        return false;
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
