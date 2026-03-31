<?php

declare(strict_types=1);

namespace IvanBaric\Status\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use InvalidArgumentException;
use IvanBaric\Status\Support\StatusModels;

class Status extends Model
{
    protected $table = 'statuses';

    protected $fillable = [
        'uuid',
        'type',
        'key',
        'name',
        'tooltip',
        'color',
        'icon',
        'sort_order',
        'is_default',
        'is_final',
        'is_active',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'int',
            'is_default' => 'bool',
            'is_final' => 'bool',
            'is_active' => 'bool',
            'meta' => 'array',
        ];
    }

    public function historiesFrom(): HasMany
    {
        return $this->hasMany(StatusModels::statusHistory(), 'from_status_id');
    }

    public function historiesTo(): HasMany
    {
        return $this->hasMany(StatusModels::statusHistory(), 'to_status_id');
    }

    public function transitionsFrom(): HasMany
    {
        return $this->hasMany(StatusModels::statusTransition(), 'from_status_id');
    }

    public function transitionsTo(): HasMany
    {
        return $this->hasMany(StatusModels::statusTransition(), 'to_status_id');
    }

    public function scopeForType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isFinal(): bool
    {
        return $this->is_final;
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function badge(): array
    {
        return [
            'label' => $this->name,
            'tooltip' => $this->tooltip,
            'color' => $this->color,
            'icon' => $this->icon,
        ];
    }

    public static function getStatuses(string $type): Collection
    {
        $rows = static::getCachedRows($type);

        return new Collection(array_map(static function (array $attributes): self {
            $model = new static();
            $model->setRawAttributes($attributes, true);
            $model->exists = true;

            return $model;
        }, $rows));
    }

    public static function keysFor(string $type): array
    {
        /** @var list<string> $keys */
        $keys = Cache::remember(
            static::cacheKey($type, 'keys'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => static::getStatuses($type)->pluck('key')->all(),
        );

        return $keys;
    }

    public static function defaultFor(string $type): ?self
    {
        /** @var ?self */
        return static::getStatuses($type)->firstWhere('is_default', true);
    }

    public static function findByKey(string $type, string $key): self
    {
        /** @var ?self $status */
        $status = static::query()->forType($type)->where('key', $key)->first();

        if ($status instanceof self) {
            return $status;
        }

        throw new InvalidArgumentException("Status [{$type}:{$key}] not found.");
    }

    public static function findByKeyCached(string $type, string $key): ?self
    {
        /** @var array<string, int> $map */
        $map = Cache::remember(
            static::cacheKey($type, 'active'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => collect(static::getCachedRows($type))
                ->mapWithKeys(static fn (array $row): array => [(string) $row['key'] => (int) $row['id']])
                ->all(),
        );

        $id = $map[$key] ?? null;

        return is_int($id) && $id > 0 ? static::query()->whereKey($id)->first() : null;
    }

    public static function clearCache(?string $type = null): void
    {
        if (is_string($type) && $type !== '') {
            static::flushTypeCache($type);

            return;
        }

        static::query()->distinct()->pluck('type')->each(static fn (string $knownType): bool => static::flushTypeCache($knownType) || true);
    }

    protected static function cacheKey(string $type, string $suffix): string
    {
        return "statuses.{$type}.{$suffix}";
    }

    protected static function getCachedRows(string $type): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            static::cacheKey($type, 'list'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => static::query()->forType($type)->active()->ordered()->get()
                ->map(static fn (self $status): array => $status->getAttributes())
                ->values()
                ->all(),
        );

        return $rows;
    }

    protected static function booted(): void
    {
        $flush = static function (self $status): void {
            foreach (array_filter([$status->type, $status->getOriginal('type')]) as $type) {
                static::flushTypeCache((string) $type);
            }
        };

        static::creating(static function (self $status): void {
            $status->uuid ??= (string) Str::uuid();
        });

        static::saved($flush);
        static::deleted($flush);
    }

    protected static function flushTypeCache(string $type): bool
    {
        Cache::forget(static::cacheKey($type, 'list'));
        Cache::forget(static::cacheKey($type, 'keys'));
        Cache::forget(static::cacheKey($type, 'active'));

        return true;
    }
}
