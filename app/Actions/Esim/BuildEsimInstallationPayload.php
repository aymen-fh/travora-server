<?php

declare(strict_types=1);

namespace App\Actions\Esim;

use App\Domain\Esim\EsimInstallationPayload;
use App\Enums\EsimProfileStatus;
use App\Exceptions\ForbiddenOperationException;
use App\Integrations\Esim\Support\SecretMasker;
use App\Models\EsimProfile;
use App\Models\User;

final class BuildEsimInstallationPayload
{
    public function __invoke(User $user, EsimProfile $profile): EsimInstallationPayload
    {
        if ($profile->user_id !== $user->id) {
            throw new ForbiddenOperationException('eSIM profile does not belong to the actor.');
        }

        if (! in_array($profile->status, [
            EsimProfileStatus::Ready,
            EsimProfileStatus::Installed,
            EsimProfileStatus::Activated,
        ], true)) {
            throw new ForbiddenOperationException('Installation payload is only available after the eSIM is ready.');
        }

        $activationCode = $profile->activation_code_encrypted;
        $confirmation = $profile->confirmation_code_encrypted;
        $qrPayload = $this->qrPayload($activationCode, $profile->smdp_address, $profile->matching_id);

        return new EsimInstallationPayload(
            profileId: $profile->id,
            iccid: $profile->iccid,
            smdpAddress: $profile->smdp_address,
            matchingId: $profile->matching_id,
            activationCode: $activationCode,
            qrPayload: $qrPayload,
            confirmationCodeMasked: SecretMasker::maskValue($confirmation),
        );
    }

    private function qrPayload(?string $activationCode, ?string $smdp, ?string $matchingId): ?string
    {
        if (is_string($activationCode) && str_starts_with($activationCode, 'LPA:')) {
            return $activationCode;
        }

        if ($smdp !== null && $smdp !== '' && $matchingId !== null && $matchingId !== '') {
            return 'LPA:1$'.$smdp.'$'.$matchingId;
        }

        return $activationCode;
    }
}
