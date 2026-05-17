<?php

use yii\helpers\Html;

/** @var \humhub\modules\kickoff\models\Team|null $team */

$name = $team ? $team->name : '?';
$short = $team && $team->short_name !== null && $team->short_name !== '' ? $team->short_name : null;
$logo = $team && $team->logo_url !== null && $team->logo_url !== '' ? $team->logo_url : null;
$flag = null;

if (!$logo && $team && !empty($team->country_code)) {
    $code = strtoupper(trim($team->country_code));
    if (strlen($code) === 2 && ctype_alpha($code)) {
        $flag = mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8')
              . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
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
<?php if ($logo): ?>
    <span class="kickoff-team-badge" title="<?= Html::encode($name) ?>">
        <img src="<?= Html::encode($logo) ?>" alt="<?= Html::encode($name) ?>">
    </span>
<?php elseif ($flag !== null): ?>
    <span class="kickoff-team-badge kickoff-team-badge--flag" title="<?= Html::encode($name) ?>">
        <?= $flag ?>
    </span>
<?php else: ?>
    <span class="kickoff-team-badge" title="<?= Html::encode($name) ?>" style="background: <?= $color ?>;">
        <?= Html::encode($initials) ?>
    </span>
<?php endif; ?>
