<?php

declare(strict_types=1);

namespace App\Services\Prelims\Pdf\Extractors;

final class BinaryFileValidator
{
    public const MAX_PDF_BYTES = 26214400; // 25 MB

    /**
     * Validate a PDF file strictly at the binary level (magic bytes, EOF marker,
     * MIME type, size limit, and SHA-256 checksum).
     *
     * @return array{valid: bool, message: string, size?: int, sha256?: string}
     */
    public function validatePdf(string $filePath): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['valid' => false, 'message' => 'Uploaded file is missing or unreadable.'];
        }

        $size = filesize($filePath);
        if ($size === false || $size < 16) {
            return ['valid' => false, 'message' => 'Uploaded file is empty or too small to be a valid PDF.'];
        }

        if ($size > self::MAX_PDF_BYTES) {
            return ['valid' => false, 'message' => 'PDF exceeds the maximum allowed size of 25 MB.'];
        }

        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            return ['valid' => false, 'message' => 'Unable to open PDF in binary read mode.'];
        }

        try {
            // 1. Check %PDF- magic header within first 1024 bytes (PDF spec allows leading BOM/comments)
            $header = fread($fh, 1024);
            if ($header === false || !preg_match('/%PDF-[12]\.\d/', $header)) {
                return ['valid' => false, 'message' => 'Invalid binary signature: file is not a genuine PDF document.'];
            }

            // 2. Check %%EOF marker near end of file (last 2048 bytes)
            $seekOffset = max(0, $size - 2048);
            fseek($fh, $seekOffset);
            $tail = fread($fh, 2048);
            if ($tail === false || !str_contains($tail, '%%EOF')) {
                return ['valid' => false, 'message' => 'Corrupted or truncated PDF binary: missing %%EOF marker.'];
            }
        } finally {
            fclose($fh);
        }

        // 3. MIME verification via fileinfo if available
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $filePath);
                unset($finfo);
                $allowedMimes = ['application/pdf', 'application/x-pdf', 'application/octet-stream'];
                if (is_string($mime) && !in_array($mime, $allowedMimes, true)) {
                    return ['valid' => false, 'message' => "Unsupported binary MIME type detected: {$mime}."];
                }
            }
        }

        $sha256 = hash_file('sha256', $filePath);
        if ($sha256 === false) {
            return ['valid' => false, 'message' => 'Failed to compute binary SHA-256 digest.'];
        }

        return [
            'valid'   => true,
            'message' => 'Valid PDF binary.',
            'size'    => $size,
            'sha256'  => $sha256,
        ];
    }

    /**
     * Read entire file safely in binary mode.
     */
    public function readBinary(string $filePath): string
    {
        $fh = fopen($filePath, 'rb');
        if ($fh === false) {
            throw new \RuntimeException("Unable to open binary file: {$filePath}");
        }
        try {
            $data = stream_get_contents($fh);
            if ($data === false) {
                throw new \RuntimeException("Failed reading binary stream: {$filePath}");
            }
            return $data;
        } finally {
            fclose($fh);
        }
    }
}
