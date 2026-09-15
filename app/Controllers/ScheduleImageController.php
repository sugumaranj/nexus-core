<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore
 * -------------------------------------------------------------------------
 * File        : ScheduleImageController.php
 * Location    : app/Controllers/
 * Description : Generates a formal PNG schedule image server-side using PHP GD
 *               with TTF fonts (Times New Roman).
 *
 * Called via: GET /schedule/image?date=YYYY-MM-DD
 *
 * Returns a print-quality PNG image of the event schedule table for
 * sharing on WhatsApp, matching the official paper notice format.
 * -------------------------------------------------------------------------
 */

namespace App\Controllers;

use App\Core\Session;
use App\Middleware\AuthMiddleware;
use App\Middleware\RoleMiddleware;
use App\Database\Database;
use PDO;

class ScheduleImageController extends BaseController
{
    // ── Font paths (Times New Roman — formal, serif) ───────────────────────
    private const FONT_REGULAR = 'C:/Windows/Fonts/times.ttf';
    private const FONT_BOLD    = 'C:/Windows/Fonts/timesbd.ttf';

    // ── Layout constants ───────────────────────────────────────────────────
    private const IMG_W   = 1100;
    private const PADDING = 50;
    private const ROW_H   = 52;
    private const THEAD_H = 56;

    // ── Font sizes ─────────────────────────────────────────────────────────
    private const FS_COLLEGE = 22;
    private const FS_ADDRESS = 16;
    private const FS_SYMPO   = 18;
    private const FS_LABEL   = 14;
    private const FS_THEAD   = 15;
    private const FS_CELL    = 14;

    /**
     * Generate and output a PNG image of tomorrow's schedule.
     * GET /schedule/image?date=YYYY-MM-DD
     */
    public function generate(): void
    {
        AuthMiddleware::handle();
        RoleMiddleware::requireRole('Staff Coordinator');

        // ── Validate requested date ────────────────────────────────────────
        $requestedDate = $_GET['date'] ?? '';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestedDate)) {
            $requestedDate = date('Y-m-d', strtotime('+1 day'));
        }

        // Only reject strictly past dates (allow today and future)
        if ($requestedDate < date('Y-m-d')) {
            $requestedDate = date('Y-m-d');
        }

        // ── Fetch events strictly for that date ───────────────────────────
        $events = $this->fetchEventsForDate($requestedDate);

        if (empty($events)) {
            $this->outputErrorImage('No events scheduled for ' . date('d M Y', strtotime($requestedDate)) . '.');
            return;
        }

        // ── College details from config ────────────────────────────────────
        $collegeName    = college_name();
        $collegeAddress = college_address();

        // ── Organizing department ──────────────────────────────────────────
        $organizedBy = 'Department of Computer Science & Computer Applications';

        // ── Symposium title + academic year ────────────────────────────────
        $baseTitle   = $events[0]['symposium_title'] ?? college_event();
        $symYear     = !empty($events[0]['academic_year'])
            ? (string) $events[0]['academic_year']
            : date('Y');

        // "NEXUS – 2026"
        $symposiumLine = strtoupper(trim($baseTitle)) . ' – ' . $symYear;

        // ── Render ────────────────────────────────────────────────────────
        $this->renderImage(
            $collegeName,
            $collegeAddress,
            $organizedBy,
            $symposiumLine,
            $requestedDate,
            $events
        );
    }

    // =========================================================================
    // Private: DB helpers
    // =========================================================================

    /**
     * Fetch all events for a specific date (strict — no fallback).
     * Includes symposium_id and academic_year for header assembly.
     */
    private function fetchEventsForDate(string $date): array
    {
        $db   = Database::getConnection();
        $stmt = $db->prepare("
            SELECT se.symposium_event_id,
                   se.symposium_id,
                   se.event_name,
                   se.event_date,
                   se.start_time,
                   se.end_time,
                   s.title         AS symposium_title,
                   s.academic_year
            FROM symposium_events se
            JOIN symposiums s ON s.symposium_id = se.symposium_id
            WHERE se.status != 'Cancelled'
              AND se.event_date = :date
            ORDER BY se.start_time ASC
        ");
        $stmt->execute([':date' => $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }



    // =========================================================================
    // Private: Image renderer
    // =========================================================================

    /**
     * Render the schedule as a formal PNG notice and output it.
     */
    private function renderImage(
        string $collegeName,
        string $collegeAddress,
        string $organizedBy,
        string $symposiumLine,
        string $date,
        array  $events
    ): void {
        $fontR = self::FONT_REGULAR;
        $fontB = self::FONT_BOLD;

        $imgW   = self::IMG_W;
        $pad    = self::PADDING;
        $tableW = $imgW - $pad * 2;

        // ── Pre-calculate total image height ───────────────────────────────
        $headerLines = [
            ['text' => $collegeName,    'font' => $fontB, 'size' => self::FS_COLLEGE, 'gap' => 15],
            ['text' => $collegeAddress, 'font' => $fontR, 'size' => self::FS_ADDRESS, 'gap' => 10],
            ['text' => $organizedBy,    'font' => $fontR, 'size' => self::FS_ADDRESS, 'gap' => 12],
            ['text' => $symposiumLine,  'font' => $fontB, 'size' => self::FS_SYMPO,   'gap' => 20],
        ];

        $headerH = $pad;
        foreach ($headerLines as $line) {
            $bb       = imagettfbbox($line['size'], 0, $line['font'], $line['text']);
            $headerH += abs($bb[7] - $bb[1]) + $line['gap'];
        }
        // divider (double rule) + "Schedule for" label
        $lb       = imagettfbbox(self::FS_LABEL, 0, $fontR, 'Schedule for : 00/00/0000 (Wednesday)');
        $headerH += 26 + abs($lb[7] - $lb[1]) + 24;

        $imgH = $headerH
              + self::THEAD_H
              + count($events) * self::ROW_H
              + $pad;

        // ── Create canvas ──────────────────────────────────────────────────
        $im = imagecreatetruecolor($imgW, $imgH);

        $cWhite   = imagecolorallocate($im, 255, 255, 255);
        $cBlack   = imagecolorallocate($im, 0,   0,   0);
        $cTheadBg = imagecolorallocate($im, 220, 220, 220);
        $cEvenRow = imagecolorallocate($im, 245, 245, 245);

        imagefill($im, 0, 0, $cWhite);

        // Outer double border
        imagerectangle($im, 0, 0, $imgW - 1, $imgH - 1, $cBlack);
        imagerectangle($im, 3, 3, $imgW - 4, $imgH - 4, $cBlack);

        // ── Header lines ───────────────────────────────────────────────────
        $y = $pad;

        $y = $this->ttfCentered($im, $collegeName,    $fontB, self::FS_COLLEGE, $imgW, $y, $cBlack);
        $y += $headerLines[0]['gap'];

        $y = $this->ttfCentered($im, $collegeAddress, $fontR, self::FS_ADDRESS, $imgW, $y, $cBlack);
        $y += $headerLines[1]['gap'];

        $y = $this->ttfCentered($im, $organizedBy,    $fontR, self::FS_ADDRESS, $imgW, $y, $cBlack);
        $y += $headerLines[2]['gap'];

        $y = $this->ttfCentered($im, $symposiumLine,  $fontB, self::FS_SYMPO,   $imgW, $y, $cBlack);
        $y += $headerLines[3]['gap'];

        // Double-rule divider
        imageline($im, $pad, $y, $imgW - $pad, $y, $cBlack);
        imageline($im, $pad, $y + 3, $imgW - $pad, $y + 3, $cBlack);
        $y += 24;

        // "Schedule for" date label
        $dateLabel = 'Schedule for : ' . date('d/m/Y (l)', strtotime($date));
        $y = $this->ttfCentered($im, $dateLabel, $fontR, self::FS_LABEL, $imgW, $y, $cBlack);
        $y += 24;

        // ── Column definitions ─────────────────────────────────────────────
        $cols = [
            'S. No'      => (int)($tableW * 0.08),
            'Date'       => (int)($tableW * 0.14),
            'Session'    => (int)($tableW * 0.11),
            'Event Name' => (int)($tableW * 0.43),
            'Time'       => (int)($tableW * 0.24),
        ];
        $endX = $pad + $tableW;

        // ── Table header ───────────────────────────────────────────────────
        imagefilledrectangle($im, $pad, $y, $endX, $y + self::THEAD_H, $cTheadBg);
        $xOff = $pad;
        foreach ($cols as $label => $colW) {
            imagerectangle($im, $xOff, $y, $xOff + $colW, $y + self::THEAD_H, $cBlack);
            $this->ttfCellCentered($im, $label, $fontB, self::FS_THEAD, $xOff, $y, $colW, self::THEAD_H, $cBlack);
            $xOff += $colW;
        }
        $y += self::THEAD_H;

        // ── Data rows ──────────────────────────────────────────────────────
        foreach ($events as $idx => $evt) {
            $rowBg = ($idx % 2 === 0) ? $cWhite : $cEvenRow;
            imagefilledrectangle($im, $pad, $y, $endX, $y + self::ROW_H, $rowBg);

            $startTs = !empty($evt['start_time']) ? strtotime($evt['start_time']) : time();
            $endTs   = !empty($evt['end_time']) ? strtotime($evt['end_time']) : time();
            $hour    = (int) date('H', $startTs);
            $session = $hour < 12 ? 'FN' : 'AN';

            $timeStr = strtolower(date('g.i', $startTs))
                     . ' ' . strtolower(date('a', $startTs))
                     . ' – '
                     . strtolower(date('g.i', $endTs))
                     . ' ' . strtolower(date('a', $endTs));

            $dispDate = !empty($evt['event_date']) ? date('d/m/y', strtotime($evt['event_date'])) : 'TBD';

            $rowData = [
                (string)($idx + 1),
                $dispDate,
                $session,
                $evt['event_name'],
                $timeStr,
            ];

            $xOff = $pad;
            foreach (array_values($cols) as $ci => $colW) {
                imagerectangle($im, $xOff, $y, $xOff + $colW, $y + self::ROW_H, $cBlack);
                $this->ttfCellCentered(
                    $im, $rowData[$ci], $fontR, self::FS_CELL,
                    $xOff, $y, $colW, self::ROW_H, $cBlack
                );
                $xOff += $colW;
            }
            $y += self::ROW_H;
        }

        // ── Output ────────────────────────────────────────────────────────
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="schedule-' . $date . '.png"');
        header('Cache-Control: no-store, no-cache');
        header('Pragma: no-cache');
        imagepng($im, null, 6);
        imagedestroy($im);
    }

    // =========================================================================
    // Private: TTF helpers
    // =========================================================================

    /**
     * Draw TTF text centered horizontally on the canvas.
     * Returns the bottom Y of the text bounding box.
     */
    private function ttfCentered(
        \GdImage $im,
        string   $text,
        string   $fontFile,
        float    $size,
        int      $imgW,
        int      $y,
        int      $color
    ): int {
        $bbox  = imagettfbbox($size, 0, $fontFile, $text);
        $textW = abs($bbox[4] - $bbox[0]);
        $textH = abs($bbox[7] - $bbox[1]);
        $x     = (int)(($imgW - $textW) / 2);
        imagettftext($im, $size, 0, $x, $y + $textH, $color, $fontFile, $text);
        return $y + $textH;
    }

    /**
     * Draw TTF text centered inside a table cell (both axes), with word-wrap.
     */
    private function ttfCellCentered(
        \GdImage $im,
        string   $text,
        string   $fontFile,
        float    $size,
        int      $cellX,
        int      $cellY,
        int      $cellW,
        int      $cellH,
        int      $color
    ): void {
        $innerW = $cellW - 10;
        $lines  = $this->wrapText($text, $fontFile, $size, $innerW);

        $refBbox = imagettfbbox($size, 0, $fontFile, 'Mg');
        $lineH   = abs($refBbox[7] - $refBbox[1]) + 4;
        $totalH  = count($lines) * $lineH;
        $startY  = $cellY + (int)(($cellH - $totalH) / 2);

        foreach ($lines as $i => $line) {
            $lBbox = imagettfbbox($size, 0, $fontFile, $line);
            $lineW = abs($lBbox[4] - $lBbox[0]);
            $lineHActual = abs($lBbox[7] - $lBbox[1]);
            $textX = $cellX + (int)(($cellW - $lineW) / 2);
            $textY = $startY + ($i * $lineH) + $lineHActual;
            imagettftext($im, $size, 0, $textX, $textY, $color, $fontFile, $line);
        }
    }

    /**
     * Word-wrap $text to fit within $maxWidth pixels using the given TTF font.
     */
    private function wrapText(string $text, string $fontFile, float $size, int $maxWidth): array
    {
        $words = explode(' ', $text);
        $lines = [];
        $curr  = '';

        foreach ($words as $word) {
            $test = $curr === '' ? $word : "$curr $word";
            $bbox = imagettfbbox($size, 0, $fontFile, $test);
            if (abs($bbox[4] - $bbox[0]) <= $maxWidth) {
                $curr = $test;
            } else {
                if ($curr !== '') {
                    $lines[] = $curr;
                }
                $curr = $word;
            }
        }
        if ($curr !== '') {
            $lines[] = $curr;
        }
        return $lines ?: [$text];
    }

    /**
     * Output a plain error image (no events found).
     */
    private function outputErrorImage(string $msg): void
    {
        $im = imagecreatetruecolor(600, 80);
        $bg = imagecolorallocate($im, 255, 248, 248);
        $fg = imagecolorallocate($im, 160, 0, 0);
        imagefill($im, 0, 0, $bg);
        $fontR = self::FONT_REGULAR;
        $bbox  = imagettfbbox(14, 0, $fontR, $msg);
        $tw    = abs($bbox[4] - $bbox[0]);
        imagettftext($im, 14, 0, (int)((600 - $tw) / 2), 45, $fg, $fontR, $msg);
        header('Content-Type: image/png');
        imagepng($im);
        imagedestroy($im);
    }
}
