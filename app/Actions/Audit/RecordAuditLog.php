<?php

declare(strict_types=1);

namespace App\Actions\Audit;

use App\Domain\References\AssertReference;
use App\Integrations\Esim\Support\SecretMasker;
use App\Models\AuditLog;
use App\Models\User;

final class RecordAuditLog
{
    public function __construct(
        private readonly AssertReference $references,
    ) {}

    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function __invoke(
        string $action,
        ?User $actor = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): AuditLog {
        $this->references->audit($entityType, $entityId);

        $log = new AuditLog;
        $log->user_id = $actor?->id;
        $log->action = $action;
        $log->entity_type = $entityType;
        $log->entity_id = $entityId;
        $log->old_values = $oldValues === null ? null : SecretMasker::mask($oldValues);
        $log->new_values = $newValues === null ? null : SecretMasker::mask($newValues);
        $log->ip_address = $ipAddress;
        $log->user_agent = $userAgent;
        $log->save();

        return $log;
    }
}
