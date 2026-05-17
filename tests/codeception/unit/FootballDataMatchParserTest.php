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
        $this->assertSame(Game::STATUS_LIVE, FootballDataMatchParser::status('PAUSED'));
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
}
