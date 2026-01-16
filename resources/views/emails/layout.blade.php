<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $subject ?? 'Swift Pay' }}</title>
    <style>
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }
        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            outline: none;
            text-decoration: none;
        }

        /* Base styles */
        body {
            margin: 0;
            padding: 0;
            width: 100% !important;
            height: 100% !important;
            background-color: #f5f5f5;
            font-family: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #1a1a1a;
        }

        .email-wrapper {
            width: 100%;
            background-color: #f5f5f5;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .email-content {
            padding: 40px 30px;
        }

        h1 {
            font-size: 28px;
            font-weight: 600;
            line-height: 1.3;
            margin: 0 0 20px 0;
            color: #1a1a1a;
        }

        h2 {
            font-size: 24px;
            font-weight: 600;
            line-height: 1.3;
            margin: 0 0 16px 0;
            color: #1a1a1a;
        }

        p {
            margin: 0 0 16px 0;
            color: #4a4a4a;
        }

        .text-muted {
            color: #6b7280;
            font-size: 14px;
        }

        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            body {
                background-color: #1a1a1a;
            }
            .email-container {
                background-color: #2a2a2a;
            }
            h1, h2 {
                color: #f5f5f5;
            }
            p {
                color: #d1d5db;
            }
            .text-muted {
                color: #9ca3af;
            }
        }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .email-content {
                padding: 30px 20px;
            }
            h1 {
                font-size: 24px;
            }
            h2 {
                font-size: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding: 20px 0;">
                    <table role="presentation" class="email-container" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width: 600px; width: 100%;">
                        <!-- Header -->
                        @include('emails.components.header', ['user' => $user ?? null])

                        <!-- Content -->
                        <tr>
                            <td class="email-content">
                                @yield('content')
                            </td>
                        </tr>

                        <!-- Footer -->
                        @include('emails.components.footer', ['user' => $user ?? null, 'unsubscribeUrl' => $unsubscribeUrl ?? null])
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
