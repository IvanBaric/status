<?php

declare(strict_types=1);

namespace IvanBaric\Status\Support;

use Carbon\CarbonInterface;
use IvanBaric\Status\Models\Status;

final class StatusHistoryData
{
    public function __construct(
        public readonly ?Status $fromStatus,
        public readonly ?Status $toStatus,
        public readonly ?int $actorId,
        public readonly string $source,
        public readonly ?string $reason,
        public readonly array $meta,
        public readonly CarbonInterface $changedAt,
    ) {
    }
}
