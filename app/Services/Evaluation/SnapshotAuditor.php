<?php
declare(strict_types=1);

namespace App\Services\Evaluation;

class SnapshotAuditor
{
    /**
     * Recursively sorts associative array keys alphabetically.
     * This ensures deterministic canonical JSON representation.
     */
    private function recursiveKsort(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->recursiveKsort($value);
            }
        }
    }

    /**
     * Generates canonical JSON and its SHA-256 hash.
     * 
     * @param array $snapshotData The raw snapshot array data.
     * @return array{json: string, hash: string}
     */
    public function canonicalizeAndHash(array $snapshotData): array
    {
        // 1. Deterministic Key Sort
        $this->recursiveKsort($snapshotData);

        // 2. Serialize with exact canonical rules (no pretty print, unescaped slashes, unescaped unicode)
        $canonicalJson = json_encode($snapshotData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($canonicalJson === false) {
            throw new \RuntimeException('Failed to JSON encode snapshot data: ' . json_last_error_msg());
        }

        // 3. Hash
        $hash = hash('sha256', $canonicalJson);

        return [
            'json' => $canonicalJson,
            'hash' => $hash
        ];
    }
}
