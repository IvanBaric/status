<?php

declare(strict_types=1);

namespace IvanBaric\Status\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class StatusHistoryWriter
{
    public function write(Model $statusable, StatusHistoryData $data): void
    {
        if (! StatusModels::historyEnabled()) {
            return;
        }

        $statusable->statusHistory()->create([
            'uuid' => (string) Str::uuid(),
            'from_status_id' => $data->fromStatus?->getKey(),
            'to_status_id' => $data->toStatus?->getKey(),
            'changed_by_user_id' => $data->actorId,
            'source' => $data->source,
            'reason' => $data->reason,
            'meta' => $data->meta,
            'changed_at' => $data->changedAt,
        ]);
    }
}
