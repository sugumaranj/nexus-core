<?php

declare(strict_types=1);

namespace App\Services\Prelims;

/**
 * Dual-Layer Desktop-Only Device Enforcement.
 *
 * Strictly blocks smartphones and tablets EVEN WHEN "Desktop Site" mode is enabled
 * in mobile Chrome/Safari/Edge/Firefox, while permitting standard desktops and
 * touchscreen Windows/Mac laptops (which expose `any-pointer: fine`, `hover: hover`,
 * and desktop GPUs).
 */
final class DeviceGuardService
{
    private const MOBILE_UA_REGEX = '/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini|Mobile|Tablet|Silk|Kindle|CriOS|FxiOS/i';
    private const MOBILE_GPU_REGEX = '/Adreno|Mali|PowerVR|Apple\s+GPU|Immortalis|Xclipse|Tegra|SGX|Vivante/i';
    private const MOBILE_PLATFORM_REGEX = '/^(?:Android|iOS|iPadOS|iPhone|iPad)$/i';

    /**
     * Evaluate server HTTP headers + client hardware attestation telemetry.
     *
     * @param array<string, mixed> $serverHeaders  Typically $_SERVER
     * @param array<string, mixed> $clientSignals  Submitted by device-guard.js
     * @return array{allowed: bool, risk_score: int, reasons: string[], fingerprint_hash: string}
     */
    public function evaluate(array $serverHeaders, array $clientSignals = []): array
    {
        $reasons   = [];
        $riskScore = 0;

        $ua          = (string) ($serverHeaders['HTTP_USER_AGENT'] ?? '');
        $secChMobile = trim((string) ($serverHeaders['HTTP_SEC_CH_UA_MOBILE'] ?? ''));
        $secChPlat   = trim((string) ($serverHeaders['HTTP_SEC_CH_UA_PLATFORM'] ?? ''), "\"' ");

        // 1. Server-side hard signals
        if ($secChMobile === '?1') {
            $riskScore += 100;
            $reasons[] = 'Sec-CH-UA-Mobile client hint indicates mobile hardware.';
        }

        if ($secChPlat !== '' && preg_match(self::MOBILE_PLATFORM_REGEX, $secChPlat)) {
            $riskScore += 100;
            $reasons[] = "Mobile OS platform detected via Client Hints ({$secChPlat}).";
        }

        if ($ua !== '' && preg_match(self::MOBILE_UA_REGEX, $ua)) {
            $riskScore += 100;
            $reasons[] = 'Mobile User-Agent detected.';
        }

        // 2. Client hardware feature attestation (catches "Request Desktop Site" spoofing!)
        if (!empty($clientSignals)) {
            $uaDataMobile   = !empty($clientSignals['ua_data_mobile']);
            $uaDataPlatform = trim((string) ($clientSignals['ua_data_platform'] ?? ''));
            $maxTouch       = (int) ($clientSignals['max_touch_points'] ?? 0);
            $pointerCoarse  = !empty($clientSignals['pointer_coarse']);
            $hoverNone      = !empty($clientSignals['hover_none']);
            $anyPointerFine = array_key_exists('any_pointer_fine', $clientSignals)
                ? (bool) $clientSignals['any_pointer_fine']
                : true;
            $anyHoverHover  = array_key_exists('any_hover_hover', $clientSignals)
                ? (bool) $clientSignals['any_hover_hover']
                : true;
            $screenW        = (int) ($clientSignals['screen_width'] ?? 1920);
            $screenH        = (int) ($clientSignals['screen_height'] ?? 1080);
            $dpr            = (float) ($clientSignals['dpr'] ?? 1.0);
            $webglRenderer  = (string) ($clientSignals['webgl_renderer'] ?? '');
            $hasOrientation = !empty($clientSignals['mobile_orientation_api']);
            $platformStr    = (string) ($clientSignals['platform'] ?? '');

            if ($uaDataMobile) {
                $riskScore += 100;
                $reasons[] = 'Browser userAgentData reports mobile device.';
            }

            if ($uaDataPlatform !== '' && preg_match(self::MOBILE_PLATFORM_REGEX, $uaDataPlatform)) {
                $riskScore += 100;
                $reasons[] = "Mobile platform reported by userAgentData ({$uaDataPlatform}).";
            }

            // Mobile GPU signature (even when User-Agent is faked to "X11; Linux x86_64"!)
            if ($webglRenderer !== '' && preg_match(self::MOBILE_GPU_REGEX, $webglRenderer)) {
                $riskScore += 90;
                $reasons[] = 'Mobile-class GPU renderer detected via WebGL hardware check.';
            }

            // Primary pointer is coarse AND cannot hover AND lacks any fine pointer (mouse/trackpad)
            // Touch laptops report `any_pointer_fine = true` and `any_hover_hover = true`!
            if ($pointerCoarse && $hoverNone && !$anyPointerFine) {
                $riskScore += 90;
                $reasons[] = 'Touch-only input hardware without precision mouse/trackpad detected.';
            } elseif ($maxTouch > 0 && !$anyPointerFine && !$anyHoverHover) {
                $riskScore += 85;
                $reasons[] = 'Multi-touch mobile display without fine desktop pointer detected.';
            } elseif ($pointerCoarse && $hoverNone) {
                $riskScore += 60;
                $reasons[] = 'Coarse pointer with no hover capability detected.';
            }

            // Physical screen short side check (CSS pixels; phones in desktop site report <= 540 or high DPR + small screen)
            $minScreen = min($screenW, $screenH);
            $maxScreen = max($screenW, $screenH);
            if ($minScreen > 0 && $minScreen <= 520 && $maxScreen <= 1024) {
                $riskScore += 70;
                $reasons[] = "Compact mobile screen geometry ({$screenW}x{$screenH}) detected.";
            } elseif ($maxTouch > 0 && $dpr >= 2.25 && $minScreen <= 600) {
                $riskScore += 45;
                $reasons[] = 'High-DPR compact touch screen characteristic of smartphone.';
            }

            // Mobile Linux ARM claiming desktop UA (classic Android Chrome "Desktop site" fingerprint!)
            if (preg_match('/Linux armv|Linux aarch64/i', $platformStr) && $maxTouch > 0 && !$anyPointerFine) {
                $riskScore += 80;
                $reasons[] = 'ARM mobile Linux platform spoofed as desktop browser.';
            }

            // iPadOS desktop-mode spoofing: MacIntel platform + multi-touch > 1 + no fine pointer
            if (stripos($platformStr, 'MacIntel') !== false && $maxTouch > 1 && !$anyPointerFine) {
                $riskScore += 85;
                $reasons[] = 'iPadOS/iOS Desktop-Site spoofing detected (MacIntel + touch-only).';
            }

            if ($hasOrientation && $maxTouch > 0 && !$anyPointerFine) {
                $riskScore += 25;
            }
        }

        $fingerprintSource = implode('|', [
            $ua,
            (string) ($clientSignals['platform'] ?? ''),
            (string) ($clientSignals['screen_width'] ?? ''),
            (string) ($clientSignals['screen_height'] ?? ''),
            (string) ($clientSignals['webgl_renderer'] ?? ''),
            (string) ($clientSignals['timezone'] ?? ''),
        ]);

        return [
            'allowed'          => $riskScore < 60,
            'risk_score'       => $riskScore,
            'reasons'          => $reasons,
            'fingerprint_hash' => hash('sha256', $fingerprintSource),
        ];
    }

    /**
     * Quick server-only header pre-check (used before rendering lobby).
     */
    public function isObviousMobileRequest(array $serverHeaders): bool
    {
        $res = $this->evaluate($serverHeaders, []);
        return !$res['allowed'];
    }
}
