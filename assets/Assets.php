<?php

namespace humhub\modules\kickoff\assets;

use humhub\components\assets\AssetBundle;

/**
 * Shared front-end styles for the Kickoff module. Registered by every
 * standalone front-end page (competition view, rules, leaderboard) so the
 * markup renders correctly on a direct/new-tab load — previously the styles
 * lived inline in the competition view only and the other pages relied on
 * that CSS still being in the DOM from in-app (pjax) navigation.
 */
class Assets extends AssetBundle
{
    public $sourcePath = '@kickoff/resources';

    public $css = [
        'css/kickoff.css',
    ];

    // Re-publish on every request in debug so CSS edits show up without
    // clearing the asset cache; production publishes once.
    public $forceCopy = YII_DEBUG;
}
