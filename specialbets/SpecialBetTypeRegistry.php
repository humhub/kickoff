<?php

namespace humhub\modules\kickoff\specialbets;

class SpecialBetTypeRegistry
{
    /** @var array<string, SpecialBetType> */
    private array $types = [];

    public function register(SpecialBetType $type): void
    {
        $this->types[$type->getKey()] = $type;
    }

    public function get(string $key): ?SpecialBetType
    {
        return $this->types[$key] ?? null;
    }

    public function requireType(string $key): SpecialBetType
    {
        $type = $this->get($key);
        if ($type === null) {
            throw new \RuntimeException("No special-bet type registered for key '{$key}'.");
        }
        return $type;
    }

    /** @return SpecialBetType[] */
    public function all(): array
    {
        return array_values($this->types);
    }

    public static function createDefault(): self
    {
        $registry = new self();
        $registry->register(new WinnerBetType());
        $registry->register(new TopScorerBetType());
        $registry->register(new GroupWinnerBetType());
        return $registry;
    }
}
