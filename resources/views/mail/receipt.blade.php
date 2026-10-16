@php($r = $reservation)
<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    <h1 style="margin:0 0 4px;font-size:22px">{{ $hotel['hotel_name'] }}</h1>
    <p style="margin:0 0 20px;color:#4f615c;font-size:13px">{{ collect([$hotel['address'], $hotel['phone'], $hotel['email']])->filter()->join(' · ') }}</p>
    <p style="margin:0 0 4px"><strong>{{ $r->guest->full_name }}</strong></p>
    <p style="margin:0 0 20px;color:#4f615c;font-size:13px">
      {{ $r->reference() }} · Room {{ $r->room->number }} · {{ $r->check_in->format('D j M') }} – {{ $r->check_out->format('D j M Y') }}
    </p>
    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px">
      @foreach ($r->charges as $c)
        <tr><td style="padding:6px 0;border-top:1px solid #e3e8e4">{{ $c->posted_on->format('j M') }} · {{ $c->description }}</td>
            <td style="padding:6px 0;border-top:1px solid #e3e8e4;text-align:right">{{ number_format($c->amount_ugx) }}</td></tr>
      @endforeach
      @foreach ($r->payments->where('status.value', 'succeeded') as $p)
        <tr><td style="padding:6px 0;border-top:1px solid #e3e8e4">{{ $p->received_at->timezone('Africa/Kampala')->format('j M') }} · {{ $p->isRefund() ? 'Refund' : 'Payment' }} · {{ $p->method->label() }}</td>
            <td style="padding:6px 0;border-top:1px solid #e3e8e4;text-align:right">{{ $p->isRefund() ? '+' : '−' }} {{ number_format($p->amount_ugx) }}</td></tr>
      @endforeach
      <tr><td style="padding:10px 0;border-top:2px solid #0e2420;font-weight:bold">Balance due</td>
          <td style="padding:10px 0;border-top:2px solid #0e2420;text-align:right;font-weight:bold">UGX {{ number_format($r->balance()) }}</td></tr>
    </table>
    @if ($hotel['receipt_footer'])
      <p style="margin:20px 0 0;color:#4f615c;font-size:13px">{{ $hotel['receipt_footer'] }}</p>
    @endif
  </td></tr>
</table>
</body>
</html>
