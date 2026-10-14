<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimOperationStatus;
use App\Models\EsimTopup;

final class FailEsimTopup
{
    public function __invoke(EsimTopup $topup, string $reason): EsimTopup
    {
        if (in_array($topup->status, [EsimOperationStatus::Failed, EsimOperationStatus::Completed, EsimOperationStatus::Cancelled], true)) {
            return $topup;
        }

        $topup->status = EsimOperationStatus::Failed;
        $topup->save();

        return $topup;
    }
}
