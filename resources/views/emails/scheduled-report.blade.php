<!doctype html>
<html>
<body style="margin:0;padding:32px;background:#F5F6F8;font-family:Arial,Helvetica,sans-serif;color:#14151A;">
    <table role="presentation" width="100%" style="max-width:420px;margin:0 auto;background:#FFFFFF;border-radius:14px;padding:32px;">
        <tr><td>
            <div style="font-weight:800;font-size:14px;letter-spacing:0.4px;">SYSTEMS <span style="color:#D9251E;">INTELLIGENZ</span></div>
            <h1 style="font-size:20px;margin:24px 0 8px;">{{ $schedule->name }}</h1>
            <p style="font-size:14px;color:#4B5563;line-height:1.6;">Your scheduled {{ $schedule->frequency }} report is attached ({{ strtoupper($schedule->format) }}).</p>
        </td></tr>
    </table>
</body>
</html>
