<?php

namespace App\Domains\Content\Enquiry\Services;

use App\Domains\Audit\AuditLog\Services\AuditLogger;
use App\Domains\Content\Enquiry\DTOs\CreateEnquiryDTO;
use App\Domains\Content\Enquiry\Enums\EnquiryStatus;
use App\Domains\Content\Enquiry\Repositories\EnquiryRepositoryInterface;
use App\Domains\Notifications\Services\NotificationService;
use App\Domains\Settings\Setting\Services\SettingsService;
use App\Exceptions\EntityNotFoundException;
use App\Mail\EnquiryReceivedMail;
use App\Models\Content\Enquiry;
use App\Notifications\PortalNotification;
use App\Support\Query\ListQuery;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Event and contact enquiries sent from the website. */
class EnquiryService
{
    public function __construct(
        private EnquiryRepositoryInterface $repository,
        private NotificationService $notifications,
        private AuditLogger $audit,
        private SettingsService $settings,
    ) {}

    public function create(CreateEnquiryDTO $dto): Enquiry
    {
        $enquiry = $this->repository->create($dto->toArray());
        $what = $dto->eventType ? "{$dto->eventType} enquiry" : 'New enquiry';
        $this->notifications->notify('enquiries.manage', new PortalNotification(
            "{$what} from {$dto->name}",
            $dto->eventDate ? "For {$dto->eventDate}. Call {$dto->phone}." : "Call {$dto->phone}.",
            "/portal/enquiries/{$enquiry->id}",
            'i-lucide-inbox',
        ));
        DB::afterCommit(fn () => $this->emailHotel($enquiry));

        return $enquiry;
    }

    /** A copy to the hotel email in Settings, after the enquiry is saved. A mail outage never loses the enquiry. */
    private function emailHotel(Enquiry $enquiry): void
    {
        $to = $this->settings->get('email');
        if (! $to) {
            return;
        }
        try {
            Mail::to($to)->send(new EnquiryReceivedMail($enquiry->refresh(), rtrim(config('app.frontend_url'), '/')."/portal/enquiries/{$enquiry->id}"));
        } catch (Throwable $e) {
            report($e);
        }
    }

    public function paginate(ListQuery $query): LengthAwarePaginator
    {
        return $this->repository->paginate($query);
    }

    public function find(string $id): Enquiry
    {
        return $this->repository->findById($id) ?? throw new EntityNotFoundException('Enquiry', $id);
    }

    /**
     * Leaving "new" marks it attended (by whoever did it, once); going back to "new" clears that.
     *
     * @param  array{status?: string, notes?: ?string}  $data
     */
    public function update(string $id, array $data): Enquiry
    {
        $enquiry = $this->find($id);
        $before = $enquiry->attributesToArray();
        if (isset($data['status'])) {
            if ($data['status'] === EnquiryStatus::New->value) {
                $data += ['attended_by' => null, 'attended_at' => null];
            } elseif (! $enquiry->attended_at) {
                $data += ['attended_by' => auth()->id(), 'attended_at' => now()];
            }
        }

        return DB::transaction(function () use ($enquiry, $data, $before) {
            $updated = $this->repository->update($enquiry, $data);
            $this->audit->record('enquiry.updated', $updated, AuditLogger::diff($before, $data));

            return $updated;
        });
    }
}
