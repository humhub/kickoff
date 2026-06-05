<?php

use yii\helpers\Html;

/** @var \humhub\modules\kickoff\models\Team|null $team */

$name = $team ? $team->getDisplayName() : '?';
$short = $team && $team->short_name !== null && $team->short_name !== '' ? $team->short_name : null;
$logo = $team && $team->logo_url !== null && $team->logo_url !== '' ? $team->logo_url : null;
$flagUrl = null;

if (!$logo && $team) {
    // The codepoint sequence doubles as the Twemoji filename: regional
    // indicator pairs for countries, tag sequences for England/Scotland/Wales.
    $codepoints = \humhub\modules\kickoff\services\TeamNameLocalizer::flagCodepoints($team->country_code);
    if ($codepoints !== null) {
        $fileStem = implode('-', array_map(
            static fn(int $codepoint): string => strtolower(dechex($codepoint)),
            $codepoints,
        ));
        $baseUrl = \humhub\modules\kickoff\assets\Assets::register($this)->baseUrl;
        $flagUrl = "{$baseUrl}/flags/{$fileStem}.svg";
    }
}

if ($short !== null) {
    $initials = mb_strtoupper(mb_substr($short, 0, 3));
} else {
    $parts = preg_split('/\s+/u', trim($name)) ?: [$name];
    $letters = '';
    foreach ($parts as $p) {
        $letters .= mb_substr($p, 0, 1);
        if (mb_strlen($letters) >= 2) {
            break;
        }
    }
    $initials = mb_strtoupper($letters);
}

$palette = [
    '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444',
    '#8b5cf6', '#06b6d4', '#84cc16', '#f97316', '#ec4899',
];
$color = $team ? $palette[$team->id % count($palette)] : '#9ca3af';

?>
<?php /* Explicit width/height: the Twemoji SVGs carry no intrinsic size, so
         without attributes the image briefly renders at full content width
         before the stylesheet applies (visible flash in Firefox). The badge
         CSS still overrides the final size. */ ?>
<?php if ($logo): ?>
    <span class="kickoff-team-badge" title="<?= Html::encode($name) ?>">
        <img src="<?= Html::encode($logo) ?>" alt="<?= Html::encode($name) ?>" width="28" height="28">
    </span>
<?php elseif ($flagUrl !== null): ?>
    <span class="kickoff-team-badge kickoff-team-badge--flag" title="<?= Html::encode($name) ?>">
        <img src="<?= Html::encode($flagUrl) ?>" alt="<?= Html::encode($name) ?>" width="28" height="28">
    </span>
<?php else: ?>
    <span class="kickoff-team-badge" title="<?= Html::encode($name) ?>" style="background: <?= $color ?>;">
        <?= Html::encode($initials) ?>
    </span>
<?php endif; ?>
