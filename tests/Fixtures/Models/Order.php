<?php

declare(strict_types=1);

namespace IvanBaric\Status\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Status\Traits\HasStatus;

class Order extends Model
{
    use HasStatus;

    protected $table = 'orders';

    protected $fillable = [
        'number',
    ];

    protected function statusType(): string
    {
        return 'order';
    }
}
