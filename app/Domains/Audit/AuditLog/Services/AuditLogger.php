<?php

namespace App\Domains\Audit\AuditLog\Services;

use App\Domains\Audit\AuditLog\Repositories\AuditLogRepositoryInterface;
use Illuminate\Database\Eloquent\Model;

class AuditLogger
{
    public function __construct(private AuditLogRepositoryInterface $repository) {}

    /**
     * @param  string  $action  e.g. "facility.created", "reservation.checked_in"
     * @param  array<string, mixed>  $changes
     */
    public function record(string $action, Model $subject, array $changes = []): void
    {
        $this->repository->create([
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => (string) $subject->getKey(),
            'changes' => $changes ?: null,
            'ip' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    /**
     * Field-by-field diff for an update.
     *
     * @param  array<string, mixed>  $before  attributesToArray() taken before the change
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    public static function diff(array $before, array $after): array
    {
        $changes = [];
        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) != $value) {
                $changes[$field] = ['from' => $before[$field] ?? null, 'to' => $value];
            }
        }

        return $changes;
    }
}
