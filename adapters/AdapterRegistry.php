<?php

namespace humhub\modules\kickoff\adapters;

use humhub\modules\kickoff\models\Competition;

class AdapterRegistry
{
    /** @var array<string, CompetitionDataAdapter> */
    private array $adapters = [];

    public function register(CompetitionDataAdapter $adapter): void
    {
        $this->adapters[$adapter->getKey()] = $adapter;
    }

    public function get(string $key): ?CompetitionDataAdapter
    {
        return $this->adapters[$key] ?? null;
    }

    public function requireAdapter(string $key): CompetitionDataAdapter
    {
        $adapter = $this->get($key);
        if ($adapter === null) {
            throw new \RuntimeException("No adapter registered for key '{$key}'.");
        }
        return $adapter;
    }

    /** @return CompetitionDataAdapter[] */
    public function all(): array
    {
        return array_values($this->adapters);
    }

    public function forCompetition(Competition $competition): ?CompetitionDataAdapter
    {
        return $this->get($competition->data_source);
    }

    public static function createDefault(): self
    {
        $registry = new self();
        // HumHub-hosted adapter first so it shows up at the top of the data-source
        // dropdown — it's the zero-config default for the WM 2026 use case.
        $registry->register(new HumHubApiAdapter());
        $registry->register(new ManualAdapter());
        $registry->register(new MockAdapter());
        $registry->register(new MockLargeAdapter());
        $registry->register(new FootballDataOrgAdapter());
        return $registry;
    }
}
