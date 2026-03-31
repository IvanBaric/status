<?php

declare(strict_types=1);

namespace IvanBaric\Status\Data;

final class StatusTransitionResult
{
    public function __construct(
        public readonly bool $allowed,
        public readonly ?string $message = null,
        public readonly array $payload = [],
    ) {
    }

    public static function allow(?string $message = null, array $payload = []): self
    {
        return new self(true, $message, $payload);
    }

    public static function deny(?string $message = null, array $payload = []): self
    {
        return new self(false, $message, $payload);
    }
}
