<?php

namespace humhub\modules\kickoff\controllers;

use humhub\components\Controller;
use humhub\modules\kickoff\models\Competition;
use humhub\modules\kickoff\Module;

class DashboardController extends Controller
{
    public function getAccessRules()
    {
        return [
            ['login'],
            ['permission' => Module::ACCESS_PERMISSIONS],
        ];
    }

    public function actionIndex()
    {
        $active = Competition::find()
            ->where(['status' => Competition::STATUS_ACTIVE])
            ->orderBy(['is_test' => SORT_ASC, 'starts_at' => SORT_ASC])
            ->all();

        $nonTest = array_values(array_filter($active, fn(Competition $c) => !$c->isTest()));
        if (count($nonTest) === 1) {
            return $this->redirect(['/kickoff/competition/view', 'slug' => $nonTest[0]->slug]);
        }

        return $this->render('index', ['competitions' => $active]);
    }
}
