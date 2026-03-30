<?php

declare(strict_types=1);

namespace IvanBaric\Status\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Status\Traits\HasStatus;

class Post extends Model
{
    use HasStatus;

    protected $table = 'posts';

    protected $fillable = [
        'title',
    ];

    public function statusType(): string
    {
        return 'blog';
    }
}
