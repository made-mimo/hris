<!doctype html>
<html>
<body style="margin:0;padding:32px;background:#F5F6F8;font-family:Arial,Helvetica,sans-serif;color:#14151A;">
    <table role="presentation" width="100%" style="max-width:420px;margin:0 auto;background:#FFFFFF;border-radius:14px;padding:32px;">
        <tr><td>
            <div style="font-weight:800;font-size:14px;letter-spacing:0.4px;">SYSTEMS <span style="color:#D9251E;">INTELLIGENZ</span></div>
            <h1 style="font-size:20px;margin:24px 0 8px;">{{ $title }}</h1>
            <p style="font-size:14px;color:#4B5563;line-height:1.6;">{{ $body }}</p>
            @if($deepLink)
                <a href="{{ url($deepLink) }}" style="display:inline-block;margin-top:12px;padding:10px 18px;background:#D9251E;color:#FFFFFF;text-decoration:none;border-radius:8px;font-size:13.5px;font-weight:600;">View in HRIS</a>
            @endif
        </td></tr>
    </table>
</body>
</html>
