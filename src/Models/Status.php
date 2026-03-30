<?php

declare(strict_types=1);

namespace IvanBaric\Status\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
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

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isFinal(): bool
    {
        return $this->is_final;
    }

    /**
     * @param array<int|string, array<string, mixed>> $definitions
     * @return Collection<int, static>
     */
    public static function syncType(string $type, array $definitions): Collection
    {
        $statuses = [];

        foreach (static::normalizeDefinitions($type, $definitions) as $attributes) {
            $status = static::query()->firstOrNew([
                'type' => $type,
                'key' => $attributes['key'],
            ]);

            $status->fill($attributes);
            $status->uuid ??= (string) Str::uuid();
            $status->save();

            $statuses[] = $status->fresh() ?? $status;
        }

        /** @var Collection<int, static> */
        return new Collection($statuses);
    }

    /**
     * @return Collection<int, static>
     */
    public static function getStatuses(string $type): Collection
    {
        $rows = static::getCachedRows($type);

        $models = array_map(static function (array $attributes): self {
            $model = new static();
            $model->setRawAttributes($attributes, true);
            $model->exists = true;

            return $model;
        }, $rows);

        /** @var Collection<int, static> */
        return new Collection($models);
    }

    /**
     * @return list<string>
     */
    public static function keysFor(string $type): array
    {
        /** @var list<string> $keys */
        $keys = Cache::remember(
            static::cacheKey($type, 'keys'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => static::getStatuses($type)
                ->pluck('key')
                ->all(),
        );

        return $keys;
    }

    public static function findByKeyCached(string $type, string $key): ?static
    {
        /** @var array<string, int> $map */
        $map = Cache::remember(
            static::cacheKey($type, 'active'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => collect(static::getCachedRows($type))
                ->mapWithKeys(static fn (array $row): array => [(string) ($row['key'] ?? '') => (int) ($row['id'] ?? 0)])
                ->filter(static fn (int $id, string $k): bool => $k !== '' && $id > 0)
                ->all(),
        );

        $id = $map[$key] ?? null;
        if (! is_int($id) || $id <= 0) {
            return null;
        }

        /** @var ?static */
        return static::query()->whereKey($id)->first();
    }

    protected static function cacheKey(string $type, string $suffix): string
    {
        return "statuses.{$type}.{$suffix}";
    }

    /**
     * @param array<int|string, array<string, mixed>> $definitions
     * @return list<array<string, mixed>>
     */
    protected static function normalizeDefinitions(string $type, array $definitions): array
    {
        $normalized = [];

        foreach ($definitions as $definitionKey => $definition) {
            if (! is_array($definition)) {
                throw new InvalidArgumentException('Each status definition must be an array.');
            }

            if (array_key_exists('type', $definition) && (string) $definition['type'] !== $type) {
                throw new InvalidArgumentException("Status definition type must match [{$type}].");
            }

            $key = is_string($definitionKey)
                ? trim($definitionKey)
                : trim((string) ($definition['key'] ?? ''));

            if ($key === '') {
                throw new InvalidArgumentException('Each status definition must have a non-empty key.');
            }

            if (array_key_exists('key', $definition) && trim((string) $definition['key']) !== $key) {
                throw new InvalidArgumentException("Status definition key [{$definition['key']}] does not match [{$key}].");
            }

            $attributes = $definition;
            unset($attributes['id']);

            $normalized[] = array_merge($attributes, [
                'type' => $type,
                'key' => $key,
            ]);
        }

        return $normalized;
    }

    /**
     * Cache-safe representation (no model serialization).
     *
     * @return list<array<string, mixed>>
     */
    protected static function getCachedRows(string $type): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = Cache::remember(
            static::cacheKey($type, 'list'),
            now()->addSeconds(StatusModels::cacheTtl()),
            fn (): array => static::query()
                ->forType($type)
                ->active()
                ->ordered()
                ->get()
                ->map(static fn (self $status): array => $status->getAttributes())
                ->values()
                ->all(),
        );

        return $rows;
    }

    protected static function booted(): void
    {
        $flush = static function (self $status): void {
            $types = [];

            foreach ([$status->type, $status->getOriginal('type')] as $type) {
                if (is_string($type) && $type !== '' && ! in_array($type, $types, true)) {
                    $types[] = $type;
                }
            }

            foreach ($types as $type) {
                static::flushTypeCache($type);
            }
        };

        static::saved($flush);
        static::deleted($flush);
    }

    protected static function flushTypeCache(string $type): void
    {
        Cache::forget(static::cacheKey($type, 'list'));
        Cache::forget(static::cacheKey($type, 'keys'));
        Cache::forget(static::cacheKey($type, 'active'));
    }
}
