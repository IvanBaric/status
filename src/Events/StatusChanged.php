<?php

declare(strict_types=1);

namespace IvanBaric\Status\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use IvanBaric\Status\Models\Status;

class StatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Model $statusable,
        public readonly ?Status $fromStatus,
        public readonly ?Status $toStatus,
        public readonly ?int $actorId,
        public readonly string $source,
        public readonly ?string $reason,
        public readonly array $meta = [],
    ) {
    }
}
