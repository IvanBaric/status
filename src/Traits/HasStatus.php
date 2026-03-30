<?php

declare(strict_types=1);

namespace IvanBaric\Status\Traits;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Support\StatusModels;
use LogicException;

trait HasStatus
{
    protected bool $statusRelationResolved = false;

    protected ?Status $resolvedCurrentStatus = null;

    public function scopeWhereHasStatus(Builder $query, string $key, ?string $type = null): Builder
    {
        /** @var Model&self $model */
        $model = $query->getModel();
        $type ??= $model->statusType();

        return $query->whereHas('statuses', static fn (Builder $q): Builder => $q
            ->where('type', $type)
            ->where('key', $key)
        );
    }

    public function statuses(): MorphToMany
    {
        /** @var Model $this */
        return $this->morphToMany(
            StatusModels::status(),
            'statusable',
            'statusables',
            'statusable_id',
            'status_id'
        )->withTimestamps();
    }

    public function statusHistory(): MorphMany
    {
        if (! StatusModels::historyEnabled()) {
            throw new LogicException('Status history is disabled in the status config.');
        }

        /** @var Model $this */
        return $this->morphMany(
            StatusModels::statusHistory(),
            'statusable'
        )->latest('changed_at');
    }

    public function getStatus(): ?Status
    {
        if ($this->statusRelationResolved) {
            return $this->resolvedCurrentStatus;
        }

        /** @var ?Status $status */
        $status = $this->statuses()
            ->where('type', $this->statusType())
            ->first();

        $this->resolvedCurrentStatus = $status;
        $this->statusRelationResolved = true;

        return $status;
    }

    public function setStatus(
        mixed $status,
        ?string $reason = null,
        string $source = 'manual',
        array $meta = []
    ): static {
        $this->assertStatusableExists();

        return DB::transaction(function () use ($status, $reason, $source, $meta): static {
            $resolvedStatus = $this->resolveStatus($status);
            $currentStatus = $this->getStatus();

            if ($currentStatus?->is($resolvedStatus)) {
                return $this;
            }

            if ($currentStatus?->isFinal()) {
                throw new DomainException("Current status [{$currentStatus->key}] is final and cannot be changed.");
            }

            $timestamp = now();

            $this->statuses()->detach();

            $this->statuses()->attach($resolvedStatus->getKey(), [
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);

            $this->recordStatusHistory(
                fromStatus: $currentStatus,
                toStatus: $resolvedStatus,
                source: $source,
                reason: $reason,
                meta: $meta,
                changedAt: $timestamp,
            );

            $this->cacheResolvedStatus($resolvedStatus);

            return $this;
        });
    }

    public function hasStatus(mixed $status): bool
    {
        $resolvedStatus = $this->resolveStatus($status);
        $currentStatus = $this->getStatus();

        return $currentStatus?->is($resolvedStatus) ?? false;
    }

    public function clearStatus(?string $reason = null, string $source = 'manual', array $meta = []): static
    {
        $this->assertStatusableExists();

        return DB::transaction(function () use ($reason, $source, $meta): static {
            $currentStatus = $this->getStatus();

            if (! $currentStatus instanceof Status) {
                return $this;
            }

            if ($currentStatus->isFinal()) {
                throw new DomainException("Current status [{$currentStatus->key}] is final and cannot be cleared.");
            }

            $timestamp = now();

            $this->statuses()->detach();

            $this->recordStatusHistory(
                fromStatus: $currentStatus,
                toStatus: null,
                source: $source,
                reason: $reason,
                meta: $meta,
                changedAt: $timestamp,
            );

            $this->cacheResolvedStatus(null);

            return $this;
        });
    }

    public function getStatusKey(): ?string
    {
        return $this->getStatus()?->key;
    }

    public function getStatusName(): ?string
    {
        return $this->getStatus()?->name;
    }

    public function getStatusTooltip(): ?string
    {
        return $this->getStatus()?->tooltip;
    }

    public function getStatusColor(): ?string
    {
        return $this->getStatus()?->color;
    }

    public function getStatusIcon(): ?string
    {
        return $this->getStatus()?->icon;
    }

    protected function resolveStatus(mixed $status): Status
    {
        $statusModel = StatusModels::status();

        /** @var ?Status $resolvedStatus */
        $resolvedStatus = match (true) {
            $status instanceof Status => $status,
            is_int($status) => $statusModel::query()->whereKey($status)->first(),
            is_string($status) => $statusModel::findByKeyCached($this->statusType(), $status),
            default => throw new InvalidArgumentException(
                'Invalid status input. Expected Status model, int ID or string key.'
            ),
        };

        if (! $resolvedStatus instanceof Status) {
            throw new DomainException("Status could not be resolved for type [{$this->statusType()}].");
        }

        $this->validateStatus($resolvedStatus);

        return $resolvedStatus;
    }

    protected function validateStatus(Status $status): void
    {
        if ($status->type !== $this->statusType()) {
            throw new DomainException(
                "Invalid status type [{$status->type}] for model status type [{$this->statusType()}]."
            );
        }

        if (! $status->isActive()) {
            throw new DomainException("Status [{$status->key}] is inactive.");
        }
    }

    protected function resolveStatusActorId(): ?int
    {
        return auth()->id();
    }

    protected function assertStatusableExists(): void
    {
        /** @var Model $this */
        if ($this->exists) {
            return;
        }

        throw new LogicException('Status operations require a persisted model instance.');
    }

    protected function cacheResolvedStatus(?Status $status): void
    {
        $this->resolvedCurrentStatus = $status;
        $this->statusRelationResolved = true;

        unset($this->relations['statuses'], $this->relations['statusHistory']);
    }

    protected function recordStatusHistory(
        ?Status $fromStatus,
        ?Status $toStatus,
        string $source,
        ?string $reason,
        array $meta,
        mixed $changedAt
    ): void {
        if (! StatusModels::historyEnabled()) {
            return;
        }

        $this->statusHistory()->create([
            'uuid' => (string) Str::uuid(),
            'status_type' => $this->statusType(),
            'from_status_id' => $fromStatus?->getKey(),
            'from_status_key' => $fromStatus?->key,
            'to_status_id' => $toStatus?->getKey(),
            'to_status_key' => $toStatus?->key,
            'changed_by_user_id' => $this->resolveStatusActorId(),
            'source' => $source,
            'reason' => $reason,
            'meta' => $meta,
            'changed_at' => $changedAt,
        ]);
    }

    public function statusType(): string
    {
        /** @var Model $this */
        return Str::snake(class_basename($this));
    }
}
