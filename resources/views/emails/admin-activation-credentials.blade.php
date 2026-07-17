<div style="margin:0;padding:24px 12px;background:#f5f7fb;font-family:Arial,sans-serif;color:#16324f;">
    <div style="max-width:520px;margin:0 auto;background:#ffffff;border:1px solid #dbe4ee;border-radius:16px;padding:28px 24px;">
        <div style="font-size:13px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#56708c;margin-bottom:12px;">
            Smart L&amp;D
        </div>

        <h1 style="margin:0 0 12px;font-size:24px;line-height:1.25;color:#10233a;">
            Employee ID Activation
        </h1>

        <p style="margin:0 0 20px;font-size:15px;line-height:1.7;color:#4a5d73;">
            Use this Employee ID to activate your Smart L&amp;D account.
        </p>

        <div style="margin:0 0 20px;padding:16px 18px;background:#f7fbff;border:1px solid #cfe0f2;border-radius:12px;">
            <div style="font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#6b7f95;margin-bottom:6px;">
                Employee ID
            </div>
            <div style="font-size:30px;font-weight:800;letter-spacing:0.06em;color:#0f3b68;">
                {{ $user->employee_id }}
            </div>
        </div>

        <a href="{{ $activationUrl }}" style="display:inline-block;padding:13px 20px;background:#0f5ea8;color:#ffffff;text-decoration:none;border-radius:10px;font-size:15px;font-weight:700;">
            Open Activation Page
        </a>

        <p style="margin:20px 0 0;font-size:13px;line-height:1.7;color:#6b7f95;">
            If the button does not open, use this link:
            <br>
            <a href="{{ $activationUrl }}" style="color:#0f5ea8;text-decoration:none;">{{ $activationUrl }}</a>
        </p>
    </div>
</div>
