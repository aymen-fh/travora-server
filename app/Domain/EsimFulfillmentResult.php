<?php

declare(strict_types=1);

namespace App\Domain;

final readonly class EsimFulfillmentResult
{
    public function __construct(
        public string $externalProfileId,
        public ?string $iccid = null,
        public ?string $matchingId = null,
        public ?string $smdpAddress = null,
        public ?string $activationCode = null,
        public ?string $confirmationCode = null,
        public ?int $dataTotalMb = null,
        public ?int $dataRemainingMb = null,
        public ?\DateTimeInterface $expiresAt = null,
    ) {}
}
