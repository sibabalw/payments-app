<tr>
    <td style="background: linear-gradient(135deg, #1a1a1a 0%, #2a2a2a 100%); padding: 40px 30px; text-align: center;">
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
            <tr>
                <td align="center">
                    <div style="display: inline-block; background-color: rgba(255, 255, 255, 0.1); border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                        @php
                            // Log business info for debugging
                            \Illuminate\Support\Facades\Log::info('Email header component - business check', [
                                'has_business' => isset($business),
                                'business_id' => $business->id ?? null,
                                'business_name' => $business->name ?? null,
                                'has_logo' => isset($business->logo),
                                'logo_value' => $business->logo ?? null,
                                'logo_trimmed' => isset($business->logo) ? trim($business->logo) : null,
                                'logo_not_empty' => isset($business->logo) && trim($business->logo) !== '',
                            ]);
                        @endphp
                        @if(isset($business) && $business?->logo && trim($business->logo) !== '')
                            @php
                                // Convert logo to base64 data URI for email embedding
                                $logoDataUri = '';
                                $logoPath = $business->logo;
                                
                                // Log for debugging
                                \Illuminate\Support\Facades\Log::info('Email logo processing', [
                                    'logo_path' => $logoPath,
                                    'business_id' => $business->id ?? null,
                                    'business_name' => $business->name ?? null,
                                    'logo_exists' => $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath) : false,
                                ]);
                                
                                try {
                                    // Check if it's already a URL or data URI
                                    if (filter_var($logoPath, FILTER_VALIDATE_URL) || str_starts_with($logoPath, 'data:')) {
                                        $logoDataUri = $logoPath;
                                    } elseif ($logoPath && \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath)) {
                                        $logoContents = \Illuminate\Support\Facades\Storage::disk('public')->get($logoPath);
                                        $mimeType = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($logoPath) ?: 'image/png';
                                        $base64 = base64_encode($logoContents);
                                        $logoDataUri = "data:{$mimeType};base64,{$base64}";
                                        
                                        \Illuminate\Support\Facades\Log::info('Logo converted to base64', [
                                            'mime_type' => $mimeType,
                                            'base64_size' => strlen($base64),
                                            'data_uri_length' => strlen($logoDataUri),
                                        ]);
                                    } else {
                                        \Illuminate\Support\Facades\Log::warning('Logo file not found', [
                                            'logo_path' => $logoPath,
                                            'exists' => $logoPath ? \Illuminate\Support\Facades\Storage::disk('public')->exists($logoPath) : false,
                                        ]);
                                    }
                                } catch (\Exception $e) {
                                    \Illuminate\Support\Facades\Log::error('Failed to load logo for email', [
                                        'logo_path' => $logoPath,
                                        'error' => $e->getMessage(),
                                    ]);
                                    $logoDataUri = '';
                                }
                            @endphp
                            @if($logoDataUri)
                                <img
                                    src="{{ $logoDataUri }}"
                                    alt="{{ $business->name ?? 'Business logo' }}"
                                    style="max-width: 160px; max-height: 64px; display: block; width: auto; height: auto;"
                                >
                            @else
                                <h1 style="color: #ffffff; font-size: 32px; font-weight: 600; margin: 0; letter-spacing: -0.5px;">
                                    {{ $business->name ?? 'SwiftPay' }}
                                </h1>
                            @endif
                        @else
                            <h1 style="color: #ffffff; font-size: 32px; font-weight: 600; margin: 0; letter-spacing: -0.5px;">
                                {{ $business->name ?? 'SwiftPay' }}
                            </h1>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </td>
</tr>
