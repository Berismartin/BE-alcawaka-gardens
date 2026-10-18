<?php

namespace App\Providers;

use App\Domains\Audit\AuditLog\Repositories\AuditLogRepository;
use App\Domains\Audit\AuditLog\Repositories\AuditLogRepositoryInterface;
use App\Domains\Channels\Channel\Repositories\ChannelRepository;
use App\Domains\Channels\Channel\Repositories\ChannelRepositoryInterface;
use App\Domains\Content\ContentEntry\Repositories\ContentEntryRepository;
use App\Domains\Content\ContentEntry\Repositories\ContentEntryRepositoryInterface;
use App\Domains\Content\Enquiry\Repositories\EnquiryRepository;
use App\Domains\Content\Enquiry\Repositories\EnquiryRepositoryInterface;
use App\Domains\Facilities\Facility\Repositories\FacilityRepository;
use App\Domains\Facilities\Facility\Repositories\FacilityRepositoryInterface;
use App\Domains\Facilities\FacilityBooking\Repositories\FacilityBookingRepository;
use App\Domains\Facilities\FacilityBooking\Repositories\FacilityBookingRepositoryInterface;
use App\Domains\Guests\Guest\Repositories\GuestRepository;
use App\Domains\Guests\Guest\Repositories\GuestRepositoryInterface;
use App\Domains\Media\Media\Repositories\MediaRepository;
use App\Domains\Media\Media\Repositories\MediaRepositoryInterface;
use App\Domains\Payments\Payment\Repositories\PaymentRepository;
use App\Domains\Payments\Payment\Repositories\PaymentRepositoryInterface;
use App\Domains\Pos\Terminal\Repositories\PosTerminalRepository;
use App\Domains\Pos\Terminal\Repositories\PosTerminalRepositoryInterface;
use App\Domains\Rates\SeasonalDiscount\Repositories\SeasonalDiscountRepository;
use App\Domains\Rates\SeasonalDiscount\Repositories\SeasonalDiscountRepositoryInterface;
use App\Domains\Reports\Report\Repositories\ReportRepository;
use App\Domains\Reports\Report\Repositories\ReportRepositoryInterface;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepository;
use App\Domains\Reservations\Reservation\Repositories\ReservationRepositoryInterface;
use App\Domains\Rooms\MaintenanceIssue\Repositories\MaintenanceIssueRepository;
use App\Domains\Rooms\MaintenanceIssue\Repositories\MaintenanceIssueRepositoryInterface;
use App\Domains\Rooms\Room\Repositories\RoomRepository;
use App\Domains\Rooms\Room\Repositories\RoomRepositoryInterface;
use App\Domains\Rooms\RoomType\Repositories\RoomTypeRepository;
use App\Domains\Rooms\RoomType\Repositories\RoomTypeRepositoryInterface;
use App\Domains\Settings\Setting\Repositories\SettingRepository;
use App\Domains\Settings\Setting\Repositories\SettingRepositoryInterface;
use App\Domains\Users\User\Repositories\UserRepository;
use App\Domains\Users\User\Repositories\UserRepositoryInterface;
use App\Models\Channels\Channel;
use App\Models\Content\ContentEntry;
use App\Models\Content\Enquiry;
use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityBooking;
use App\Models\Guests\Guest;
use App\Models\Payments\Payment;
use App\Models\Pos\PosTerminal;
use App\Models\Reservations\Reservation;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use App\Models\Rooms\SeasonalDiscount;
use App\Models\Settings\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * One place for every module: bind repository interfaces, load route files.
 * New module = one bind() below + one route file in the list.
 */
class DomainServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        FacilityRepositoryInterface::class => FacilityRepository::class,
        MediaRepositoryInterface::class => MediaRepository::class,
        AuditLogRepositoryInterface::class => AuditLogRepository::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        RoomTypeRepositoryInterface::class => RoomTypeRepository::class,
        RoomRepositoryInterface::class => RoomRepository::class,
        MaintenanceIssueRepositoryInterface::class => MaintenanceIssueRepository::class,
        SeasonalDiscountRepositoryInterface::class => SeasonalDiscountRepository::class,
        GuestRepositoryInterface::class => GuestRepository::class,
        ReservationRepositoryInterface::class => ReservationRepository::class,
        PaymentRepositoryInterface::class => PaymentRepository::class,
        FacilityBookingRepositoryInterface::class => FacilityBookingRepository::class,
        ReportRepositoryInterface::class => ReportRepository::class,
        ChannelRepositoryInterface::class => ChannelRepository::class,
        PosTerminalRepositoryInterface::class => PosTerminalRepository::class,
        ContentEntryRepositoryInterface::class => ContentEntryRepository::class,
        EnquiryRepositoryInterface::class => EnquiryRepository::class,
    ];

    /** @var list<string> */
    private const ROUTE_FILES = [
        'routes/auth/auth.php',
        'routes/facilities/facilities.php',
        'routes/media/media.php',
        'routes/audit/audit.php',
        'routes/settings/settings.php',
        'routes/rooms/rooms.php',
        'routes/guests/guests.php',
        'routes/reservations/reservations.php',
        'routes/reservations/online.php',
        'routes/payments/payments.php',
        'routes/staff/staff.php',
        'routes/reports/reports.php',
        'routes/notifications/notifications.php',
        'routes/channels/channels.php',
        'routes/pos/pos.php',
        'routes/content/content.php',
    ];

    public function boot(): void
    {
        // Short, stable names in media.mediable_type, audit_logs.subject_type and the upload API.
        // New owner model = one entry here (+ its alias in config/media.php if it owns files).
        Relation::morphMap([
            'facility' => Facility::class,
            'facility_booking' => FacilityBooking::class,
            'room_type' => RoomType::class,
            'room' => Room::class,
            'discount' => SeasonalDiscount::class,
            'guest' => Guest::class,
            'reservation' => Reservation::class,
            'payment' => Payment::class,
            'channel' => Channel::class,
            'setting' => Setting::class,
            'user' => User::class,
            'pos_terminal' => PosTerminal::class,
            'content_entry' => ContentEntry::class,
            'enquiry' => Enquiry::class,
        ]);

        Route::middleware('api')->prefix('api')->group(function () {
            foreach (self::ROUTE_FILES as $file) {
                require base_path($file);
            }
        });
    }
}
