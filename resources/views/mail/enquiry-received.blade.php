<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:560px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    @include('mail.partials.logo')
    <h1 style="margin:0 0 6px;font-size:22px">New enquiry from {{ $enquiry->name }}</h1>
    <p style="margin:0 0 18px;color:#4f615c">Received {{ $enquiry->created_at->timezone('Africa/Kampala')->format('D j M Y, H:i') }}. Please call back within one working day.</p>
    <table role="presentation" style="width:100%;border-collapse:collapse;margin:0 0 18px;font-size:15px">
      <tr><td style="padding:6px 0;color:#4f615c;width:140px">Phone</td><td style="padding:6px 0"><a href="tel:{{ $enquiry->phone }}" style="color:#0f5c4d">{{ $enquiry->phone }}</a></td></tr>
      @if ($enquiry->email)
      <tr><td style="padding:6px 0;color:#4f615c">Email</td><td style="padding:6px 0"><a href="mailto:{{ $enquiry->email }}" style="color:#0f5c4d">{{ $enquiry->email }}</a></td></tr>
      @endif
      @if ($enquiry->event_type)
      <tr><td style="padding:6px 0;color:#4f615c">Planning</td><td style="padding:6px 0">{{ $enquiry->event_type }}</td></tr>
      @endif
      @if ($enquiry->event_date)
      <tr><td style="padding:6px 0;color:#4f615c">Date</td><td style="padding:6px 0">{{ $enquiry->event_date->format('D j M Y') }}</td></tr>
      @endif
      @if ($enquiry->guests)
      <tr><td style="padding:6px 0;color:#4f615c">Guests</td><td style="padding:6px 0">{{ number_format($enquiry->guests) }}</td></tr>
      @endif
    </table>
    <p style="margin:0 0 6px;color:#4f615c">Message</p>
    <p style="margin:0 0 20px;white-space:pre-line">{{ $enquiry->message }}</p>
    <p style="margin:0"><a href="{{ $portalUrl }}" style="display:inline-block;background:#0f5c4d;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Open in the portal</a></p>
  </td></tr>
</table>
</body>
</html>
