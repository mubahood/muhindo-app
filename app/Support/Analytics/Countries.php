<?php

namespace App\Support\Analytics;

/**
 * ISO country codes to names, and to a flag.
 *
 * Only the countries this audience actually comes from are named: East Africa
 * in full, then the places the diaspora, the clients and the YouTube traffic
 * are. Anything else shows its code, which is honest and takes no maintenance.
 * A complete 249-entry table would be mostly dead weight in a file nobody
 * reads.
 */
final class Countries
{
    private const NAMES = [
        'UG' => 'Uganda', 'KE' => 'Kenya', 'TZ' => 'Tanzania', 'RW' => 'Rwanda',
        'BI' => 'Burundi', 'SS' => 'South Sudan', 'CD' => 'DR Congo', 'ET' => 'Ethiopia',
        'SO' => 'Somalia', 'SD' => 'Sudan', 'ZM' => 'Zambia', 'MW' => 'Malawi',
        'ZW' => 'Zimbabwe', 'MZ' => 'Mozambique', 'BW' => 'Botswana', 'ZA' => 'South Africa',
        'NG' => 'Nigeria', 'GH' => 'Ghana', 'CM' => 'Cameroon', 'CI' => 'Ivory Coast',
        'SN' => 'Senegal', 'EG' => 'Egypt', 'MA' => 'Morocco', 'DZ' => 'Algeria',
        'TN' => 'Tunisia', 'LY' => 'Libya', 'AO' => 'Angola', 'NA' => 'Namibia',

        'GB' => 'United Kingdom', 'US' => 'United States', 'CA' => 'Canada',
        'IE' => 'Ireland', 'DE' => 'Germany', 'FR' => 'France', 'NL' => 'Netherlands',
        'BE' => 'Belgium', 'SE' => 'Sweden', 'NO' => 'Norway', 'DK' => 'Denmark',
        'FI' => 'Finland', 'IT' => 'Italy', 'ES' => 'Spain', 'PT' => 'Portugal',
        'PL' => 'Poland', 'CH' => 'Switzerland', 'AT' => 'Austria', 'RU' => 'Russia',
        'UA' => 'Ukraine', 'TR' => 'Turkey', 'GR' => 'Greece', 'RO' => 'Romania',

        'IN' => 'India', 'PK' => 'Pakistan', 'BD' => 'Bangladesh', 'CN' => 'China',
        'JP' => 'Japan', 'KR' => 'South Korea', 'PH' => 'Philippines', 'ID' => 'Indonesia',
        'MY' => 'Malaysia', 'SG' => 'Singapore', 'TH' => 'Thailand', 'VN' => 'Vietnam',
        'AE' => 'United Arab Emirates', 'SA' => 'Saudi Arabia', 'QA' => 'Qatar',
        'KW' => 'Kuwait', 'OM' => 'Oman', 'IL' => 'Israel', 'JO' => 'Jordan',

        'AU' => 'Australia', 'NZ' => 'New Zealand',
        'BR' => 'Brazil', 'AR' => 'Argentina', 'MX' => 'Mexico', 'CL' => 'Chile',
        'CO' => 'Colombia', 'PE' => 'Peru',
    ];

    public static function name(?string $code): string
    {
        $code = strtoupper((string) $code);

        return self::NAMES[$code] ?? ($code !== '' ? $code : 'Unknown');
    }

    /**
     * The flag, built from the code rather than stored.
     *
     * Regional indicator symbols are the two letters offset into a private
     * block of Unicode, so every country in existence works with no table and
     * no image requests.
     */
    public static function flag(?string $code): string
    {
        $code = strtoupper((string) $code);

        if (! preg_match('/^[A-Z]{2}$/', $code)) {
            return '';
        }

        return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8')
            .mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
    }
}
