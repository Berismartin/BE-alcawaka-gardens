<?php

namespace Database\Seeders;

use App\Models\Audit\AuditLog;
use App\Models\Channels\Channel;
use App\Models\Facilities\Facility;
use App\Models\Facilities\FacilityBooking;
use App\Models\Guests\Guest;
use App\Models\Payments\Payment;
use App\Models\Reservations\FolioCharge;
use App\Models\Reservations\Reservation;
use App\Models\Reservations\ReservationNight;
use App\Models\Rooms\Room;
use App\Models\Rooms\RoomType;
use App\Models\Rooms\SeasonalDiscount;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local demo data: a year of stays (for reports) plus a busy "today" for the
 * front desk, housekeeping and facilities screens. Placeholder names and
 * prices only; replace with the client's real data before launch.
 */
class DemoDataSeeder extends Seeder
{
    private CarbonImmutable $today;

    /** @var array<string, list<array{0: string, 1: string}>> room id => booked [from, to) ranges */
    private array $taken = [];

    private int $number = 10000;

    private ?int $deskUserId = null;

    public function run(): void
    {
        if (Room::exists()) {
            return; // already seeded
        }
        mt_srand(42);
        $this->today = CarbonImmutable::now('Africa/Kampala')->startOfDay();
        $this->deskUserId = User::where('email', 'frontdesk@alcawaka.test')->value('id');

        $types = [
            'standard' => RoomType::create(['name' => 'Standard Room', 'slug' => 'standard-room', 'base_rate_ugx' => 180000, 'max_guests' => 2, 'bed_type' => 'Queen bed', 'features' => ['Wi-Fi', 'Garden view'], 'description' => 'A calm room with a queen bed and a view of the gardens.']),
            'deluxe' => RoomType::create(['name' => 'Deluxe Room', 'slug' => 'deluxe-room', 'base_rate_ugx' => 260000, 'max_guests' => 3, 'bed_type' => 'King bed', 'features' => ['Wi-Fi', 'Balcony'], 'description' => 'More space, a king bed and a private balcony.']),
            'suite' => RoomType::create(['name' => 'Executive Suite', 'slug' => 'executive-suite', 'base_rate_ugx' => 380000, 'max_guests' => 4, 'bed_type' => 'King bed', 'features' => ['Lounge', 'Work desk', 'Wi-Fi'], 'description' => 'A separate lounge and work desk for longer stays.']),
        ];
        $rooms = collect([
            ['101', 1, 'standard'], ['102', 1, 'standard'], ['103', 1, 'standard'], ['104', 1, 'deluxe'],
            ['105', 1, 'deluxe'], ['106', 1, 'deluxe'], ['107', 1, 'suite'], ['108', 1, 'suite'],
            ['201', 2, 'standard'], ['202', 2, 'standard'], ['203', 2, 'standard'], ['204', 2, 'deluxe'],
        ])->map(fn ($r) => Room::create(['number' => $r[0], 'floor' => $r[1], 'room_type_id' => $types[$r[2]]->id, 'status' => 'available', 'status_changed_at' => now()->subHours(3)]));

        $year = $this->today->month === 12 ? $this->today->year : $this->today->year;
        SeasonalDiscount::create(['name' => 'Festive season', 'starts_on' => "{$year}-12-15", 'ends_on' => ($year + 1).'-01-05', 'percent' => 10]);
        SeasonalDiscount::create(['name' => 'Low season', 'starts_on' => ($year + 1).'-04-01', 'ends_on' => ($year + 1).'-04-30', 'percent' => 15, 'room_type_id' => $types['standard']->id]);

        $guests = collect([
            'Amina Nakato', 'Daniel Opio', 'Peter Okello', 'Hellen Akello', 'Joseph Mugisha', 'Brenda Kizza', 'Moses Ssali',
            'Grace Mutesi', 'Patrick Odongo', 'Timothy Mwesigwa', 'Sarah Kabuye', 'Martin Byaruhanga', 'Joan Atwine',
            'Lydia Atim', 'Emmanuel Sekandi', 'Florence Wanyama', 'Stella Tumusiime', 'Robert Lubega', 'Vivian Nanteza',
            'Catherine Namutebi', 'Michael Ochen', 'Kevin Namatovu', 'Ruth Nabirye', 'Isaac Kato', 'Esther Nansubuga',
        ])->map(fn ($name, $i) => Guest::create([
            'full_name' => $name,
            'phone' => '+2567'.str_pad((string) (70000000 + $i * 1234567 % 29999999), 8, '0', STR_PAD_LEFT),
            'email' => Str::slug($name, '.').'@example.com',
        ]));

        // History: about a year of finished stays, so reports have something to show.
        foreach (range(330, 3) as $daysAgo) {
            if (mt_rand(1, 100) > 72) {
                continue;
            }
            $room = $rooms->random();
            $nights = min(mt_rand(1, 4), $daysAgo - 1); // history ends before today
            $in = $this->today->subDays($daysAgo);
            $this->stay($room, $guests->random(), $in, $in->addDays($nights), 'checked_out', paid: true);
        }

        // Today: in house, leaving, arriving, and coming up.
        $byNumber = $rooms->keyBy('number');
        $g = $guests->keyBy('full_name');
        $this->stay($byNumber['102'], $g['Joseph Mugisha'], $this->today->subDays(2), $this->today->addDay(), 'checked_in', paidAmount: 200000, extras: [['restaurant', 'Dinner', 45000, 1], ['laundry', 'Shirts and trousers', 20000, 0]]);
        $this->stay($byNumber['104'], $g['Brenda Kizza'], $this->today->subDays(3), $this->today, 'checked_in', paid: true, source: 'website');
        $this->stay($byNumber['107'], $g['Martin Byaruhanga'], $this->today->subDays(4), $this->today, 'checked_in', paidAmount: 1400000, source: 'phone', extras: [['bar', 'Drinks', 120000, 1]]);
        $this->stay($byNumber['106'], $g['Timothy Mwesigwa'], $this->today->subDay(), $this->today->addDays(2), 'checked_in', paid: true, source: 'ota');
        $this->stay($byNumber['108'], $g['Lydia Atim'], $this->today->subDays(1), $this->today->addDays(3), 'checked_in', paidAmount: 380000, source: 'website');
        $this->stay($byNumber['202'], $g['Robert Lubega'], $this->today->subDays(2), $this->today->addDays(1), 'checked_in', paid: true);
        $this->stay($byNumber['105'], $g['Moses Ssali'], $this->today->subDays(2), $this->today, 'checked_out', paid: true, checkedOutToday: true);

        $this->stay($byNumber['103'], $g['Amina Nakato'], $this->today, $this->today->addDays(2), 'confirmed', paid: true, source: 'website', requests: 'Quiet room, late arrival');
        $this->stay($byNumber['101'], $g['Peter Okello'], $this->today, $this->today->addDays(3), 'confirmed', paid: true, source: 'ota');
        $this->stay($byNumber['203'], $g['Daniel Opio'], $this->today, $this->today->addDay(), 'pending_payment', source: 'website');
        $this->stay($byNumber['204'], $g['Hellen Akello'], $this->today, $this->today->addDays(4), 'confirmed', paidAmount: 260000, source: 'phone');

        foreach ([[3, 'Grace Mutesi', '105'], [4, 'Patrick Odongo', '104'], [5, 'Sarah Kabuye', '106'], [6, 'Joan Atwine', '107'], [8, 'Emmanuel Sekandi', '108'], [9, 'Florence Wanyama', '101'], [10, 'Stella Tumusiime', '202'], [12, 'Vivian Nanteza', '103'], [13, 'Catherine Namutebi', '204'], [15, 'Michael Ochen', '104']] as [$offset, $name, $number]) {
            $in = $this->today->addDays($offset);
            $this->stay($byNumber[$number], $g[$name], $in, $in->addDays(mt_rand(1, 3)), 'confirmed', paid: mt_rand(0, 1) === 1, source: ['website', 'front_desk', 'ota', 'phone'][mt_rand(0, 3)]);
        }
        $cancelled = $this->stay($byNumber['203'], $g['Isaac Kato'], $this->today->addDays(6), $this->today->addDays(8), 'cancelled');
        $cancelled->update(['cancel_reason' => 'Guest changed travel plans']);

        // Housekeeping picture for today.
        $occupied = Reservation::where('status', 'checked_in')->pluck('room_id');
        Room::whereIn('id', $occupied)->update(['status' => 'occupied']);
        $byNumber['105']->update(['status' => 'needs_cleaning', 'status_changed_at' => now()->subMinutes(40)]);
        $byNumber['201']->update(['status' => 'under_maintenance', 'status_note' => 'Air conditioner repair', 'status_changed_at' => now()->subDays(2)]);
        $byNumber['103']->update(['status' => 'available', 'status_changed_at' => now()->subHours(2), 'status_changed_by' => User::where('email', 'housekeeping@alcawaka.test')->value('id')]);

        // Facilities in use.
        $facilities = Facility::all()->keyBy('slug');
        if ($pool = $facilities['swimming-pool'] ?? null) {
            $booking = FacilityBooking::create(['facility_id' => $pool->id, 'guest_name' => 'Kevin Namatovu', 'guest_phone' => '+256772555111', 'starts_at' => $this->today->setTime(10, 0)->utc(), 'party_size' => 4, 'charge_mode' => 'paid', 'amount_ugx' => 80000, 'status' => 'confirmed']);
            Payment::create(['facility_booking_id' => $booking->id, 'kind' => 'payment', 'method' => 'mtn_momo', 'amount_ugx' => 80000, 'status' => 'succeeded', 'reference' => 'MP'.mt_rand(100000, 999999), 'received_at' => now()->subHours(1), 'recorded_by' => $this->deskUserId]);
        }
        if ($gardens = $facilities['gardens'] ?? null) {
            FacilityBooking::create(['facility_id' => $gardens->id, 'guest_name' => 'Akello family', 'starts_at' => $this->today->addDays(2)->setTime(15, 0)->utc(), 'party_size' => 40, 'charge_mode' => 'pay_later', 'amount_ugx' => 300000, 'status' => 'pending_payment', 'notes' => 'Birthday celebration']);
        }
        if ($events = $facilities['event-and-meeting-space'] ?? null) {
            FacilityBooking::create(['facility_id' => $events->id, 'guest_name' => 'Company meeting', 'starts_at' => $this->today->addDays(3)->setTime(9, 0)->utc(), 'party_size' => 12, 'charge_mode' => 'pay_later', 'amount_ugx' => 250000, 'status' => 'pending_payment']);
        }

        // A pending mobile-money payment waiting for approval, and a failed card attempt.
        $waiting = Reservation::where('status', 'pending_payment')->first();
        Payment::create(['reservation_id' => $waiting->id, 'kind' => 'payment', 'method' => 'mtn_momo', 'amount_ugx' => 180000, 'status' => 'pending', 'reference' => 'MP'.mt_rand(100000, 999999), 'received_at' => now()->subMinutes(25)]);
        $failed = Reservation::where('status', 'confirmed')->where('check_in', '>', $this->today->toDateString())->first();
        Payment::create(['reservation_id' => $failed->id, 'kind' => 'payment', 'method' => 'card', 'amount_ugx' => 260000, 'status' => 'failed', 'reference' => 'Card declined', 'received_at' => now()->subMinutes(70)]);

        Channel::create(['name' => 'Sample OTA listing', 'room_id' => $byNumber['104']->id, 'export_token' => Str::random(40), 'is_active' => true]);

        // A few staff from the design, so the staff list isn't just demo logins.
        foreach ([['Ruth Namusoke', 'front_desk'], ['Sarah Nabirye', 'housekeeping'], ['Joan Kyomuhendo', 'housekeeping']] as [$name, $role]) {
            $u = new User;
            $u->forceFill(['name' => $name, 'email' => Str::slug($name, '.').'@alcawaka.test', 'password' => Str::random(32), 'role' => $role, 'last_active_at' => now()->subHours(mt_rand(1, 30))])->save();
        }
    }

    /**
     * One stay with its nights, room charge, extras and payments.
     *
     * @param  list<array{0: string, 1: string, 2: int, 3: int}>  $extras  [category, description, amount, days after check-in]
     */
    private function stay(Room $room, Guest $guest, CarbonImmutable $in, CarbonImmutable $out, string $status, bool $paid = false, ?int $paidAmount = null, string $source = 'front_desk', array $extras = [], ?string $requests = null, bool $checkedOutToday = false): Reservation
    {
        foreach ($this->taken[$room->id] ?? [] as [$from, $to]) {
            if ($in->toDateString() < $to && $out->toDateString() > $from) {
                return new Reservation; // overlaps an earlier stay in this room: skip
            }
        }
        if (! in_array($status, ['cancelled', 'no_show', 'expired'], true)) {
            $this->taken[$room->id][] = [$in->toDateString(), $out->toDateString()];
        }
        $type = $room->type;
        $nights = (int) $in->diffInDays($out);
        $source = $status === 'checked_out' && ! $checkedOutToday ? ['website', 'website', 'front_desk', 'ota', 'phone'][mt_rand(0, 4)] : $source;
        $createdAt = $in->subDays(mt_rand(0, 14))->setTime(mt_rand(7, 20), mt_rand(0, 59));

        $r = Reservation::create([
            'number' => ++$this->number,
            'guest_id' => $guest->id,
            'room_id' => $room->id,
            'room_type_id' => $type->id,
            'check_in' => $in->toDateString(),
            'check_out' => $out->toDateString(),
            'adults' => min(2, $type->max_guests),
            'status' => $status,
            'source' => $source,
            'special_requests' => $requests,
            'checked_in_at' => in_array($status, ['checked_in', 'checked_out'], true) ? $in->setTime(14, 20)->utc() : null,
            'checked_out_at' => $status === 'checked_out' ? ($checkedOutToday ? now()->subHour() : $out->setTime(10, 5)->utc()) : null,
            'cancelled_at' => $status === 'cancelled' ? now()->subDay() : null,
            'created_by' => $source === 'front_desk' ? $this->deskUserId : null,
        ]);
        $r->forceFill(['created_at' => $createdAt->utc(), 'updated_at' => $createdAt->utc()])->saveQuietly();

        if (! in_array($status, ['cancelled', 'no_show', 'expired'], true)) {
            for ($d = $in; $d->lt($out); $d = $d->addDay()) {
                ReservationNight::create(['reservation_id' => $r->id, 'room_id' => $room->id, 'night' => $d->toDateString(), 'rate_ugx' => $type->base_rate_ugx]);
            }
        }
        $total = $nights * $type->base_rate_ugx;
        FolioCharge::create(['reservation_id' => $r->id, 'category' => 'room', 'description' => "Room charge · {$nights} ".($nights === 1 ? 'night' : 'nights').' × '.number_format($type->base_rate_ugx), 'amount_ugx' => $total, 'posted_on' => $in->toDateString()]);

        if ($status === 'checked_out' && ! $checkedOutToday && mt_rand(1, 100) <= 45) {
            $extras[] = [['restaurant', 'Dinner', mt_rand(2, 9) * 5000, 0], ['bar', 'Drinks', mt_rand(2, 8) * 5000, 0], ['laundry', 'Shirts', mt_rand(1, 4) * 5000, 0]][mt_rand(0, 2)];
        }
        foreach ($extras as [$category, $description, $amount, $after]) {
            FolioCharge::create(['reservation_id' => $r->id, 'category' => $category, 'description' => $description, 'amount_ugx' => $amount, 'posted_on' => $in->addDays(min($after, max(0, $nights - 1)))->toDateString()]);
            $total += $amount;
        }

        $amount = $paid ? $total : $paidAmount;
        if ($amount) {
            $method = ['mtn_momo', 'airtel_money', 'cash', 'pos', 'card', 'mtn_momo'][mt_rand(0, 5)];
            $when = $status === 'checked_out' ? $out->setTime(9, mt_rand(0, 59)) : ($in->lte($this->today) ? $in->setTime(14, 30) : $createdAt->addMinutes(3));
            Payment::create(['reservation_id' => $r->id, 'kind' => 'payment', 'method' => $method, 'amount_ugx' => $amount, 'status' => 'succeeded', 'reference' => in_array($method, ['mtn_momo', 'airtel_money'], true) ? 'MP'.mt_rand(100000, 999999) : null, 'received_at' => min($when->utc(), now()), 'recorded_by' => in_array($method, ['cash', 'pos'], true) ? $this->deskUserId : null]);
        }

        // Timeline entries for current bookings (history keeps its record lighter).
        if ($in->gte($this->today->subDays(5))) {
            $log = fn (string $action, $at, array $changes = []) => AuditLog::create(['user_id' => $source === 'front_desk' ? $this->deskUserId : null, 'action' => $action, 'subject_type' => 'reservation', 'subject_id' => $r->id, 'changes' => $changes ?: null, 'created_at' => $at]);
            $log('reservation.created', $createdAt->utc(), ['room' => ['from' => null, 'to' => $room->number], 'source' => ['from' => null, 'to' => $source]]);
            if ($amount) {
                $log('payment.recorded', min($createdAt->addMinutes(3)->utc(), now()), ['amount_ugx' => ['from' => null, 'to' => $amount]]);
            }
            if (in_array($status, ['checked_in', 'checked_out'], true)) {
                $log('reservation.checked_in', $in->setTime(14, 20)->utc(), ['room' => ['from' => null, 'to' => $room->number]]);
            }
            if ($status === 'cancelled') {
                $log('reservation.cancelled', now()->subDay(), ['reason' => ['from' => null, 'to' => 'Guest changed travel plans']]);
            }
        }

        return $r;
    }
}
