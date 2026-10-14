<?php

declare(strict_types=1);

namespace App\Models\Concerns;

trait HasCreatedAtOnly
{
    public const UPDATED_AT = null;
}
