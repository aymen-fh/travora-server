<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\EsimProfile;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class EsimReady
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly EsimProfile $esim,
    ) {}
}
