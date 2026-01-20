<tr>
    <td style="background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%); padding: 40px 30px; text-align: center;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        @if(isset($business) && $business?->logo)
                            <img
                                src="{{ $business->logo }}"
                                alt="{{ $business->name ?? 'Business logo' }}"
                                style="max-width: 160px; max-height: 64px; display: block;"
                            >
                        @else
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 600; margin: 0; letter-spacing: -0.5px;">
                                {{ $business->name ?? 'Swift Pay' }}
                            </h1>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </td>
</tr>
