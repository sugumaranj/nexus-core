<?php

declare(strict_types=1);

namespace App\Services;

use setasign\Fpdi\Fpdi;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;

final class CertificateGeneratorService
{

    public function generateOne(
        array $result,
        array $fieldConfig,
        string $templatePath,
        array $template,
        string $verificationUrl = ''
    ): string {
        $orientation = substr($template['page_orientation'] ?? 'Portrait', 0, 1);
        $pdf = new Fpdi($orientation, 'pt');
        
        // Disable margins and auto page breaks to ensure exact 1:1 coordinate mapping
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);

        $pageWidth  = (float)($template['page_width_pt'] ?? 595.28);
        $pageHeight = (float)($template['page_height_pt'] ?? 841.89);

        $pdf->AddPage($orientation, [$pageWidth, $pageHeight]);
        
        $pdf->setSourceFile($templatePath);
        $tplIdx = $pdf->importPage(1);
        // Explicitly place the template at (0,0) and scale it to the exact page dimensions
        $pdf->useTemplate($tplIdx, 0, 0, $pageWidth, $pageHeight);

        foreach ($fieldConfig as $field) {
            $fieldKey  = $field['field_key'] ?? '';
            $type      = $field['type'] ?? 'text';
            $xPt       = (float)($field['x_pt'] ?? 0);
            $yPt       = (float)($field['y_pt'] ?? 0);
            $widthPt   = (float)($field['width_pt'] ?? 200);
            $heightPt  = (float)($field['height_pt'] ?? 10);
            $fontFamily  = $field['font_family'] ?? 'Helvetica';
            $fontSize    = (float)($field['font_size'] ?? 12);
            $fontStyle   = $field['font_style'] ?? '';
            $textColor   = $field['text_color'] ?? '#000000';
            $alignment   = $field['alignment'] ?? 'L';
            $autoShrink  = !empty($field['auto_shrink']);
            $minFontSize = (float)($field['min_font_size'] ?? 6);

            if ($type === 'rank_indicator') {
                $rankPos   = (int)($result['rank_position'] ?? 0);
                $fieldRank = (int)($field['rank'] ?? 0);
                $matched   = ($fieldRank > 0 && $rankPos === $fieldRank);

                if ($matched) {
                    // Draw tick mark for the winning rank, using the field's own text color
                    $this->drawCheckmark($pdf, $xPt, $yPt, $widthPt, $heightPt, $textColor);
                }

            } elseif ($type === 'gender_indicator') {
                // Handles gender_check_male and gender_check_female fields
                $gender   = strtolower(trim($result['gender'] ?? ''));
                $isMale   = in_array($gender, ['m', 'male'], true);
                $isFemale = in_array($gender, ['f', 'female'], true);

                $shouldTick = false;
                if ($fieldKey === 'gender_check_male'   && $isMale)   $shouldTick = true;
                if ($fieldKey === 'gender_check_female' && $isFemale) $shouldTick = true;

                if ($shouldTick) {
                    $this->drawCheckmark($pdf, $xPt, $yPt, $widthPt, $heightPt, $textColor);
                }

            } elseif ($type === 'qr_code') {
                // Render the public verification QR code.
                // Skip silently if no URL is provided (e.g. preview without token).
                if ($verificationUrl !== '') {
                    $this->drawQrCode($pdf, $xPt, $yPt, $widthPt, $verificationUrl);
                }

            } else {
                $value = $this->resolveFieldValue($fieldKey, $result);
                if ($value !== '') {
                    if ($autoShrink) {
                        $fontSize = $this->getAutoShrunkFontSize(
                            $pdf, $value, $widthPt, $fontSize, $minFontSize, $fontFamily, $fontStyle
                        );
                    }
                    $pdf->SetFont($fontFamily, $fontStyle, $fontSize);
                    $this->setTextColor($pdf, $textColor);
                    $pdf->SetXY($xPt, $yPt);
                    $pdf->Cell($widthPt, $heightPt, $value, 0, 0, $alignment);
                }
            }
        }

        return $pdf->Output('S');
    }

    // -------------------------------------------------------------------------
    // QR Code helper
    // -------------------------------------------------------------------------

    /**
     * Generate a QR code PNG in memory, write to a temp file, embed into the
     * PDF using FPDI's Image() method, then delete the temp file.
     *
     * The QR field is always square (width_pt == height_pt enforced server-side).
     * We use the field width as the embedded image width and height.
     *
     * Error correction: QRCode::ECC_M provides a good balance between data density
     * and resilience. The verification URL is ~80 chars (hex token), which fits
     * well within the QR capacity at this level.
     *
     * @param Fpdi   $pdf             Active PDF instance
     * @param float  $xPt             X position in PDF points
     * @param float  $yPt             Y position in PDF points
     * @param float  $sizePt          Width (== height) in PDF points
     * @param string $verificationUrl The full public verification URL
     */
    private function drawQrCode(Fpdi $pdf, float $xPt, float $yPt, float $sizePt, string $verificationUrl): void
    {
        $options = new QROptions([
            // v6 API: set output class by FQCN
            'outputInterface' => QRGdImagePNG::class,
            // ECC level M: 15% correction — good balance for a ~80-char URL
            'eccLevel'        => EccLevel::M,
            'scale'           => 8,      // 8px per module — produces clear image at this field size
            'outputBase64'    => false,  // we write raw PNG to a file path
            'addQuietzone'    => true,
            'quietzoneSize'   => 4,      // 4-module quiet zone as per QR spec
        ]);

        $qrCode  = new QRCode($options);
        $tmpFile = tempnam(sys_get_temp_dir(), 'nexus_qr_') . '.png';

        try {
            // v6: render($data, $file) writes PNG bytes to $file directly
            $qrCode->render($verificationUrl, $tmpFile);

            if (!file_exists($tmpFile) || !is_readable($tmpFile)) {
                throw new \RuntimeException('QR temp file was not created.');
            }

            // Embed the PNG image at the exact field position and size (pt)
            $pdf->Image(
                $tmpFile,
                $xPt,
                $yPt,
                $sizePt,  // width in pt
                $sizePt,  // height in pt (always equal — square)
                'PNG'
            );
        } finally {
            // Exception-safe cleanup — always remove temp file
            if (file_exists($tmpFile)) {
                @unlink($tmpFile);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Drawing helpers
    // -------------------------------------------------------------------------

    /**
     * Draw a small, subtle typographic tick mark (✓) centred in the bounding box.
     *
     * Uses ZapfDingbats chr(51) — a lighter checkmark — rendered at 55% of the
     * smaller field dimension, so it never overwhelms the certificate background.
     * The color matches whatever text_color the field has (default dark grey #555).
     */
    private function drawCheckmark(Fpdi $pdf, float $x, float $y, float $w, float $h, string $hexColor = '#555555'): void
    {
        // chr(51) = ✓  light checkmark  (chr(52) = ✔ heavy bold — too dark)
        $fontSize = min($h, $w) * 0.55;
        $fontSize = max(6.0, min($fontSize, 18.0)); // clamp: never smaller than 6pt or larger than 18pt

        $pdf->SetFont('ZapfDingbats', '', $fontSize);
        $this->setTextColor($pdf, $hexColor);

        $pdf->SetXY($x, $y);
        $pdf->Cell($w, $h, chr(51), 0, 0, 'C');
    }

    // -------------------------------------------------------------------------
    // Field value resolution
    // -------------------------------------------------------------------------

    private function resolveFieldValue(string $fieldKey, array $result): string
    {
        switch ($fieldKey) {
            case 'participant_name':
                return (string) ($result['student_name'] ?? $result['recipient_name'] ?? '');
            case 'register_number':
                return (string) ($result['register_number'] ?? '');
            case 'department':
                // department_name now holds the short_name after query updates
                return (string) ($result['department_name'] ?? '');
            case 'event_name':
                return (string) ($result['event_name'] ?? '');
            case 'symposium_name':
                return (string) ($result['symposium_title'] ?? '');
            case 'held_on':
                return !empty($result['event_date'])
                    ? date('d F Y', strtotime((string)$result['event_date']))
                    : '';
            case 'academic_year':
                // Already converted to Roman numeral upstream in controller/service
                return (string) ($result['academic_year'] ?? '');
            case 'rank_label':
                return $this->rankLabel((int)($result['rank_position'] ?? 0));
            // Legacy fields — return empty so old templates do not break
            case 'venue':
            default:
                return '';
        }
    }

    private function rankLabel(int $rank): string
    {
        return match ($rank) {
            1 => '1st Place',
            2 => '2nd Place',
            3 => '3rd Place',
            default => 'Participation',
        };
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    private function setTextColor(Fpdi $pdf, string $hexColor): void
    {
        if (preg_match('/^#?([a-f0-9]{2})([a-f0-9]{2})([a-f0-9]{2})$/i', $hexColor, $m)) {
            $pdf->SetTextColor(hexdec($m[1]), hexdec($m[2]), hexdec($m[3]));
        } else {
            $pdf->SetTextColor(0, 0, 0);
        }
    }

    public function getAutoShrunkFontSize(
        Fpdi $pdf,
        string $text,
        float $maxWidth,
        float $startSize,
        float $minSize,
        string $family,
        string $style
    ): float {
        $current = $startSize;
        while ($current > $minSize) {
            $pdf->SetFont($family, $style, $current);
            if ($pdf->GetStringWidth($text) <= $maxWidth) {
                return $current;
            }
            $current -= 0.5;
        }
        return max($minSize, $current);
    }
}
