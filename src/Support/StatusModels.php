<?php

declare(strict_types=1);

namespace IvanBaric\Status\Support;

use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Models\StatusHistory;

final class StatusModels
{
    public static function status(): string
    {
        /** @var class-string<Status> $model */
        $model = config('status.models.status', Status::class);

        return $model;
    }

    public static function statusHistory(): string
    {
        /** @var class-string<StatusHistory> $model */
        $model = config('status.models.status_history', StatusHistory::class);

        return $model;
    }

    public static function historyEnabled(): bool
    {
        return (bool) config('status.history.enabled', true);
    }

    public static function cacheTtl(): int
    {
        return max(1, (int) config('status.cache_ttl', 3600));
    }
}
