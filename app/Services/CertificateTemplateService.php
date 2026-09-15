<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CertificateTemplateModel;
use App\Models\AuditLogModel;
use setasign\Fpdi\Fpdi;

class CertificateTemplateService
{
    private CertificateTemplateModel $templateModel;
    private AuditLogModel $auditModel;

    public const TEMPLATE_STORAGE_DIR = __DIR__ . '/../../storage/certificate_templates';
    public const MAX_FILE_SIZE = 10 * 1024 * 1024; // 10 MB

    public const FIELD_REGISTRY = [
        'participant_name', 'register_number', 'department',
        'event_name', 'symposium_name', 'held_on', 'academic_year',
        'rank_label', 'rank_check_1', 'rank_check_2', 'rank_check_3',
        'gender_check_male', 'gender_check_female',
        // QR code verification field — square, contains public verification URL
        'qr_code',
        // Legacy — kept for backward-compat loading; ignored during validation
        'venue',
    ];

    /**
     * Server-side field property whitelist per field type.
     * Only properties listed here are forwarded to the generator.
     * Unknown/extra properties are silently dropped by whitelistField().
     */
    public const ALLOWED_PROPS = [
        'text' => [
            'field_key', 'type', 'x_pt', 'y_pt', 'width_pt', 'height_pt',
            'font_family', 'font_size', 'font_style', 'text_color',
            'alignment', 'auto_shrink', 'min_font_size',
        ],
        'rank_indicator' => [
            'field_key', 'type', 'rank', 'x_pt', 'y_pt', 'width_pt', 'height_pt',
        ],
        'gender_indicator' => [
            'field_key', 'type', 'x_pt', 'y_pt', 'width_pt', 'height_pt',
        ],
        'qr_code' => [
            // QR is always square: width_pt == height_pt enforced server-side.
            'field_key', 'type', 'x_pt', 'y_pt', 'width_pt', 'height_pt',
        ],
    ];

    /**
     * Tier 1 — Schema-only validation for the designer preview endpoint.
     *
     * Intentionally permissive: allows incomplete/partial field configurations
     * so the coordinator can preview a work-in-progress design without needing
     * to complete the entire template first.
     *
     * Enforces:
     *   - field_key is in FIELD_REGISTRY
     *   - type is a known value
     *   - coordinates are numeric and non-negative (x >= 0, y >= 0)
     *   - dimensions are positive (w > 0, h > 0)
     *   - rank_indicator fields carry a valid rank value (1, 2, or 3)
     *   - no duplicate field_key in the submitted array
     *
     * Does NOT enforce:
     *   - any mandatory field must be present
     *   - fields must be within page boundaries (out-of-boundary allowed in preview)
     *
     * @param  array  $fields     Array of raw field data from the browser POST
     * @param  float  $pageWidth  Template page width in pt (unused at Tier 1, kept for signature parity)
     * @param  float  $pageHeight Template page height in pt (unused at Tier 1)
     * @return array  { success: bool, message?: string }
     */
    public function validateFieldSchemaOnly(array $fields, float $pageWidth = 0.0, float $pageHeight = 0.0): array
    {
        $validTypes = ['text', 'rank_indicator', 'gender_indicator', 'qr_code'];

        // Minimum QR dimension in pt: 57pt ≈ 20mm — minimum reliable scan size.
        // This must match the UI constraint in designer.php.
        $minQrSizePt = 57.0;

        $seenKeys   = [];

        foreach ($fields as $field) {
            $key  = $field['field_key'] ?? '';
            $type = $field['type']      ?? '';

            // field_key must be a known registry entry
            if ($key === '' || !in_array($key, self::FIELD_REGISTRY, true)) {
                return ['success' => false, 'message' => 'Unknown field key: ' . htmlspecialchars($key)];
            }

            // type must be valid
            if (!in_array($type, $validTypes, true)) {
                return ['success' => false, 'message' => 'Invalid field type "' . htmlspecialchars($type) . '" for field: ' . htmlspecialchars($key)];
            }

            // no duplicates
            if (isset($seenKeys[$key])) {
                return ['success' => false, 'message' => 'Duplicate field key: ' . htmlspecialchars($key)];
            }
            $seenKeys[$key] = true;

            // coordinates and dimensions must be numeric
            foreach (['x_pt', 'y_pt', 'width_pt', 'height_pt'] as $prop) {
                if (!isset($field[$prop]) || !is_numeric($field[$prop])) {
                    return ['success' => false, 'message' => 'Non-numeric or missing "' . $prop . '" for field: ' . htmlspecialchars($key)];
                }
            }

            $x = (float)$field['x_pt'];
            $y = (float)$field['y_pt'];
            $w = (float)$field['width_pt'];
            $h = (float)$field['height_pt'];

            // coordinates must be non-negative (a field at negative origin is a bug, not a partial state)
            if ($x < 0) {
                return ['success' => false, 'message' => 'Negative x_pt for field: ' . htmlspecialchars($key)];
            }
            if ($y < 0) {
                return ['success' => false, 'message' => 'Negative y_pt for field: ' . htmlspecialchars($key)];
            }

            // dimensions must be positive
            if ($w <= 0) {
                return ['success' => false, 'message' => 'Zero or negative width_pt for field: ' . htmlspecialchars($key)];
            }
            if ($h <= 0) {
                return ['success' => false, 'message' => 'Zero or negative height_pt for field: ' . htmlspecialchars($key)];
            }

            // rank_indicator fields must carry a valid rank (1, 2, or 3)
            if ($type === 'rank_indicator') {
                $rank = (int)($field['rank'] ?? 0);
                if (!in_array($rank, [1, 2, 3], true)) {
                    return ['success' => false, 'message' => 'Invalid rank value (' . $rank . ') for field: ' . htmlspecialchars($key) . '. Must be 1, 2, or 3.'];
                }
            }

            // qr_code fields must be square and meet minimum size
            if ($type === 'qr_code') {
                if (abs($w - $h) > 0.5) {
                    return ['success' => false, 'message' => 'QR Code field must be square (width must equal height). Got: ' . round($w, 2) . ' × ' . round($h, 2) . ' pt.'];
                }
                if ($w < $minQrSizePt) {
                    return ['success' => false, 'message' => 'QR Code field is too small (' . round($w, 2) . 'pt). Minimum size is ' . $minQrSizePt . 'pt (~20mm) for reliable scanning.'];
                }
            }
        }

        return ['success' => true];
    }

    /**
     * Strip any properties from a field array that are not in the server-side whitelist
     * for its type. This is the authoritative property filter — client-side removal
     * of properties (e.g. delete o.id) is NOT a security boundary.
     *
     * @param  array $field  Raw field array from browser POST
     * @return array         Whitelisted field array safe to pass to the generator
     */
    public function whitelistField(array $field): array
    {
        $type    = $field['type'] ?? 'text';
        $allowed = self::ALLOWED_PROPS[$type] ?? self::ALLOWED_PROPS['text'];
        return array_intersect_key($field, array_flip($allowed));
    }



    public function __construct()
    {
        $this->templateModel = new CertificateTemplateModel();
        $this->auditModel = new AuditLogModel();
    }

    public function uploadTemplate(array $file, array $data, int $userId): array
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error.'];
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            return ['success' => false, 'message' => 'File size exceeds maximum limit of 10MB.'];
        }

        $tmpPath = $file['tmp_name'];
        if (!file_exists($tmpPath)) {
            return ['success' => false, 'message' => 'Uploaded file not found.'];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpPath);
        if ($mime !== 'application/pdf') {
            return ['success' => false, 'message' => 'Uploaded file must be a PDF.'];
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($extension !== 'pdf') {
            return ['success' => false, 'message' => 'Uploaded file extension must be .pdf.'];
        }

        try {
            $fpdi = new Fpdi('P', 'pt');
            $pageCount = $fpdi->setSourceFile($tmpPath);
            if ($pageCount !== 1) {
                return ['success' => false, 'message' => 'Template PDF must contain exactly 1 page. This file has ' . $pageCount . ' pages.'];
            }
            $tplIdx = $fpdi->importPage(1);
            $dims = $fpdi->getTemplateSize($tplIdx);

            $widthPt = $dims['width'];
            $heightPt = $dims['height'];
            $orientation = $widthPt > $heightPt ? 'Landscape' : 'Portrait';
        } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
            return ['success' => false, 'message' => 'This PDF uses unsupported compression (PDF 1.5+). Please re-save it as PDF 1.4 or use "Print to PDF" before uploading.'];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to parse PDF file. Details: ' . $e->getMessage()];
        }

        $hash = hash_file('sha256', $tmpPath);
        $filename = 'tpl_' . time() . '_' . substr($hash, 0, 8) . '.pdf';

        if (!is_dir(self::TEMPLATE_STORAGE_DIR)) {
            mkdir(self::TEMPLATE_STORAGE_DIR, 0755, true);
        }

        $destPath = self::TEMPLATE_STORAGE_DIR . '/' . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            return ['success' => false, 'message' => 'File upload failed to move to storage.'];
        }

        $relativePath = 'storage/certificate_templates/' . $filename;

        $templateData = array_merge($data, [
            'file_path' => $relativePath,
            'file_hash' => $hash,
            'page_width_pt' => $widthPt,
            'page_height_pt' => $heightPt,
            'page_orientation' => $orientation,
            'created_by' => $userId
        ]);

        $newId = $this->templateModel->create($templateData);

        if ($newId) {
            $this->auditModel->log('certificate_templates', $newId, 'template_uploaded', $userId, 'Uploaded new certificate template');
            return ['success' => true, 'template_id' => $newId];
        }

        return ['success' => false, 'message' => 'Failed to save template record.'];
    }

    public function validateForActivation(int $templateId): array
    {
        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found.'];
        }

        $activeVersion = $this->templateModel->getActiveVersion($templateId);
        if (!$activeVersion) {
            return ['success' => false, 'message' => 'No designer configuration found. Please open the designer and save field positions.'];
        }

        $fieldConfig = json_decode($activeVersion['field_config'], true) ?? [];
        $fieldKeys = array_column($fieldConfig, 'field_key');
        
        $pageWidth = (float)($template['page_width_pt'] ?? 0);
        $pageHeight = (float)($template['page_height_pt'] ?? 0);

        // Required field validation
        $requiredAlways = ['participant_name'];
        $missing = [];

        foreach ($requiredAlways as $req) {
            if (!in_array($req, $fieldKeys, true)) {
                $missing[] = $req;
            }
        }

        // Rank configuration validation
        $rankMode = $template['rank_display_mode'] ?? 'none';
        if ($rankMode === 'checkboxes') {
            $reqRank = ['rank_check_1', 'rank_check_2', 'rank_check_3'];
            foreach ($reqRank as $req) {
                if (!in_array($req, $fieldKeys, true)) {
                    $missing[] = $req;
                }
            }
        } elseif ($rankMode === 'label') {
            if (!in_array('rank_label', $fieldKeys, true)) {
                $missing[] = 'rank_label';
            }
        }

        if (!empty($missing)) {
            return [
                'success' => false,
                'missing' => $missing,
                'message' => 'Cannot activate: missing required fields: ' . implode(', ', $missing)
            ];
        }
        
        // Strict boundary and collision validation for activation (sanity check)
        $seenKeys = [];
        foreach ($fieldConfig as $f) {
            if (isset($seenKeys[$f['field_key']])) {
                return ['success' => false, 'message' => 'Cannot activate: Duplicate field found (' . $f['field_key'] . ').'];
            }
            $seenKeys[$f['field_key']] = true;
            
            $x = (float)($f['x_pt'] ?? 0);
            $y = (float)($f['y_pt'] ?? 0);
            $w = (float)($f['width_pt'] ?? 0);
            $h = (float)($f['height_pt'] ?? 0);
            
            if ($x < 0 || $y < 0 || $x + $w > $pageWidth || $y + $h > $pageHeight || $w <= 0 || $h <= 0) {
                return ['success' => false, 'message' => "Cannot activate: Field '{$f['field_key']}' is out of bounds or invalid."];
            }
        }

        return ['success' => true];
    }

    public function activate(int $templateId, int $userId): array
    {
        $validation = $this->validateForActivation($templateId);
        if (!$validation['success']) {
            return $validation;
        }

        $activeVersion = $this->templateModel->getActiveVersion($templateId);
        if ($activeVersion) {
            $this->templateModel->lockVersion((int)$activeVersion['version_id']);
        }

        $this->templateModel->update($templateId, ['is_active' => 1]);
        $this->auditModel->log('certificate_templates', $templateId, 'template_activated', $userId, 'Activated certificate template');

        return ['success' => true];
    }

    public function deactivate(int $templateId, int $userId): array
    {
        $this->templateModel->update($templateId, ['is_active' => 0]);
        $this->auditModel->log('certificate_templates', $templateId, 'template_deactivated', $userId, 'Deactivated certificate template');

        return ['success' => true];
    }

    public function deleteTemplate(int $templateId, int $userId): array
    {
        if ($this->templateModel->hasGeneratedCertificates($templateId)) {
            return ['success' => false, 'message' => 'Cannot delete: certificates have been generated from this template. Archive it instead.'];
        }

        $template = $this->templateModel->findById($templateId);
        if ($template) {
            $absPath = __DIR__ . '/../../' . $template['file_path'];
            if (file_exists($absPath)) {
                unlink($absPath);
            }
            $this->templateModel->delete($templateId);
            $this->auditModel->log('certificate_templates', $templateId, 'template_deleted', $userId, 'Deleted certificate template');
        }

        return ['success' => true];
    }

    public function archiveTemplate(int $templateId, int $userId): array
    {
        $this->templateModel->archive($templateId);
        $this->auditModel->log('certificate_templates', $templateId, 'template_archived', $userId, 'Archived certificate template');

        return ['success' => true];
    }

    public function unarchiveTemplate(int $templateId, int $userId): array
    {
        $this->templateModel->unarchive($templateId);
        $this->auditModel->log('certificate_templates', $templateId, 'template_unarchived', $userId, 'Unarchived certificate template');

        return ['success' => true];
    }

    public function saveDesign(int $templateId, array $fields, int $userId): array
    {
        $template = $this->templateModel->findById($templateId);
        if (!$template) {
            return ['success' => false, 'message' => 'Template not found.'];
        }

        $pageWidth = (float)($template['page_width_pt'] ?? 0);
        $pageHeight = (float)($template['page_height_pt'] ?? 0);

        $validFields = [];
        $seenKeys = [];

        foreach ($fields as $field) {
            if (!isset($field['field_key'], $field['x_pt'], $field['y_pt'])) {
                return ['success' => false, 'message' => 'Missing required field properties (key, x, y).'];
            }
            
            $key = $field['field_key'];
            if (!in_array($key, self::FIELD_REGISTRY, true)) {
                return ['success' => false, 'message' => 'Invalid field key: ' . htmlspecialchars($key)];
            }
            
            if (isset($seenKeys[$key])) {
                return ['success' => false, 'message' => 'Duplicate field detected: ' . htmlspecialchars($key) . '. Fields must be singletons.'];
            }
            $seenKeys[$key] = true;

            if (!is_numeric($field['x_pt']) || !is_numeric($field['y_pt']) || 
                !is_numeric($field['width_pt'] ?? 1) || !is_numeric($field['height_pt'] ?? 1)) {
                return ['success' => false, 'message' => 'Coordinates and dimensions must be numeric for ' . htmlspecialchars($key) . '.'];
            }

            $x = (float)$field['x_pt'];
            $y = (float)$field['y_pt'];
            $w = (float)($field['width_pt'] ?? 0);
            $h = (float)($field['height_pt'] ?? 0);

            if ($w <= 0 || $h <= 0) {
                return ['success' => false, 'message' => 'Invalid dimensions (<= 0) for ' . htmlspecialchars($key) . '.'];
            }
            
            if ($x < 0 || $y < 0 || $x + $w > $pageWidth || $y + $h > $pageHeight) {
                return ['success' => false, 'message' => 'Field ' . htmlspecialchars($key) . ' is outside the actual PDF page boundaries.'];
            }

            $type = $field['type'] ?? 'text';
            if (!in_array($type, ['text', 'rank_indicator', 'gender_indicator', 'qr_code'])) {
                return ['success' => false, 'message' => 'Unknown field type: ' . htmlspecialchars($type)];
            }

            if ($type === 'rank_indicator') {
                $rank = (int)($field['rank'] ?? 0);
                if (!in_array($rank, [1, 2, 3], true)) {
                    return ['success' => false, 'message' => 'Invalid rank value for ' . htmlspecialchars($key) . '.'];
                }
            }

            // Server-side QR geometry enforcement (prevents bypassing UI constraints)
            if ($type === 'qr_code') {
                $minQrSizePt = 57.0;
                if (abs($w - $h) > 0.5) {
                    return ['success' => false, 'message' => 'QR Code field must be square (width must equal height). Got: ' . round($w, 2) . ' × ' . round($h, 2) . ' pt.'];
                }
                if ($w < $minQrSizePt) {
                    return ['success' => false, 'message' => 'QR Code field is too small (' . round($w, 2) . 'pt). Minimum is ' . $minQrSizePt . 'pt (~20mm) for reliable scanning.'];
                }
                // Only one QR code field allowed per template
                if (($seenQr ?? false)) {
                    return ['success' => false, 'message' => 'Only one QR Code field is allowed per template.'];
                }
                $seenQr = true;
            }

            $validFields[] = $field;
        }

        $activeVersion = $this->templateModel->getActiveVersion($templateId);
        
        if ($activeVersion && !$activeVersion['locked']) {
            $this->templateModel->deleteVersion((int)$activeVersion['version_id']);
        }

        $versionId = $this->templateModel->createVersion($templateId, $validFields, $userId);

        $this->auditModel->log('certificate_templates', $templateId, 'design_saved', $userId, "Saved design version");

        return ['success' => true, 'version_id' => $versionId];
    }

    public function resolveTemplate(int $symposiumEventId, ?int $symposiumId = null): array|false
    {
        $eventConfig = $this->templateModel->getEventConfig($symposiumEventId);
        if ($eventConfig && $eventConfig['template_id']) {
            $template = $this->templateModel->findById((int)$eventConfig['template_id']);
            if ($template) {
                return $template;
            }
        }

        return $this->templateModel->getSystemDefaultTemplate();
    }
}
