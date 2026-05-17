<?php

namespace humhub\modules\kickoff\controllers;

use humhub\modules\admin\components\Controller;
use humhub\modules\kickoff\adapters\SyncReport;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\models\ScoringScheme;
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
