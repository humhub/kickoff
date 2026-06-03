<?php

namespace humhub\modules\kickoff\controllers;

use humhub\components\Controller;
use humhub\modules\kickoff\models\Competition;

class DashboardController extends Controller
{
    public function getAccessRules()
    {
        // Listing is login-only; each competition's own visibility is applied
        // below, so a member always sees at least the public competitions.
        return [
            ['login'],
        ];
    }

    public function actionIndex()
    {
        $active = Competition::find()
            ->where(['status' => Competition::STATUS_ACTIVE])
            ->orderBy(['is_test' => SORT_ASC, 'starts_at' => SORT_ASC])
            ->all();

        // Public competitions for everyone; restricted ones only for members
        // allowed to view them.
        $viewable = array_values(array_filter($active, fn(Competition $c) => $c->canView()));

        $nonTest = array_values(array_filter($viewable, fn(Competition $c) => !$c->isTest()));
        if (count($nonTest) === 1) {
            return $this->redirect(['/kickoff/competition/view', 'slug' => $nonTest[0]->slug]);
        }

        return $this->render('index', ['competitions' => $viewable]);
    }
}
