<!doctype html>
<html lang="en">
<body style="margin:0;padding:24px;background:#f2f4f1;font-family:Arial,sans-serif;color:#0e2420">
<table role="presentation" style="max-width:520px;margin:0 auto;background:#fff;border:1px solid #d5dbd6;border-radius:12px;padding:24px;width:100%">
  <tr><td>
    <h1 style="margin:0 0 12px;font-size:22px">Welcome, {{ $user->name }}</h1>
    <p style="margin:0 0 16px">You have a {{ str_replace('_', ' ', $user->role->value) }} account on the Alcawaka Gardens reservation system. Set your password to sign in.</p>
    <p style="margin:0 0 20px"><a href="{{ $url }}" style="display:inline-block;background:#0f5c4d;color:#fff;padding:12px 20px;border-radius:8px;text-decoration:none;font-weight:bold">Set your password</a></p>
    <p style="margin:0;color:#4f615c;font-size:13px">This link works for {{ $days }} days. If you weren't expecting it, ignore this email.</p>
  </td></tr>
</table>
</body>
</html>
