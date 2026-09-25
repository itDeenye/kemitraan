<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
</head>
<body style="margin:0;background:#fff7f8;font-family:Arial,Helvetica,sans-serif;color:#261b1d;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#fff7f8;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:600px;background:#ffffff;border:1px solid #f1d7dc;border-radius:18px;overflow:hidden;">
                    <tr>
                        <td style="background:#ffffff;padding:20px 32px;text-align:center;border-bottom:1px solid #f1d7dc;">
                            <img src="{{ asset('logo-email.png') }}" alt="DNY Skincare" style="height:80px;width:auto;display:inline-block;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background:#a90028;padding:26px 32px;color:#ffffff;">
                            <div style="font-size:13px;letter-spacing:.08em;text-transform:uppercase;opacity:.9;">DNY Skincare Kemitraan</div>
                            <div style="font-size:25px;font-weight:700;margin-top:8px;">@yield('heading')</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            @yield('content')
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:18px 32px;background:#faf7f7;color:#8b7378;font-size:12px;line-height:1.5;text-align:center;">Email ini dikirim otomatis oleh sistem DNY Skincare Kemitraan.</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
