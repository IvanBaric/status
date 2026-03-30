# ivanbaric/status

`ivanbaric/status` is a lightweight Laravel package for attaching reusable statuses to Eloquent models.

Use it when you want to:
- keep status definitions in one place
- avoid a dedicated `status` column on every model
- share status sets across models
- keep a full status change history
- work with readable keys such as `draft`, `published`, `pending`, and `paid`

The package stores:
- status definitions in `statuses`
- the current status in `statusables`
- transition history in `status_history`

Each model has one current status at a time.

## Installation

```bash
composer require ivanbaric/status
php artisan migrate
```

Optional publish commands:

```bash
php artisan vendor:publish --tag=status-config
php artisan vendor:publish --tag=status-migrations
```

## Configuration

Transition history is enabled by default.

After publishing the config, you can disable transition history if you only want the current status:

```php
return [
    'history' => [
        'enabled' => false,
    ],
];
```

When history is disabled:
- `setStatus()` and `clearStatus()` still work
- no rows are written to `status_history`
- `statusHistory()` should not be used

## Quick start

### 1. Add the trait to your model

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Status\Traits\HasStatus;

final class Post extends Model
{
    use HasStatus;

    public function statusType(): string
    {
        return 'blog';
    }
}
```

`statusType()` tells the package which status group the model uses.

If you do not define it, the package automatically uses the snake_case model class name.

Examples:
- `Order` defaults to `order`
- `Post` defaults to `post`
- `Invoice` defaults to `invoice`
- override it only when you want a custom namespace such as `blog`

That means:
- keep the method for `Post` if you want it to use `blog`
- omit the method for `Order` if `order` is correct

### 2. Define statuses

Statuses are not created automatically. You define them in a seeder or an admin panel.

The easiest way is `Status::syncType()`:

```php
<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use IvanBaric\Status\Models\Status;

final class BlogStatusSeeder extends Seeder
{
    public function run(): void
    {
        Status::syncType('blog', [
            'draft' => [
                'name' => 'Draft',
                'tooltip' => 'The post is not public yet.',
                'color' => 'zinc',
                'icon' => 'pencil',
                'sort_order' => 1,
                'is_default' => true,
                'is_final' => false,
                'is_active' => true,
            ],
            'published' => [
                'name' => 'Published',
                'tooltip' => 'The post is visible to the public.',
                'color' => 'green',
                'icon' => 'check-circle',
                'sort_order' => 2,
                'is_default' => false,
                'is_final' => false,
                'is_active' => true,
            ],
        ]);
    }
}
```

Run the seeder:

```bash
php artisan db:seed --class=BlogStatusSeeder
```

### 3. Use statuses on the model

```php
<?php

$post = Post::query()->findOrFail(1);

$post->setStatus('draft');
$post->setStatus('published', reason: 'Content approved', source: 'manual');

$post->getStatus();
$post->getStatusKey();
$post->getStatusName();
$post->getStatusTooltip();
$post->getStatusColor();
$post->getStatusIcon();

$post->hasStatus('published');

$post->clearStatus(reason: 'Status removed manually');
```

## The `syncType()` helper

`Status::syncType()` is intended for seeders and setup code.

```php
Status::syncType('order', [
    'pending' => [
        'name' => 'Pending',
        'tooltip' => 'Waiting for payment.',
        'color' => 'amber',
        'icon' => 'clock',
        'sort_order' => 1,
        'is_default' => true,
    ],
    'paid' => [
        'name' => 'Paid',
        'tooltip' => 'Payment was captured.',
        'color' => 'green',
        'icon' => 'credit-card',
        'sort_order' => 2,
    ],
]);
```

What it does:
- creates missing statuses
- updates existing statuses
- does not create duplicates
- automatically fills `uuid` for new records

What it does not do:
- it does not delete missing statuses
- it does not deactivate missing statuses

Supported fields are the same as the `statuses` table, including:
- `name`
- `tooltip`
- `color`
- `icon`
- `sort_order`
- `is_default`
- `is_final`
- `is_active`
- `description`
- `meta`

## Most useful methods

- `setStatus('draft')`
- `setStatus('published', reason: 'Ready', source: 'manual')`
- `getStatus()`
- `getStatusKey()`
- `getStatusName()`
- `getStatusTooltip()`
- `getStatusColor()`
- `getStatusIcon()`
- `hasStatus('published')`
- `clearStatus()`
- `statusHistory()` when history is enabled

## Querying by status

Because the current status is stored in `statusables` instead of a model column, you query through the relation.

The package includes a convenience scope on models using `HasStatus`:

```php
// Uses $model->statusType()
$draftPosts = Post::query()->whereHasStatus('draft')->get();

// Or pass the status type explicitly
$draftBlogPosts = Post::query()->whereHasStatus('draft', 'blog')->get();
```

### Avoiding N+1 on index pages

If your list view renders status data for each row, remember to eager-load statuses, otherwise you can end up with N+1 queries.

```php
$posts = Post::query()
    ->whereHasStatus('draft')
    ->with('statuses')
    ->get();
```

## Validating incoming status values

Use `Status::keysFor()` when validating a request:

```php
<?php

use Illuminate\Validation\Rule;
use IvanBaric\Status\Models\Status;

$validated = validator(
    data: request()->all(),
    rules: [
        'status' => ['required', 'string', Rule::in(Status::keysFor('blog'))],
    ],
)->validate();
```

If the status group depends on a model:

```php
<?php

use App\Models\Post;
use Illuminate\Validation\Rule;
use IvanBaric\Status\Models\Status;

$post = Post::query()->findOrFail($id);

$validated = validator(
    data: request()->all(),
    rules: [
        'status' => ['required', 'string', Rule::in(Status::keysFor($post->statusType()))],
    ],
)->validate();
```

## Example: `Order`

If your model name already matches the type, you do not need to implement `statusType()`:

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use IvanBaric\Status\Traits\HasStatus;

final class Order extends Model
{
    use HasStatus;
}
```

Usage:

```php
<?php

$order->setStatus('pending', reason: 'Order created', source: 'system');
$order->setStatus('paid', reason: 'Payment captured', source: 'system');
$order->setStatus('shipped', reason: 'Handed to courier', source: 'system');
```

## Important rules

- the model must already exist before calling `setStatus()` or `clearStatus()`
- the status must exist in `statuses`
- the status must belong to the same `type` as the model `statusType()`
- inactive statuses cannot be assigned
- final statuses cannot be changed
- final statuses cannot be cleared
- setting the same current status again is a no-op

## What happens automatically

When you call `setStatus()`:
- the previous current status is removed from `statusables`
- the new status becomes the current one
- a row is written to `status_history`

When you call `clearStatus()`:
- the current status is removed
- a row is written to `status_history`

If history is disabled in config, status changes still work, but no history row is created.

## Cache helpers

The package caches active statuses per `type`.

Useful helpers:

```php
Status::getStatuses('blog');
Status::keysFor('blog');
Status::findByKeyCached('blog', 'draft');
```

The cache is automatically cleared when a status is created, updated, moved to another type, or deleted.

## Customization

You can override the package models in `config/status.php`:

```php
return [
    'history' => [
        'enabled' => true,
    ],
    'models' => [
        'status' => App\Models\Status::class,
        'status_history' => App\Models\StatusHistory::class,
    ],
];
```

You can also override `resolveStatusActorId()` on your model if you do not want to use `auth()->id()`.

## Summary

The simplest setup is:
1. add `HasStatus` to your model
2. define statuses with `Status::syncType()`
3. call `setStatus('key')`
4. use `getStatusKey()` and `hasStatus()` in your application

If the class name already matches the type you want, you do not need to implement `statusType()`.

That is enough for most projects.

## License

MIT
