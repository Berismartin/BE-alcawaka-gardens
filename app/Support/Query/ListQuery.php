<?php

namespace App\Support\Query;

/** Page, search, sort, filters and date range for any paginated list endpoint. */
final readonly class ListQuery
{
    public const PER_PAGE = [10, 25, 50, 100];

    /** @param array<string, mixed> $filters keys already whitelisted by the request */
    public function __construct(
        public int $page = 1,
        public int $perPage = 25,
        public ?string $search = null,
        public ?string $sort = null,
        public array $filters = [],
        public ?string $from = null,
        public ?string $to = null,
    ) {}

    public function sortField(): ?string
    {
        return $this->sort === null ? null : ltrim($this->sort, '-');
    }

    public function sortDescending(): bool
    {
        return str_starts_with((string) $this->sort, '-');
    }
}
