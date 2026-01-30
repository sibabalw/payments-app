<tr>
    <td style="background-color: #f9fafb; padding: 30px; text-align: center; border-top: 1px solid #e5e7eb;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center" style="padding-bottom: 20px;">
                    <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">
                        © {{ date('Y') }} {{ $business->name ?? 'SwiftPay' }}. All rights reserved.
                    </p>
                    <p style="margin: 0 0 12px 0; color: #6b7280; font-size: 14px;">
                        This email was sent to {{ $user->email ?? 'you' }}.
                    </p>
                    @if(isset($unsubscribeUrl))
                    <p style="margin: 0; color: #6b7280; font-size: 12px;">
                        <a href="{{ $unsubscribeUrl }}" style="color: #6b7280; text-decoration: underline;">Unsubscribe from these emails</a>
                    </p>
                    @endif
                </td>
            </tr>
        </table>
    </td>
</tr>
