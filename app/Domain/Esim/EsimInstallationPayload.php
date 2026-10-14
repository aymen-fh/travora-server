<?php

declare(strict_types=1);

namespace App\Domain\Esim;

final readonly class EsimInstallationPayload
{
    public function __construct(
        public string $profileId,
        public ?string $iccid = null,
        public ?string $smdpAddress = null,
        public ?string $matchingId = null,
        public ?string $activationCode = null,
        public ?string $qrPayload = null,
        public ?string $confirmationCodeMasked = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'profile_id' => $this->profileId,
            'iccid' => $this->iccid,
            'smdp_address' => $this->smdpAddress,
            'matching_id' => $this->matchingId,
            'activation_code' => $this->activationCode,
            'qr_payload' => $this->qrPayload,
            'confirmation_code_masked' => $this->confirmationCodeMasked,
        ];
    }
}
