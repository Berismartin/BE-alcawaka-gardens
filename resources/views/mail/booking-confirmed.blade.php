@php($r = $reservation)
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    @php($first = explode(' ', $r->guest->full_name)[0])
    @if ($kind === 'cancelled')
      <h1 style="margin:0 0 8px;font-size:22px">Your booking is cancelled</h1>
      <p style="margin:0 0 20px">Hello {{ $first }}, as you asked, we have cancelled this booking. If you paid, the front desk will contact you about your refund.</p>
    @elseif ($kind === 'changed')
      <h1 style="margin:0 0 8px;font-size:22px">Your new dates are confirmed</h1>
      <p style="margin:0 0 20px">Hello {{ $first }}, we have moved your booking as you asked. Here are the new details.</p>
    @else
      <h1 style="margin:0 0 8px;font-size:22px">Your booking is confirmed</h1>
      <p style="margin:0 0 20px">Thank you, {{ $first }}. We look forward to welcoming you to {{ $hotel['hotel_name'] }}.</p>
    @endif
    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
      <tr><td style="padding:6px 0;color:#4f615c">Booking</td><td style="padding:6px 0;text-align:right"><strong>{{ $r->reference() }}</strong></td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">Room</td><td style="padding:6px 0;text-align:right">{{ $r->roomType->name }}</td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">Check-in</td><td style="padding:6px 0;text-align:right">{{ $r->check_in->format('D j M Y') }}, from {{ $hotel['check_in_time'] }}</td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">Check-out</td><td style="padding:6px 0;text-align:right">{{ $r->check_out->format('D j M Y') }}, until {{ $hotel['check_out_time'] }}</td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">Paid</td><td style="padding:6px 0;text-align:right">UGX {{ number_format($r->totalPaid()) }}</td></tr>
      @if ($kind !== 'cancelled' && $r->balance() > 0)
        <tr><td style="padding:6px 0;color:#4f615c">Balance due</td><td style="padding:6px 0;text-align:right"><strong>UGX {{ number_format($r->balance()) }}</strong></td></tr>
      @endif
    </table>
    @if ($kind !== 'cancelled')
    <p style="margin:20px 0"><a href="{{ $manageUrl }}" style="display:inline-block;background:#0f5c4d;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Manage my booking</a></p>
    @endif
    @if ($kind === 'confirmed' && $r->totalPaid() === 0 && $r->balance() > 0)
      <p style="margin:0 0 12px">Please pay UGX {{ number_format($r->balance()) }} at the front desk when you arrive: cash, card or mobile money.</p>
    @endif
    <p style="margin:0;color:#4f615c;font-size:13px">Show your booking reference at the front desk when you arrive.{{ $hotel['phone'] ? ' Questions? Call '.$hotel['phone'].'.' : '' }}</p>
  </td></tr>
</table>
</body>
</html>
