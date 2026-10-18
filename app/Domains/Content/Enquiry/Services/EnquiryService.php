<?php

namespace App\Domains\Content\Enquiry\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Content\Enquiry\DTOs\CreateEnquiryDTO;
use App\Domains\Content\Enquiry\Repositories\EnquiryRepositoryInterface;
use App\Domains\Notifications\Services\NotificationService;
use App\Exceptions\EntityNotFoundException;
use App\Models\Content\Enquiry;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Event and contact enquiries sent from the website. */
class EnquiryService
{
    public function __construct(
        private EnquiryRepositoryInterface $repository,
        private NotificationService $notifications,
        private AuditLogger $audit,
    ) {}

    public function create(CreateEnquiryDTO $dto): Enquiry
    {
        $enquiry = $this->repository->create($dto->toArray());
        $what = $dto->eventType ? "{$dto->eventType} enquiry" : 'New enquiry';
        $this->notifications->notify('enquiries.manage', new PortalNotification(
            "{$what} from {$dto->name}",
            $dto->eventDate ? "For {$dto->eventDate}. Call {$dto->phone}." : "Call {$dto->phone}.",
            '/portal/enquiries',
            'i-lucide-inbox',
        ));

        return $enquiry;
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): Enquiry
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Enquiry', $id);
    }

    /** @param array{status?: string, notes?: ?string} $data */
    public function update(string $id, array $data): Enquiry
    {
        $enquiry = $this->find($id);
        $before = $enquiry->attributesToArray();

        return DB::transaction(function () use ($enquiry, $data, $before) {
            $updated = $this->repository->update($enquiry, $data);
            $this->audit->record('enquiry.updated', $updated, AuditLogger::diff($before, $data));

            return $updated;
        });
    }
}
