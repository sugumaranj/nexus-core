<?php

declare(strict_types=1);

/**
 * =========================================================================
 * NexusCore EMS — Storage Hygiene & Disk Pruning Maintenance Script
 * =========================================================================
 * File        : cleanup_storage.php
 * Location    : scripts/
 * Description : Cleans temporary generated PDFs, uncompleted cache, and
 *               stale ZIP packages older than 24 hours to prevent disk
 *               exhaustion. Also prunes log files older than 30 days.
 *
 * Usage:
 *   php scripts/cleanup_storage.php            # Execute cleanup
 *   php scripts/cleanup_storage.php --dry-run  # Preview candidates without deleting
 * =========================================================================
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script must be executed via CLI.\n");
}

$projectRoot = dirname(__DIR__);
$isDryRun = in_array('--dry-run', $argv, true);

$now = time();
$tempFileTtl = 86400;       // 24 hours
$logFileTtl  = 30 * 86400;  // 30 days

$deletedCount = 0;
$freedBytes   = 0;

echo "[" . date('Y-m-d H:i:s') . "] Storage Hygiene Maintenance started" . ($isDryRun ? " (DRY-RUN)" : "") . "...\n";

// 1. Scan storage/pdf_cache
$pdfCacheDir = $projectRoot . '/storage/pdf_cache';
if (is_dir($pdfCacheDir)) {
    pruneDirectory($pdfCacheDir, $now, $tempFileTtl, ['pdf'], $isDryRun, $deletedCount, $freedBytes);
}

// 2. Scan storage/certificates for tmp_* files
$certDir = $projectRoot . '/storage/certificates';
if (is_dir($certDir)) {
    $dirIterator = new \DirectoryIterator($certDir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isDot() || $fileInfo->isDir()) {
            continue;
        }
        $filename = $fileInfo->getFilename();
        if (str_starts_with($filename, 'tmp_cert_') && str_ends_with($filename, '.pdf')) {
            $age = $now - $fileInfo->getMTime();
            if ($age > $tempFileTtl) {
                $size = $fileInfo->getSize();
                if (!$isDryRun) {
                    @unlink($fileInfo->getPathname());
                }
                echo "  [PRUNE TEMP CERT] {$filename} (" . round($size / 1024, 2) . " KB, age: " . round($age / 3600, 1) . "h)\n";
                $deletedCount++;
                $freedBytes += $size;
            }
        }
    }
}

// 3. Scan storage/zips or temporary zip directories
$zipsDir = $projectRoot . '/storage/zips';
if (is_dir($zipsDir)) {
    pruneDirectory($zipsDir, $now, $tempFileTtl, ['zip'], $isDryRun, $deletedCount, $freedBytes);
}

// 4. Scan storage/logs for rotated logs older than 30 days
$logsDir = $projectRoot . '/storage/logs';
if (is_dir($logsDir)) {
    $dirIterator = new \DirectoryIterator($logsDir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isDot() || $fileInfo->isDir()) {
            continue;
        }
        $filename = $fileInfo->getFilename();
        if ($filename === '.gitignore' || $filename === '.htaccess') {
            continue;
        }
        // Only prune rotated daily logs, e.g. nexuscore-YYYY-MM-DD.log
        if (str_starts_with($filename, 'nexuscore-') && str_ends_with($filename, '.log')) {
            $age = $now - $fileInfo->getMTime();
            if ($age > $logFileTtl) {
                $size = $fileInfo->getSize();
                if (!$isDryRun) {
                    @unlink($fileInfo->getPathname());
                }
                echo "  [PRUNE OLD LOG] {$filename} (" . round($size / 1024, 2) . " KB, age: " . round($age / 86400, 1) . "d)\n";
                $deletedCount++;
                $freedBytes += $size;
            }
        }
    }
}

$freedMB = round($freedBytes / (1024 * 1024), 2);
echo "[" . date('Y-m-d H:i:s') . "] Storage Hygiene finished. Pruned {$deletedCount} file(s), freed {$freedMB} MB.\n";
exit(0);

/**
 * Helper to safely prune files in a directory matching specific extensions.
 */
function pruneDirectory(
    string $dir,
    int $now,
    int $ttl,
    array $allowedExts,
    bool $isDryRun,
    int &$deletedCount,
    int &$freedBytes
): void {
    $dirIterator = new \DirectoryIterator($dir);
    foreach ($dirIterator as $fileInfo) {
        if ($fileInfo->isDot() || $fileInfo->isDir()) {
            continue;
        }

        $filename = $fileInfo->getFilename();

        // Strict guard: Never delete system or hidden files
        if (str_starts_with($filename, '.') || $filename === '.gitignore' || $filename === '.htaccess') {
            continue;
        }

        $ext = strtolower($fileInfo->getExtension());
        if (!in_array($ext, $allowedExts, true)) {
            continue;
        }

        $age = $now - $fileInfo->getMTime();
        if ($age > $ttl) {
            $size = $fileInfo->getSize();
            if (!$isDryRun) {
                @unlink($fileInfo->getPathname());
            }
            echo "  [PRUNE FILE] {$filename} (" . round($size / 1024, 2) . " KB, age: " . round($age / 3600, 1) . "h)\n";
            $deletedCount++;
            $freedBytes += $size;
        }
    }
}
