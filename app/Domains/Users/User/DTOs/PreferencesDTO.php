<?php

namespace App\Domains\Users\User\DTOs;

/** UI preferences that follow a staff member across devices. Partial: only sent keys change. */
final readonly class PreferencesDTO
{
    public const MODES = ['light', 'dark', 'system'];

    public const ACCENTS = ['forest', 'ocean', 'clay', 'plum', 'slate'];

    public const DENSITIES = ['comfortable', 'compact'];

    public const DEFAULTS = [
        'mode' => 'system',
        'accent' => 'forest',
        'sidebar_collapsed' => false,
        'table_density' => 'comfortable',
        // Guided tours in the portal: the ones this person has finished or skipped, or all switched off.
        'tours_off' => false,
        'tours_seen' => [],
    ];

    /** @param array<string, mixed> $values */
    public function __construct(public array $values) {}

    /** @param array<string, mixed> $data */
    public static function fromRequest(array $data): self
    {
        return new self(array_intersect_key($data, self::DEFAULTS));
    }

    /**
     * @param  array<string, mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function resolve(?array $stored): array
    {
        return [...self::DEFAULTS, ...array_intersect_key($stored ?? [], self::DEFAULTS)];
    }
}
