<?php

declare(strict_types=1);

namespace IvanBaric\Status\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use IvanBaric\Status\Support\StatusModels;

class StatusHistory extends Model
{
    protected $table = 'status_history';

    protected $fillable = [
        'uuid',
        'status_type',
        'from_status_id',
        'from_status_key',
        'to_status_id',
        'to_status_key',
        'changed_by_user_id',
        'source',
        'reason',
        'meta',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'from_status_id' => 'int',
            'to_status_id' => 'int',
            'changed_by_user_id' => 'int',
            'meta' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function statusable(): MorphTo
    {
        return $this->morphTo();
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
