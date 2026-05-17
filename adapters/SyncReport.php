<?php

namespace humhub\modules\kickoff\adapters;

final class SyncReport
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var string[] */
    public array $errors = [];

    public function addError(string $error): void
    {
        $this->errors[] = $error;
    }

    public function isSuccess(): bool
    {
        return $this->errors === [];
    }

    public function summary(): string
    {
        return sprintf(
            '%d created, %d updated, %d skipped, %d errors',
            $this->created,
            $this->updated,
            $this->skipped,
            count($this->errors),
        );
    }
}
