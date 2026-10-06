<?php

declare(strict_types=1);

/**
 * -------------------------------------------------------------------------
 * NexusCore EMS
 * -------------------------------------------------------------------------
 * File        : CertificateRankService.php
 * Location    : app/Services/
 * Description : Single authoritative source for rank-related labels and
 *               the certificate display label.
 *
 * Design rules (critical)
 * -------------------------------------------------------------------------
 * • isWinnerRank() is the ONLY place that determines whether a rank value
 *   represents a winner. Never replicate this logic elsewhere.
 * • formatWinnerRank() NEVER returns 'Participation'.
 *   A null rank does not automatically mean Participant.
 * • getCertificateDisplayLabel() is the ONLY place that produces the
 *   'Participation' string. It requires an explicit certificate_type = 'Participant'.
 * • All views, services, ZIP builders, and verification controllers must
 *   call getCertificateDisplayLabel() rather than formatting rank independently.
 *
 * Project     : NexusCore
 * Branch      : feature/certificate-system-refactor
 * -------------------------------------------------------------------------
 */

namespace App\Services;

final class CertificateRankService
{
    // =========================================================================
    // RANK EVALUATION
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Determine whether a rank_position value represents a Winner certificate.
     *
     * Rules:
     *  - Ranks 1, 2, 3 are Winner ranks.
     *  - null, 0, or any other integer is NOT a winner rank.
     *  - This method is the single authoritative source of the 1/2/3 rule.
     *  - Do NOT use result_status text to determine winner status.
     *
     * @param int|null $rank
     * @return bool
     * -------------------------------------------------------------------------
     */
    public function isWinnerRank(?int $rank): bool
    {
        return $rank !== null && in_array($rank, [1, 2, 3], true);
    }

    /**
     * -------------------------------------------------------------------------
     * Format a winner rank as a human-readable ordinal string.
     *
     * Returns null for any rank that is not 1, 2, or 3.
     * NEVER returns 'Participation' — that is the responsibility of
     * getCertificateDisplayLabel() only.
     *
     * @param int|null $rank
     * @return string|null  '1st' | '2nd' | '3rd' | null
     * -------------------------------------------------------------------------
     */
    public function formatWinnerRank(?int $rank): ?string
    {
        return match ($rank) {
            1 => '1st Place',
            2 => '2nd Place',
            3 => '3rd Place',
            default => null,   // NOT 'Participation' — caller decides meaning of null
        };
    }

    // =========================================================================
    // DISPLAY LABEL (the ONLY place 'Participation' can be produced)
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Produce the human-readable certificate type + rank label.
     *
     * This is the ONLY method that produces 'Participation'.
     * It requires an explicit certificate_type = 'Participant' — a null rank
     * alone never produces 'Participation'.
     *
     * Examples:
     *   getCertificateDisplayLabel('Winner', 1) → '1st'
     *   getCertificateDisplayLabel('Winner', 2) → '2nd'
     *   getCertificateDisplayLabel('Winner', 3) → '3rd'
     *   getCertificateDisplayLabel('Winner', 4) → 'Winner (Unranked)'
     *   getCertificateDisplayLabel('Winner', null) → 'Winner (Unranked)'
     *   getCertificateDisplayLabel('Participant', null) → 'Participation'
     *   getCertificateDisplayLabel('Participant', 1) → 'Participation'
     *   getCertificateDisplayLabel('Legacy', null) → 'Historical Certificate'
     *   getCertificateDisplayLabel('Legacy', 2) → 'Historical Certificate'
     *
     * @param string   $certificateType  'Winner' | 'Participant' | 'Legacy'
     * @param int|null $rank             rank_position from the certificate row
     * @return string
     * -------------------------------------------------------------------------
     */
    public function getCertificateDisplayLabel(string $certificateType, ?int $rank): string
    {
        if ($certificateType === 'Winner') {
            return $this->formatWinnerRank($rank) ?? 'Winner (Unranked)';
        }

        if ($certificateType === 'Participant') {
            return 'Participation';
        }

        // Legacy or any unrecognized type
        return 'Historical Certificate';
    }

    // =========================================================================
    // ZIP SUBFOLDER HELPERS
    // =========================================================================

    /**
     * -------------------------------------------------------------------------
     * Return the ZIP subfolder path for a given winner rank.
     * Used by CertificatePackageService when building Winner ZIPs.
     *
     * @param int|null $rank
     * @return string  e.g. 'Winners/1st/', 'Winners/2nd/', 'Winners/Unranked/'
     * -------------------------------------------------------------------------
     */
    public function getWinnerZipSubfolder(?int $rank): string
    {
        return match ($rank) {
            1 => 'Winners/1st/',
            2 => 'Winners/2nd/',
            3 => 'Winners/3rd/',
            default => 'Winners/Unranked/',
        };
    }

    /**
     * -------------------------------------------------------------------------
     * Return the ZIP subfolder path for a Participant certificate.
     *
     * @return string  'Participants/'
     * -------------------------------------------------------------------------
     */
    public function getParticipantZipSubfolder(): string
    {
        return 'Participants/';
    }
}
