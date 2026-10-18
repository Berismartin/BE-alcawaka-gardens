@php($b = $booking)
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    <h1 style="margin:0 0 8px;font-size:22px">You're booked for {{ $b->facility->name }}</h1>
    <p style="margin:0 0 20px">Thank you, {{ explode(' ', $b->guest_name)[0] }}. We look forward to seeing you at {{ $hotel['hotel_name'] }}.</p>
    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
      <tr><td style="padding:6px 0;color:#4f615c">Booking</td><td style="padding:6px 0;text-align:right"><strong>{{ $b->reference() }}</strong></td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">When</td><td style="padding:6px 0;text-align:right">{{ $b->starts_at->timezone('Africa/Kampala')->format('D j M Y, H:i') }}</td></tr>
      <tr><td style="padding:6px 0;color:#4f615c">Guests</td><td style="padding:6px 0;text-align:right">{{ $b->party_size }}</td></tr>
      @if ($b->paidAmount() >= $b->amount_ugx)
        <tr><td style="padding:6px 0;color:#4f615c">Paid</td><td style="padding:6px 0;text-align:right">UGX {{ number_format($b->paidAmount()) }}</td></tr>
      @else
        <tr><td style="padding:6px 0;color:#4f615c">To pay at reception</td><td style="padding:6px 0;text-align:right"><strong>UGX {{ number_format($b->amount_ugx - $b->paidAmount()) }}</strong></td></tr>
      @endif
    </table>
    <p style="margin:20px 0"><a href="{{ $viewUrl }}" style="display:inline-block;background:#0f5c4d;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">View my booking</a></p>
    <p style="margin:0;color:#4f615c;font-size:13px">Show your booking reference at reception when you arrive.{{ $hotel['phone'] ? ' Questions or changes? Call '.$hotel['phone'].'.' : '' }}</p>
  </td></tr>
</table>
</body>
</html>
