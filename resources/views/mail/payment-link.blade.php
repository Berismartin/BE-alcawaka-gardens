@php
    $reservation = $link->reservation;
    $booking = $link->facilityBooking;
    $ugx = fn (int $n) => 'UGX '.number_format($n);
@endphp
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    <p style="margin:0 0 4px;color:#4f615c;font-size:13px;text-transform:uppercase;letter-spacing:.08em">Invoice</p>
    <h1 style="margin:0 0 6px;font-size:22px">{{ $hotel['hotel_name'] }}</h1>
    <p style="margin:0 0 18px;color:#4f615c">
      @if ($reservation)
        Booking {{ $reservation->reference() }} · {{ $reservation->guest?->full_name }} · {{ $reservation->check_in->format('D j M') }} – {{ $reservation->check_out->format('D j M Y') }}
      @else
        {{ $booking->facility?->name }} · {{ $booking->guest_name }} · {{ $booking->starts_at->timezone('Africa/Kampala')->format('D j M Y, H:i') }}
      @endif
    </p>
    @if ($link->message)
      <p style="margin:0 0 18px;padding:12px;background:#f2f4f1;border-radius:8px;white-space:pre-line">{{ $link->message }}</p>
    @endif
    <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:15px">
      @if ($reservation)
        @foreach ($reservation->charges as $charge)
          <tr><td style="padding:6px 0;border-bottom:1px solid #eef1ee">{{ $charge->description }}</td><td style="padding:6px 0;border-bottom:1px solid #eef1ee;text-align:right">{{ $ugx($charge->amount_ugx) }}</td></tr>
        @endforeach
        <tr><td style="padding:6px 0;color:#4f615c">Paid so far</td><td style="padding:6px 0;text-align:right;color:#4f615c">{{ $ugx($reservation->totalPaid()) }}</td></tr>
      @else
        <tr><td style="padding:6px 0;border-bottom:1px solid #eef1ee">{{ $booking->facility?->name }} · {{ $booking->party_size }} {{ $booking->party_size === 1 ? 'person' : 'people' }}</td><td style="padding:6px 0;border-bottom:1px solid #eef1ee;text-align:right">{{ $ugx($booking->amount_ugx) }}</td></tr>
        <tr><td style="padding:6px 0;color:#4f615c">Paid so far</td><td style="padding:6px 0;text-align:right;color:#4f615c">{{ $ugx($booking->paidAmount()) }}</td></tr>
      @endif
      <tr><td style="padding:10px 0;font-weight:bold">Balance due</td><td style="padding:10px 0;text-align:right;font-weight:bold">{{ $ugx($due) }}</td></tr>
    </table>
    <p style="margin:0 0 8px"><a href="{{ $url }}" style="display:inline-block;background:#0f5c4d;color:#fff;padding:14px 22px;border-radius:8px;text-decoration:none;font-weight:bold">Pay {{ $ugx($payNow) }} now</a></p>
    <p style="margin:0 0 16px;color:#4f615c;font-size:13px">Secure payment by Pesapal: MTN Mobile Money, Airtel Money, Visa or Mastercard. The link works until {{ $link->expires_at->timezone('Africa/Kampala')->format('j M Y') }}.</p>
    <p style="margin:0;color:#4f615c;font-size:13px">Questions? {{ collect([$hotel['phone'] ?? null, $hotel['email'] ?? null])->filter()->join(' · ') }}</p>
  </td></tr>
</table>
</body>
</html>
