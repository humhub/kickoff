<?php

namespace kickoff;

use Codeception\Test\Unit;
use humhub\modules\kickoff\adapters\FootballDataMatchParser;
use humhub\modules\kickoff\models\Game;

class FootballDataMatchParserTest extends Unit
{
    public function testStageMapping(): void
    {
        $this->assertSame(Game::STAGE_GROUP, FootballDataMatchParser::stage('GROUP_STAGE'));
        $this->assertSame(Game::STAGE_ROUND_OF_16, FootballDataMatchParser::stage('LAST_16'));
        // LAST_32 is the value the live API emits for the FWC 2026 round of 32;
        // ROUND_OF_32 is only a defensive alias.
        $this->assertSame(Game::STAGE_ROUND_OF_32, FootballDataMatchParser::stage('LAST_32'));
        $this->assertSame(Game::STAGE_ROUND_OF_32, FootballDataMatchParser::stage('ROUND_OF_32'));
        $this->assertSame(Game::STAGE_QUARTER, FootballDataMatchParser::stage('QUARTER_FINALS'));
        $this->assertSame(Game::STAGE_SEMI, FootballDataMatchParser::stage('SEMI_FINALS'));
        $this->assertSame(Game::STAGE_THIRD_PLACE, FootballDataMatchParser::stage('THIRD_PLACE'));
        $this->assertSame(Game::STAGE_THIRD_PLACE, FootballDataMatchParser::stage('THIRD_PLACE_FINAL'));
        $this->assertSame(Game::STAGE_FINAL, FootballDataMatchParser::stage('FINAL'));
    }

    public function testStageFallback(): void
    {
        $this->assertSame(Game::STAGE_GROUP, FootballDataMatchParser::stage(null));
        $this->assertSame(Game::STAGE_GROUP, FootballDataMatchParser::stage('UNKNOWN_STAGE_X'));
    }

    public function testStatusMapping(): void
    {
        $this->assertSame(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('SCHEDULED'));
        $this->assertSame(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('TIMED'));
        $this->assertSame(Game::STATUS_LIVE, FootballDataMatchParser::status('IN_PLAY'));
        // PAUSED is the half-time break — a distinct status so the live badge
        // can show "HT" instead of a frozen 45'.
        $this->assertSame(Game::STATUS_PAUSED, FootballDataMatchParser::status('PAUSED'));
        $this->assertSame(Game::STATUS_FINISHED, FootballDataMatchParser::status('FINISHED'));
        $this->assertSame(Game::STATUS_FINISHED, FootballDataMatchParser::status('AWARDED'));
        $this->assertSame(Game::STATUS_POSTPONED, FootballDataMatchParser::status('POSTPONED'));
        $this->assertSame(Game::STATUS_POSTPONED, FootballDataMatchParser::status('SUSPENDED'));
        $this->assertSame(Game::STATUS_CANCELLED, FootballDataMatchParser::status('CANCELLED'));
        $this->assertSame(Game::STATUS_CANCELLED, FootballDataMatchParser::status('CANCELED'));
    }

    public function testStatusFallback(): void
    {
        $this->assertSame(Game::STATUS_SCHEDULED, FootballDataMatchParser::status(null));
        $this->assertSame(Game::STATUS_SCHEDULED, FootballDataMatchParser::status('UNKNOWN'));
    }

    public function testGroupLabelStripsPrefix(): void
    {
        $this->assertSame('A', FootballDataMatchParser::groupLabel('GROUP_A'));
        $this->assertSame('D', FootballDataMatchParser::groupLabel('GROUP_D'));
        $this->assertSame('L', FootballDataMatchParser::groupLabel('GROUP_L'));
        $this->assertSame('A', FootballDataMatchParser::groupLabel('A'), 'plain label preserved');
    }

    public function testGroupLabelReturnsNullForBlankOrNonString(): void
    {
        $this->assertNull(FootballDataMatchParser::groupLabel(null));
        $this->assertNull(FootballDataMatchParser::groupLabel(''));
        $this->assertNull(FootballDataMatchParser::groupLabel(42));
    }

    public function testScoresRegularTimeMatch(): void
    {
        // A match decided inside 90 minutes carries only fullTime/halfTime.
        $scores = FootballDataMatchParser::scores([
            'winner' => 'HOME_TEAM',
            'duration' => 'REGULAR',
            'fullTime' => ['home' => 2, 'away' => 1],
            'halfTime' => ['home' => 1, 'away' => 0],
        ]);
        $this->assertSame(2, $scores['home_score']);
        $this->assertSame(1, $scores['away_score']);
        $this->assertNull($scores['home_score_et']);
        $this->assertNull($scores['away_score_et']);
        $this->assertNull($scores['home_score_pen']);
        $this->assertNull($scores['away_score_pen']);
    }

    public function testScoresExtraTimeMatchUsesRegularTimeFor90Minutes(): void
    {
        // 1:1 after 90, won 2:1 in extra time. football-data's fullTime (2:1)
        // is the cumulative result; the 90-minute score lives in regularTime,
        // and extraTime carries only the goals scored within extra time.
        $scores = FootballDataMatchParser::scores([
            'winner' => 'HOME_TEAM',
            'duration' => 'EXTRA_TIME',
            'fullTime' => ['home' => 2, 'away' => 1],
            'halfTime' => ['home' => 0, 'away' => 0],
            'regularTime' => ['home' => 1, 'away' => 1],
            'extraTime' => ['home' => 1, 'away' => 0],
        ]);
        // 90-minute result, NOT the after-extra-time 2:1.
        $this->assertSame(1, $scores['home_score']);
        $this->assertSame(1, $scores['away_score']);
        // Cumulative score at the end of extra time = regularTime + extraTime.
        $this->assertSame(2, $scores['home_score_et']);
        $this->assertSame(1, $scores['away_score_et']);
        $this->assertNull($scores['home_score_pen']);
        $this->assertNull($scores['away_score_pen']);
    }

    public function testScoresPenaltyShootoutKeepsShootoutOutOf90AndEtScores(): void
    {
        // 1:1 after 90 and after extra time, won 6:5 on penalties. fullTime is
        // 7:6 (cumulative incl. the shootout) — it must never leak into the
        // 90-minute or end-of-extra-time scores.
        $scores = FootballDataMatchParser::scores([
            'winner' => 'HOME_TEAM',
            'duration' => 'PENALTY_SHOOTOUT',
            'fullTime' => ['home' => 7, 'away' => 6],
            'halfTime' => ['home' => 1, 'away' => 1],
            'regularTime' => ['home' => 1, 'away' => 1],
            'extraTime' => ['home' => 0, 'away' => 0],
            'penalties' => ['home' => 6, 'away' => 5],
        ]);
        $this->assertSame(1, $scores['home_score'], '90-minute score, not 7:6');
        $this->assertSame(1, $scores['away_score']);
        $this->assertSame(1, $scores['home_score_et'], 'end of ET = regularTime + extraTime');
        $this->assertSame(1, $scores['away_score_et']);
        $this->assertSame(6, $scores['home_score_pen']);
        $this->assertSame(5, $scores['away_score_pen']);
    }

    public function testScoresEmptyForUnplayedMatch(): void
    {
        $scores = FootballDataMatchParser::scores([]);
        $this->assertSame(
            [null, null, null, null, null, null],
            array_values($scores),
        );
    }
}
