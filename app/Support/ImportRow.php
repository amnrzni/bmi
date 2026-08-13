<?php

namespace App\Support;

use App\Models\User;

/**
 * One parsed line of an uploaded roster CSV, with what would happen to it.
 *
 * Rows carry their verdict rather than throwing, so the whole file can be shown
 * to the admin before anything is written.
 */
final class ImportRow
{
    public const CREATE = 'create';

    public const UPDATE = 'update';

    public const ERROR = 'error';

    /** @param  array<int,string>  $errors */
    public function __construct(
        public readonly int $line,
        public readonly array $values,
        public string $action = self::CREATE,
        public array $errors = [],
        public ?User $existing = null,
    ) {}

    public function fail(string $message): void
    {
        $this->action = self::ERROR;
        $this->errors[] = $message;
    }

    public function isError(): bool
    {
        return $this->action === self::ERROR;
    }

    public function value(string $key): ?string
    {
        $value = trim((string) ($this->values[$key] ?? ''));

        return $value === '' ? null : $value;
    }

    public function errorText(): string
    {
        return implode(' ', $this->errors);
    }
}
