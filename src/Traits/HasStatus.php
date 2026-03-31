<?php

declare(strict_types=1);

namespace IvanBaric\Status\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use IvanBaric\Status\Data\StatusTransitionResult;
use IvanBaric\Status\Events\StatusChanged;
use IvanBaric\Status\Models\Status;
use IvanBaric\Status\Models\StatusTransition;
use IvanBaric\Status\Support\StatusHistoryData;
use IvanBaric\Status\Support\StatusHistoryWriter;
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

        return $query->whereHas('statuses', static fn (Builder $q): Builder => $q->where('type', $type)->where('key', $key));
    }

    public function statuses(): MorphToMany
    {
        /** @var Model $this */
        return $this->morphToMany(StatusModels::status(), 'statusable', 'statusables', 'statusable_id', 'status_id')->withTimestamps();
    }

    public function statusHistory(): MorphMany
    {
        /** @var Model $this */
        return $this->morphMany(StatusModels::statusHistory(), 'statusable')
            ->orderByDesc('changed_at')
            ->orderByDesc('id');
    }

    public function getStatus(): ?Status
    {
        if ($this->statusRelationResolved) {
            return $this->resolvedCurrentStatus;
        }

        /** @var ?Status $status */
        $status = $this->statuses()->where('type', $this->statusType())->first();
        $this->cacheResolvedStatus($status);

        return $status;
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

    public function hasStatus(mixed $status): bool
    {
        $resolvedStatus = $this->resolveStatus($status);

        return $this->getStatus()?->is($resolvedStatus) ?? false;
    }

    public function inspectStatusTransition(mixed $status): StatusTransitionResult
    {
        if ($result = $this->inspectPersistenceRequirement()) {
            return $result;
        }

        try {
            $toStatus = $this->resolveStatus($status);
        } catch (InvalidArgumentException) {
            return $this->deny('status_missing');
        }

        return $this->validateResolvedStatus($toStatus);
    }

    public function trySetStatus(
        mixed $status,
        ?string $reason = null,
        string $source = 'manual',
        array $meta = [],
    ): StatusTransitionResult {
        $inspection = $this->inspectStatusTransition($status);

        if (! $inspection->allowed) {
            return $inspection;
        }

        /** @var Status $toStatus */
        $toStatus = $inspection->payload['status'];
        $fromStatus = $this->getStatus();
        $actorId = $this->resolveStatusActorId();

        DB::transaction(function () use ($fromStatus, $toStatus, $actorId, $source, $reason, $meta): void {
            $this->removeCurrentStatus();
            $this->attachStatus($toStatus);
            $this->writeStatusHistory($fromStatus, $toStatus, $actorId, $source, $reason, $meta);
            $this->cacheResolvedStatus($toStatus);
            $this->dispatchStatusChangedEvent($fromStatus, $toStatus, $actorId, $source, $reason, $meta);
        });

        return StatusTransitionResult::allow(
            message: StatusModels::message('status_updated', 'Status updated successfully.'),
            payload: ['status' => $toStatus],
        );
    }

    public function setStatusOrFail(
        mixed $status,
        ?string $reason = null,
        string $source = 'manual',
        array $meta = [],
    ): static {
        $result = $this->trySetStatus($status, $reason, $source, $meta);

        if (! $result->allowed) {
            throw new LogicException($result->message ?? 'Status change failed.');
        }

        return $this;
    }

    public function clearStatus(?string $reason = null, string $source = 'manual', array $meta = []): StatusTransitionResult
    {
        if ($result = $this->inspectPersistenceRequirement()) {
            return $result;
        }

        $currentStatus = $this->getStatus();

        if (! $currentStatus instanceof Status) {
            return StatusTransitionResult::allow(StatusModels::message('status_cleared', 'Status cleared successfully.'));
        }

        if ($currentStatus->isFinal()) {
            return $this->deny('clear_final_status');
        }

        $actorId = $this->resolveStatusActorId();

        DB::transaction(function () use ($currentStatus, $actorId, $source, $reason, $meta): void {
            $this->removeCurrentStatus();
            $this->writeStatusHistory($currentStatus, null, $actorId, $source, $reason, $meta);
            $this->cacheResolvedStatus(null);
            $this->dispatchStatusChangedEvent($currentStatus, null, $actorId, $source, $reason, $meta);
        });

        return StatusTransitionResult::allow(StatusModels::message('status_cleared', 'Status cleared successfully.'));
    }

    public function allowedTransitions(): Collection
    {
        $currentStatus = $this->getStatus();

        if (! $currentStatus instanceof Status || ! StatusModels::transitionsEnabled()) {
            return new Collection();
        }

        /** @var class-string<StatusTransition> $transitionModel */
        $transitionModel = StatusModels::statusTransition();

        return $transitionModel::query()
            ->where('from_status_id', $currentStatus->getKey())
            ->where('is_active', true)
            ->with(['fromStatus', 'toStatus'])
            ->get();
    }

    public function allowedStatuses(): Collection
    {
        $currentStatus = $this->getStatus();

        if (! $currentStatus instanceof Status) {
            $default = Status::defaultFor($this->statusType());

            return $default instanceof Status ? new Collection([$default]) : Status::getStatuses($this->statusType());
        }

        return new Collection(
            $this->allowedTransitions()->pluck('toStatus')->filter()->values()->all()
        );
    }

    abstract protected function statusType(): string;

    protected function resolveStatus(mixed $status): Status
    {
        $statusModel = StatusModels::status();

        /** @var ?Status $resolvedStatus */
        $resolvedStatus = match (true) {
            $status instanceof Status => $status,
            is_int($status) => $statusModel::query()->whereKey($status)->first(),
            is_string($status) => $statusModel::findByKeyCached($this->statusType(), $status),
            default => throw new InvalidArgumentException('Invalid status input.'),
        };

        if ($resolvedStatus instanceof Status) {
            return $resolvedStatus;
        }

        throw new InvalidArgumentException('Status could not be resolved.');
    }

    protected function validateResolvedStatus(Status $status): StatusTransitionResult
    {
        foreach ([
            $this->inspectSameStatus($status),
            $this->inspectStatusType($status),
            $this->inspectActiveStatus($status),
            $this->inspectFinalStatus(),
            $this->inspectTransitionRule($status),
            $this->inspectTransitionGuards($status),
        ] as $result) {
            if ($result instanceof StatusTransitionResult) {
                return $result;
            }
        }

        return StatusTransitionResult::allow(payload: ['status' => $status]);
    }

    protected function inspectPersistenceRequirement(): ?StatusTransitionResult
    {
        /** @var Model $this */
        return $this->exists ? null : $this->deny('persisted_model_required');
    }

    protected function inspectSameStatus(Status $toStatus): ?StatusTransitionResult
    {
        return $this->getStatus()?->is($toStatus) ? $this->deny('same_status') : null;
    }

    protected function inspectStatusType(Status $status): ?StatusTransitionResult
    {
        return $status->type === $this->statusType() ? null : $this->deny('invalid_status_type');
    }

    protected function inspectActiveStatus(Status $status): ?StatusTransitionResult
    {
        return $status->isActive() ? null : $this->deny('inactive_status');
    }

    protected function inspectFinalStatus(): ?StatusTransitionResult
    {
        return $this->getStatus()?->isFinal() ? $this->deny('final_status') : null;
    }

    protected function inspectTransitionRule(Status $toStatus): ?StatusTransitionResult
    {
        $currentStatus = $this->getStatus();

        if (! $currentStatus instanceof Status) {
            return $this->inspectInitialStatus($toStatus);
        }

        return $this->canTransitionTo($currentStatus, $toStatus) ? null : $this->deny('invalid_transition');
    }

    protected function inspectInitialStatus(Status $toStatus): ?StatusTransitionResult
    {
        if (! StatusModels::strictMode()) {
            return null;
        }

        $default = Status::defaultFor($this->statusType());

        if (! $default instanceof Status) {
            return null;
        }

        return (int) $default->getKey() === (int) $toStatus->getKey()
            ? null
            : $this->deny('invalid_transition');
    }

    protected function inspectTransitionGuards(Status $toStatus): ?StatusTransitionResult
    {
        $fromStatus = $this->getStatus();

        if (! $fromStatus instanceof Status) {
            return null;
        }

        if (! method_exists($this, 'passesStatusTransitionGuards')) {
            return null;
        }

        /** @var callable(Status, Status): bool $guard */
        $guard = [$this, 'passesStatusTransitionGuards'];

        return $guard($fromStatus, $toStatus) ? null : $this->deny('guard_failed');
    }

    protected function removeCurrentStatus(): void
    {
        $this->statuses()->detach();
    }

    protected function attachStatus(Status $status): void
    {
        $timestamp = now();

        $this->statuses()->attach($status->getKey(), [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    protected function writeStatusHistory(
        ?Status $fromStatus,
        ?Status $toStatus,
        ?int $actorId,
        string $source,
        ?string $reason,
        array $meta,
    ): void {
        app(StatusHistoryWriter::class)->write($this, new StatusHistoryData(
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            actorId: $actorId,
            source: $source,
            reason: $reason,
            meta: $meta,
            changedAt: now(),
        ));
    }

    protected function resolveStatusActorId(): ?int
    {
        return StatusModels::actorResolver()->resolve();
    }

    protected function canTransitionTo(Status $from, Status $to): bool
    {
        if (! StatusModels::transitionsEnabled()) {
            return true;
        }

        /** @var class-string<StatusTransition> $transitionModel */
        $transitionModel = StatusModels::statusTransition();

        return $transitionModel::query()
            ->where('from_status_id', $from->getKey())
            ->where('to_status_id', $to->getKey())
            ->where('is_active', true)
            ->exists();
    }

    protected function dispatchStatusChangedEvent(
        ?Status $fromStatus,
        ?Status $toStatus,
        ?int $actorId,
        string $source,
        ?string $reason,
        array $meta,
    ): void {
        if (! StatusModels::eventsEnabled()) {
            return;
        }

        event(new StatusChanged($this, $fromStatus, $toStatus, $actorId, $source, $reason, $meta));
    }

    protected function cacheResolvedStatus(?Status $status): void
    {
        $this->resolvedCurrentStatus = $status;
        $this->statusRelationResolved = true;

        unset($this->relations['statuses'], $this->relations['statusHistory']);
    }

    protected function deny(string $messageKey): StatusTransitionResult
    {
        return StatusTransitionResult::deny(StatusModels::message($messageKey, 'Status transition denied.'));
    }
}
