<?php

declare(strict_types=1);

namespace IvanBaric\Status\Support;

use IvanBaric\Status\Contracts\ResolvesStatusActor;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Models\StatusHistory;
use IvanBaric\Status\Models\StatusTransition;

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

    public static function statusTransition(): string
    {
        /** @var class-string<StatusTransition> $model */
        $model = config('status.models.status_transition', StatusTransition::class);

        return $model;
    }

    public static function historyEnabled(): bool
    {
        return (bool) config('status.history_enabled', true);
    }

    public static function transitionsEnabled(): bool
    {
        return (bool) config('status.transitions_enabled', true);
    }

    public static function eventsEnabled(): bool
    {
        return (bool) config('status.events_enabled', true);
    }

    public static function strictMode(): bool
    {
        return (bool) config('status.strict_mode', true);
    }

    public static function cacheTtl(): int
    {
        return max(1, (int) config('status.cache_ttl', 3600));
    }

    public static function message(string $key, string $default): string
    {
        return (string) config("status.result_messages.{$key}", $default);
    }

    public static function actorResolver(): ResolvesStatusActor
    {
        /** @var class-string<ResolvesStatusActor> $resolver */
        $resolver = config('status.actor_resolver');

        return app($resolver);
    }
}
