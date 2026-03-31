<?php

declare(strict_types=1);

namespace IvanBaric\Status\Support\Actors;

use IvanBaric\Status\Contracts\ResolvesStatusActor;

class AuthStatusActorResolver implements ResolvesStatusActor
{
    public function resolve(): ?int
    {
        return auth()->id();
    }
}
