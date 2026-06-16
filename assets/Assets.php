<?php

namespace humhub\modules\kickoff\assets;

use humhub\components\assets\AssetBundle;
use yii\helpers\Url;

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

    /**
     * Builds the public URL for a bundled Twemoji flag SVG, resolved the same
     * way HumHub resolves its own stylesheet/script URLs.
     *
     * `$baseUrl` is the published bundle base URL (`static::register($view)->baseUrl`),
     * which is not guaranteed to be a final URL: on HumHub develop the
     * filesystem-backed asset manager sets the base URL to the unresolved path
     * alias `@web/assets/<hash>` and only resolves it when emitting `<link>`/
     * `<script>` tags (`Html::cssFile()`/`Html::jsFile()` run it through
     * {@see Url::to()}). A hand-built `<img src>` bypasses that, so without
     * Url::to() the literal `@web/...` leaks into the markup and the browser
     * resolves it against the current page URL (e.g. `…/kickoff/c/@web/assets/…`),
     * 404ing the flag. An already-resolved base URL (`/assets/<hash>` on stable)
     * passes through unchanged.
     */
    public static function flagUrl(string $baseUrl, string $fileStem): string
    {
        return Url::to($baseUrl . "/flags/{$fileStem}.svg");
    }
}
