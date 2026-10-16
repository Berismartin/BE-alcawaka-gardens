<?php

namespace App\Domains\Settings\Setting\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Settings\Setting\Repositories\SettingRepositoryInterface;
use App\Models\Settings\Setting;
use Illuminate\Support\Facades\DB;

/** Hotel profile and policies. Unset keys fall back to these defaults. */
class SettingsService
{
    public const DEFAULTS = [
        'hotel_name' => 'Alcawaka Gardens',
        'phone' => null,
        'email' => null,
        'address' => null,
        'check_in_time' => '14:00',
        'check_out_time' => '10:00',
        'cancellation_policy' => null,
        'receipt_footer' => 'Thank you for staying with us.',
    ];

    private ?array $cache = null;

    public function __construct(
        private SettingRepositoryInterface $repository,
        private AuditLogger $audit,
    ) {}

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->cache ??= array_merge(self::DEFAULTS, array_intersect_key($this->repository->all(), self::DEFAULTS));
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? null;
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): array
    {
        $values = array_intersect_key($values, self::DEFAULTS);
        $before = $this->all();
        DB::transaction(function () use ($values, $before) {
            $this->repository->putMany($values);
            $this->audit->record('settings.updated', new Setting(['key' => 'hotel']), AuditLogger::diff($before, $values));
        });
        $this->cache = null;

        return $this->all();
    }
}
