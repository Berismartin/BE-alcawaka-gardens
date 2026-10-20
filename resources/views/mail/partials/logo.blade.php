{{-- The logo atop every email and PDF. Emails carry it inline (cid:), so it shows even when the site isn't public; PDFs and previews have no $message, so they get a data URI. --}}
@php($logo = resource_path('images/mail-logo.png'))
<img src="{{ isset($message) ? $message->embed($logo) : 'data:image/png;base64,'.base64_encode(file_get_contents($logo)) }}" alt="{{ $hotel['hotel_name'] ?? 'Alcawaka Gardens' }}" width="132" style="display:block;width:132px;height:auto;margin:0 0 18px;border:0">
