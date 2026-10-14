<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Enums\EsimOperationStatus;
use App\Models\EsimRenewal;

final class FailEsimRenewal
{
    public function __invoke(EsimRenewal $renewal, string $reason): EsimRenewal
    {
        if (in_array($renewal->status, [EsimOperationStatus::Failed, EsimOperationStatus::Completed, EsimOperationStatus::Cancelled], true)) {
            return $renewal;
        }

        $renewal->status = EsimOperationStatus::Failed;
        $renewal->save();

        return $renewal;
    }
}
