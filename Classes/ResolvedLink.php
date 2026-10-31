<?php

declare(strict_types=1);

namespace Plan2net\LinkAlchemy;

final class ResolvedLink
{
    public function __construct(
        public readonly string $uri,
        public readonly string $type,
        public readonly int $id,
        public readonly string $label,
    ) {
    }
}
