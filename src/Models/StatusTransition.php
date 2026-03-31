<?php

declare(strict_types=1);

namespace IvanBaric\Status\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use IvanBaric\Status\Support\StatusModels;

class StatusTransition extends Model
{
    protected $table = 'status_transitions';

    protected $fillable = [
        'uuid',
        'from_status_id',
        'to_status_id',
        'is_active',
        'label',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'from_status_id' => 'int',
            'to_status_id' => 'int',
            'is_active' => 'bool',
            'meta' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $transition): void {
            $transition->uuid ??= (string) Str::uuid();
        });
    }

    public function fromStatus(): BelongsTo
    {
        return $this->belongsTo(StatusModels::status(), 'from_status_id');
    }

    public function toStatus(): BelongsTo
    {
        return $this->belongsTo(StatusModels::status(), 'to_status_id');
    }
}
