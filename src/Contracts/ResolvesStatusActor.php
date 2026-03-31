<?php

declare(strict_types=1);

namespace IvanBaric\Status\Contracts;

interface ResolvesStatusActor
{
    public function resolve(): ?int;
}
