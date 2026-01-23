<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeolocationService
{
    /**
     * Get cache TTL from config or use default.
     */
    private function getCacheTtl(): int
    {
        return config('services.geolocation.cache_ttl', 3600);
    }

    /**
     * Get timeout from config or use default.
     */
    private function getTimeout(): int
    {
        return config('services.geolocation.timeout', 2);
    }

    /**
     * Get location from IP address using multiple fallback methods.
     */
    public function getLocationFromIp(string $ip, ?Request $request = null): ?array
    {
        // Skip private/local IPs
        if ($this->isPrivateIp($ip)) {
            return [
                'city' => 'Local',
                'country' => 'Network',
                'region' => 'Private',
                'source' => 'private_ip',
            ];
        }

        // Check cache first
        $cacheKey = "geolocation:ip:{$ip}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        // Try methods in order until one succeeds - be persistent!
        $location = $this->tryIpApiCom($ip)
            ?? $this->tryIpApiCo($ip)
            ?? $this->tryIpApiComAlternative($ip)
            ?? $this->tryIpWhois($ip)
            ?? $this->tryIpApiNet($ip)
            ?? $this->tryFreeGeoIp($ip)
            ?? $this->tryIpApiOrg($ip)
            ?? $this->tryIpApiComFull($ip) // Try full endpoint for more data
            ?? $this->tryIpStack($ip) // Another free service
            ?? $this->tryIpify($ip) // Another free service
            ?? $this->tryHttpHeaders($request)
            ?? $this->tryReverseDns($ip)
            ?? $this->tryTimezoneInference($ip)
            ?? $this->tryAsnLookup($ip) // ASN-based country detection
            ?? $this->tryIspInference($ip) // ISP-based location hints
            ?? $this->tryUserAgentLocation($request) // Extract from user agent
            ?? $this->tryBrowserLanguage($request) // Enhanced language detection
            ?? $this->tryIpRangeLookup($ip);

        // Cache successful results
        if ($location !== null) {
            Cache::put($cacheKey, $location, $this->getCacheTtl());
        }

        return $location;
    }

    /**
     * Primary method: ip-api.com
     */
    private function tryIpApiCom(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'status,message,country,regionName,city',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'source' => 'ip-api.com',
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Geolocation primary method (ip-api.com) failed for IP {$ip}: ".$e->getMessage());
        }

        return null;
    }

    /**
     * Fallback 1: ipapi.co
     */
    private function tryIpApiCo(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("https://ipapi.co/{$ip}/json/");

            if ($response->successful()) {
                $data = $response->json();
                if (! isset($data['error'])) {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country_name'] ?? '',
                        'region' => $data['region'] ?? '',
                        'source' => 'ipapi.co',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 2: ip-api.com alternative endpoint (full response)
     */
    private function tryIpApiComAlternative(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'source' => 'ip-api.com-alt',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 2b: ip-api.com full endpoint with all fields
     */
    private function tryIpApiComFull(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}?fields=status,message,country,countryCode,region,regionName,city,zip,lat,lon,timezone,isp,org,as,query");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    // Even if city is empty, try to get country at minimum
                    $country = $data['country'] ?? '';
                    if ($country) {
                        return [
                            'city' => $data['city'] ?? '',
                            'country' => $country,
                            'region' => $data['regionName'] ?? $data['region'] ?? '',
                            'source' => 'ip-api.com-full',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 7b: ipstack.com (free tier, no API key for basic)
     */
    private function tryIpStack(string $ip): ?array
    {
        try {
            // Try without API key first (limited but works)
            $response = Http::timeout($this->getTimeout())
                ->get("http://api.ipstack.com/{$ip}?access_key=demo");

            if ($response->successful()) {
                $data = $response->json();
                if (! isset($data['error'])) {
                    $country = $data['country_name'] ?? '';
                    if ($country) {
                        return [
                            'city' => $data['city'] ?? '',
                            'country' => $country,
                            'region' => $data['region_name'] ?? '',
                            'source' => 'ipstack',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 7c: ipify.org geolocation
     */
    private function tryIpify(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get('https://geo.ipify.org/api/v2/country', [
                    'apiKey' => 'at_demo', // Demo key
                    'ipAddress' => $ip,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['location'])) {
                    return [
                        'city' => $data['location']['city'] ?? '',
                        'country' => $data['location']['country'] ?? '',
                        'region' => $data['location']['region'] ?? '',
                        'source' => 'ipify',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Try alternative ipify endpoint
            try {
                $response = Http::timeout($this->getTimeout())
                    ->get('https://api.ipify.org?format=json');

                if ($response->successful()) {
                    // This just returns IP, but we can use it to verify
                    // For actual geolocation, we'd need the paid API
                }
            } catch (\Exception $e2) {
                // Silent fallback
            }
        }

        return null;
    }

    /**
     * Fallback 3: ipwhois.app (free, no API key)
     */
    private function tryIpWhois(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("https://ipwhois.app/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['success'] ?? false) === true) {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country'] ?? '',
                        'region' => $data['region'] ?? '',
                        'source' => 'ipwhois.app',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 4: ip-api.net (free, no API key)
     */
    private function tryIpApiNet(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("https://ip-api.net/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (! isset($data['error'])) {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country'] ?? '',
                        'region' => $data['regionName'] ?? '',
                        'source' => 'ip-api.net',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 5: freegeoip.app (free, no API key)
     */
    private function tryFreeGeoIp(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("https://freegeoip.app/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['country_name'])) {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country_name'] ?? '',
                        'region' => $data['region_name'] ?? '',
                        'source' => 'freegeoip.app',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 6: ip-api.org (free, no API key)
     */
    private function tryIpApiOrg(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.org/json/{$ip}");

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'success') {
                    return [
                        'city' => $data['city'] ?? '',
                        'country' => $data['country'] ?? '',
                        'region' => $data['region'] ?? '',
                        'source' => 'ip-api.org',
                    ];
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 7: HTTP Headers Analysis
     */
    private function tryHttpHeaders(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        // Try Cloudflare country header
        $cfCountry = $request->header('CF-IPCountry');
        if ($cfCountry && $cfCountry !== 'XX' && strlen($cfCountry) === 2) {
            return [
                'city' => '',
                'country' => $this->getCountryNameFromCode($cfCountry),
                'region' => '',
                'source' => 'cloudflare-header',
            ];
        }

        // Try Cloudflare city header
        $cfCity = $request->header('CF-IPCity');
        if ($cfCity && $cfCountry && $cfCountry !== 'XX') {
            return [
                'city' => $cfCity,
                'country' => $this->getCountryNameFromCode($cfCountry),
                'region' => $request->header('CF-Region') ?? '',
                'source' => 'cloudflare-headers',
            ];
        }

        // Try X-Forwarded-For country hints (if available)
        $xffCountry = $request->header('X-Country-Code');
        if ($xffCountry && strlen($xffCountry) === 2) {
            return [
                'city' => '',
                'country' => $this->getCountryNameFromCode($xffCountry),
                'region' => '',
                'source' => 'x-country-code',
            ];
        }

        // Try Accept-Language for country inference
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            // Extract country code from language (e.g., "en-US" -> "US")
            if (preg_match('/-([A-Z]{2})\b/i', $acceptLanguage, $matches)) {
                $countryCode = strtoupper($matches[1]);

                return [
                    'city' => '',
                    'country' => $this->getCountryNameFromCode($countryCode),
                    'region' => '',
                    'source' => 'accept-language',
                ];
            }
        }

        return null;
    }

    /**
     * Fallback 8: Reverse DNS Lookup
     */
    private function tryReverseDns(string $ip): ?array
    {
        try {
            $hostname = gethostbyaddr($ip);
            if ($hostname && $hostname !== $ip) {
                // Extract country hints from hostname (e.g., .za, .us, .uk)
                if (preg_match('/\.([a-z]{2})$/', strtolower($hostname), $matches)) {
                    $tld = strtoupper($matches[1]);
                    // Map common TLDs to countries
                    $tldToCountry = [
                        'ZA' => 'South Africa',
                        'US' => 'United States',
                        'UK' => 'United Kingdom',
                        'CA' => 'Canada',
                        'AU' => 'Australia',
                        'DE' => 'Germany',
                        'FR' => 'France',
                        'IT' => 'Italy',
                        'ES' => 'Spain',
                        'NL' => 'Netherlands',
                        'BE' => 'Belgium',
                        'CH' => 'Switzerland',
                        'AT' => 'Austria',
                        'SE' => 'Sweden',
                        'NO' => 'Norway',
                        'DK' => 'Denmark',
                        'FI' => 'Finland',
                        'PL' => 'Poland',
                        'IE' => 'Ireland',
                        'PT' => 'Portugal',
                        'GR' => 'Greece',
                        'CN' => 'China',
                        'JP' => 'Japan',
                        'KR' => 'South Korea',
                        'IN' => 'India',
                        'BR' => 'Brazil',
                        'MX' => 'Mexico',
                        'AR' => 'Argentina',
                        'NZ' => 'New Zealand',
                    ];

                    if (isset($tldToCountry[$tld])) {
                        return [
                            'city' => '',
                            'country' => $tldToCountry[$tld],
                            'region' => '',
                            'source' => 'reverse-dns',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 9: Timezone-based Country Inference
     */
    private function tryTimezoneInference(string $ip): ?array
    {
        try {
            // Get timezone from IP using a free service
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'timezone,country',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $timezone = $data['timezone'] ?? null;
                $country = $data['country'] ?? null;

                // If we got country directly, use it
                if ($country) {
                    return [
                        'city' => '',
                        'country' => $country,
                        'region' => '',
                        'source' => 'timezone-country',
                    ];
                }

                if ($timezone) {
                    // Map timezone to likely country
                    $timezoneToCountry = [
                        'Africa/Johannesburg' => 'South Africa',
                        'Africa/Cairo' => 'Egypt',
                        'Africa/Lagos' => 'Nigeria',
                        'Africa/Nairobi' => 'Kenya',
                        'America/New_York' => 'United States',
                        'America/Los_Angeles' => 'United States',
                        'America/Chicago' => 'United States',
                        'America/Denver' => 'United States',
                        'America/Toronto' => 'Canada',
                        'America/Sao_Paulo' => 'Brazil',
                        'America/Mexico_City' => 'Mexico',
                        'America/Buenos_Aires' => 'Argentina',
                        'America/Santiago' => 'Chile',
                        'Europe/London' => 'United Kingdom',
                        'Europe/Paris' => 'France',
                        'Europe/Berlin' => 'Germany',
                        'Europe/Rome' => 'Italy',
                        'Europe/Madrid' => 'Spain',
                        'Europe/Amsterdam' => 'Netherlands',
                        'Europe/Brussels' => 'Belgium',
                        'Europe/Zurich' => 'Switzerland',
                        'Europe/Vienna' => 'Austria',
                        'Europe/Stockholm' => 'Sweden',
                        'Europe/Oslo' => 'Norway',
                        'Europe/Copenhagen' => 'Denmark',
                        'Europe/Helsinki' => 'Finland',
                        'Europe/Warsaw' => 'Poland',
                        'Europe/Dublin' => 'Ireland',
                        'Europe/Lisbon' => 'Portugal',
                        'Europe/Athens' => 'Greece',
                        'Europe/Moscow' => 'Russia',
                        'Asia/Shanghai' => 'China',
                        'Asia/Tokyo' => 'Japan',
                        'Asia/Seoul' => 'South Korea',
                        'Asia/Kolkata' => 'India',
                        'Asia/Dubai' => 'United Arab Emirates',
                        'Asia/Singapore' => 'Singapore',
                        'Asia/Hong_Kong' => 'Hong Kong',
                        'Pacific/Auckland' => 'New Zealand',
                        'Australia/Sydney' => 'Australia',
                        'Australia/Melbourne' => 'Australia',
                    ];

                    if (isset($timezoneToCountry[$timezone])) {
                        return [
                            'city' => '',
                            'country' => $timezoneToCountry[$timezone],
                            'region' => '',
                            'source' => 'timezone-inference',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 10: ASN-based Country Detection
     */
    private function tryAsnLookup(string $ip): ?array
    {
        try {
            // Get ASN info which often includes country
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'as,country,countryCode',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $country = $data['country'] ?? '';
                $asn = $data['as'] ?? '';

                if ($country) {
                    return [
                        'city' => '',
                        'country' => $country,
                        'region' => '',
                        'source' => 'asn-lookup',
                    ];
                }

                // Try to infer from ASN number patterns (very basic)
                if ($asn && preg_match('/AS(\d+)/', $asn, $matches)) {
                    $asnNum = (int) $matches[1];
                    // Some ASN ranges are country-specific (very rough)
                    // This is a last resort
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 11: ISP-based Location Inference
     */
    private function tryIspInference(string $ip): ?array
    {
        try {
            $response = Http::timeout($this->getTimeout())
                ->get("http://ip-api.com/json/{$ip}", [
                    'fields' => 'isp,org,country',
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $country = $data['country'] ?? '';
                $isp = strtolower($data['isp'] ?? '');
                $org = strtolower($data['org'] ?? '');

                // If we got country, use it
                if ($country) {
                    return [
                        'city' => '',
                        'country' => $country,
                        'region' => '',
                        'source' => 'isp-country',
                    ];
                }

                // Try to infer from ISP/org names (very basic)
                $ispCountryHints = [
                    'vodacom' => 'South Africa',
                    'mtn' => 'South Africa',
                    'telkom' => 'South Africa',
                    'cell c' => 'South Africa',
                    'rain' => 'South Africa',
                    'verizon' => 'United States',
                    'att' => 'United States',
                    'comcast' => 'United States',
                    'bt' => 'United Kingdom',
                    'vodafone' => ['United Kingdom', 'Germany', 'Italy', 'Spain'],
                    'orange' => ['France', 'Spain'],
                    'telefonica' => 'Spain',
                    'deutsche telekom' => 'Germany',
                    'telecom italia' => 'Italy',
                ];

                foreach ($ispCountryHints as $hint => $countries) {
                    if (str_contains($isp, $hint) || str_contains($org, $hint)) {
                        $country = is_array($countries) ? $countries[0] : $countries;

                        return [
                            'city' => '',
                            'country' => $country,
                            'region' => '',
                            'source' => 'isp-inference',
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Silent fallback
        }

        return null;
    }

    /**
     * Fallback 12: User Agent Location Hints
     */
    private function tryUserAgentLocation(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        $userAgent = strtolower($request->userAgent() ?? '');

        // Some user agents contain location hints
        // This is very unreliable but worth trying as last resort
        $uaCountryHints = [
            'za' => 'South Africa',
            'us' => 'United States',
            'uk' => 'United Kingdom',
            'ca' => 'Canada',
            'au' => 'Australia',
        ];

        foreach ($uaCountryHints as $hint => $country) {
            if (str_contains($userAgent, $hint)) {
                return [
                    'city' => '',
                    'country' => $country,
                    'region' => '',
                    'source' => 'user-agent-hint',
                ];
            }
        }

        return null;
    }

    /**
     * Fallback 13: Enhanced Browser Language Detection
     */
    private function tryBrowserLanguage(?Request $request): ?array
    {
        if (! $request) {
            return null;
        }

        $acceptLanguage = $request->header('Accept-Language');
        if (! $acceptLanguage) {
            return null;
        }

        // Parse Accept-Language more thoroughly
        $languages = explode(',', $acceptLanguage);
        foreach ($languages as $lang) {
            $lang = trim(explode(';', $lang)[0]); // Remove quality values

            // Language to country mapping (common patterns)
            $langToCountry = [
                'en-za' => 'South Africa',
                'en-us' => 'United States',
                'en-gb' => 'United Kingdom',
                'en-ca' => 'Canada',
                'en-au' => 'Australia',
                'en-nz' => 'New Zealand',
                'af' => 'South Africa', // Afrikaans
                'zu' => 'South Africa', // Zulu
                'xh' => 'South Africa', // Xhosa
                'fr' => 'France',
                'de' => 'Germany',
                'it' => 'Italy',
                'es' => 'Spain',
                'pt' => 'Portugal',
                'pt-br' => 'Brazil',
                'nl' => 'Netherlands',
                'pl' => 'Poland',
                'ru' => 'Russia',
                'zh' => 'China',
                'ja' => 'Japan',
                'ko' => 'South Korea',
                'ar' => ['Saudi Arabia', 'Egypt', 'UAE'],
                'hi' => 'India',
            ];

            $langLower = strtolower($lang);
            if (isset($langToCountry[$langLower])) {
                $country = is_array($langToCountry[$langLower])
                    ? $langToCountry[$langLower][0]
                    : $langToCountry[$langLower];

                return [
                    'city' => '',
                    'country' => $country,
                    'region' => '',
                    'source' => 'browser-language',
                ];
            }

            // Try to extract country code from language tag
            if (preg_match('/-([a-z]{2})$/i', $lang, $matches)) {
                $countryCode = strtoupper($matches[1]);
                $countryName = $this->getCountryNameFromCode($countryCode);
                if ($countryName !== $countryCode) {
                    return [
                        'city' => '',
                        'country' => $countryName,
                        'region' => '',
                        'source' => 'language-tag',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Fallback 14: IP Range Lookup (basic)
     */
    private function tryIpRangeLookup(string $ip): ?array
    {
        // Basic IP-to-country mapping for common ranges
        // This is a simplified fallback - in production, consider MaxMind GeoLite2
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return null;
        }

        // Very basic country detection from IP ranges (IANA allocations)
        // This is a last resort and may not be accurate
        // Common IP ranges by region
        $ipNum = (int) sprintf('%u', $ipLong);

        // South Africa (common ranges)
        if (($ipNum >= 1051799552 && $ipNum <= 1051803647) || // 62.0.0.0 - 62.0.15.255
            ($ipNum >= 1051803648 && $ipNum <= 1051807743)) { // 62.0.16.0 - 62.0.31.255
            return [
                'city' => '',
                'country' => 'South Africa',
                'region' => '',
                'source' => 'ip-range',
            ];
        }

        // This is very basic - return null to show "Unknown" rather than inaccurate data
        return null;
    }

    /**
     * Check if IP is private/local.
     */
    private function isPrivateIp(string $ip): bool
    {
        // Check for localhost
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return true;
        }

        // Check for private IP ranges
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        return false;
    }

    /**
     * Convert country code to country name.
     */
    private function getCountryNameFromCode(string $code): string
    {
        if (strlen($code) !== 2) {
            return $code;
        }

        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'ZA' => 'South Africa',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'PL' => 'Poland',
            'IE' => 'Ireland',
            'PT' => 'Portugal',
            'GR' => 'Greece',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HR' => 'Croatia',
            'SK' => 'Slovakia',
            'SI' => 'Slovenia',
            'EE' => 'Estonia',
            'LV' => 'Latvia',
            'LT' => 'Lithuania',
            'LU' => 'Luxembourg',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'IS' => 'Iceland',
            'LI' => 'Liechtenstein',
            'MC' => 'Monaco',
            'SM' => 'San Marino',
            'VA' => 'Vatican City',
            'AD' => 'Andorra',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'EC' => 'Ecuador',
            'BO' => 'Bolivia',
            'PY' => 'Paraguay',
            'UY' => 'Uruguay',
            'GY' => 'Guyana',
            'SR' => 'Suriname',
            'GF' => 'French Guiana',
            'FK' => 'Falkland Islands',
            'GS' => 'South Georgia',
            'CN' => 'China',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'IN' => 'India',
            'ID' => 'Indonesia',
            'TH' => 'Thailand',
            'VN' => 'Vietnam',
            'PH' => 'Philippines',
            'MY' => 'Malaysia',
            'SG' => 'Singapore',
            'HK' => 'Hong Kong',
            'TW' => 'Taiwan',
            'MO' => 'Macau',
            'BD' => 'Bangladesh',
            'PK' => 'Pakistan',
            'LK' => 'Sri Lanka',
            'NP' => 'Nepal',
            'BT' => 'Bhutan',
            'MV' => 'Maldives',
            'MM' => 'Myanmar',
            'LA' => 'Laos',
            'KH' => 'Cambodia',
            'BN' => 'Brunei',
            'TL' => 'East Timor',
            'NZ' => 'New Zealand',
            'FJ' => 'Fiji',
            'PG' => 'Papua New Guinea',
            'SB' => 'Solomon Islands',
            'VU' => 'Vanuatu',
            'NC' => 'New Caledonia',
            'PF' => 'French Polynesia',
            'WS' => 'Samoa',
            'TO' => 'Tonga',
            'KI' => 'Kiribati',
            'TV' => 'Tuvalu',
            'NR' => 'Nauru',
            'PW' => 'Palau',
            'FM' => 'Micronesia',
            'MH' => 'Marshall Islands',
            'RU' => 'Russia',
            'UA' => 'Ukraine',
            'BY' => 'Belarus',
            'KZ' => 'Kazakhstan',
            'UZ' => 'Uzbekistan',
            'TM' => 'Turkmenistan',
            'TJ' => 'Tajikistan',
            'KG' => 'Kyrgyzstan',
            'GE' => 'Georgia',
            'AM' => 'Armenia',
            'AZ' => 'Azerbaijan',
            'TR' => 'Turkey',
            'IL' => 'Israel',
            'PS' => 'Palestine',
            'JO' => 'Jordan',
            'LB' => 'Lebanon',
            'SY' => 'Syria',
            'IQ' => 'Iraq',
            'IR' => 'Iran',
            'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'KW' => 'Kuwait',
            'QA' => 'Qatar',
            'BH' => 'Bahrain',
            'OM' => 'Oman',
            'YE' => 'Yemen',
            'EG' => 'Egypt',
            'LY' => 'Libya',
            'TN' => 'Tunisia',
            'DZ' => 'Algeria',
            'MA' => 'Morocco',
            'SD' => 'Sudan',
            'ET' => 'Ethiopia',
            'KE' => 'Kenya',
            'TZ' => 'Tanzania',
            'UG' => 'Uganda',
            'RW' => 'Rwanda',
            'BI' => 'Burundi',
            'DJ' => 'Djibouti',
            'ER' => 'Eritrea',
            'SO' => 'Somalia',
            'SS' => 'South Sudan',
            'CF' => 'Central African Republic',
            'TD' => 'Chad',
            'CM' => 'Cameroon',
            'CG' => 'Congo',
            'CD' => 'DR Congo',
            'GA' => 'Gabon',
            'GQ' => 'Equatorial Guinea',
            'ST' => 'São Tomé and Príncipe',
            'AO' => 'Angola',
            'ZM' => 'Zambia',
            'ZW' => 'Zimbabwe',
            'BW' => 'Botswana',
            'NA' => 'Namibia',
            'SZ' => 'Eswatini',
            'LS' => 'Lesotho',
            'MW' => 'Malawi',
            'MZ' => 'Mozambique',
            'MG' => 'Madagascar',
            'MU' => 'Mauritius',
            'SC' => 'Seychelles',
            'KM' => 'Comoros',
            'CV' => 'Cape Verde',
            'GW' => 'Guinea-Bissau',
            'GN' => 'Guinea',
            'SL' => 'Sierra Leone',
            'LR' => 'Liberia',
            'CI' => 'Ivory Coast',
            'GH' => 'Ghana',
            'TG' => 'Togo',
            'BJ' => 'Benin',
            'NG' => 'Nigeria',
            'NE' => 'Niger',
            'BF' => 'Burkina Faso',
            'ML' => 'Mali',
            'SN' => 'Senegal',
            'MR' => 'Mauritania',
            'GM' => 'Gambia',
        ];

        return $countries[strtoupper($code)] ?? $code;
    }
}
