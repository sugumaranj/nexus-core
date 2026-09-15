<?php

declare(strict_types=1);

// Palette labels — mirrors JS PALETTE_LABELS for overlap messages
$paletteLabels = [
    'participant_name'    => 'Participant Name',
    'register_number'    => 'Register Number',
    'department'         => 'Department (Short)',
    'event_name'         => 'Event Name',
    'symposium_name'     => 'Symposium Name',
    'held_on'            => 'Held On',
    'academic_year'      => 'Academic Year (Roman)',
    'rank_label'         => 'Rank Label',
    'rank_check_1'       => '1st Prize Tick',
    'rank_check_2'       => '2nd Prize Tick',
    'rank_check_3'       => '3rd Prize Tick',
    'gender_check_male'  => 'Male Tick',
    'gender_check_female'=> 'Female Tick',
];
?>
<div class="container-fluid py-2">

    <!-- Toast notification container -->
    <div id="designer-toast" style="display:none;position:fixed;top:20px;right:20px;z-index:9999;min-width:280px;padding:12px 18px;border-radius:8px;font-size:14px;font-weight:500;box-shadow:0 4px 16px rgba(0,0,0,0.22);transition:opacity 0.3s;"></div>

    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
        <h1 class="h4 mb-0 text-gray-800">Visual Designer: <?= htmlspecialchars($template['template_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></h1>
        <div class="d-flex align-items-center flex-wrap gap-2">
            <a href="<?= base_url('/certificates') ?>" class="btn btn-sm btn-secondary shadow-sm"><i class="bi bi-x"></i> Cancel</a>
            <div class="d-flex align-items-center gap-1">
                <div>
                    <div style="font-size:9px;color:#888;line-height:1.2">Preview Rank</div>
                    <select id="preview-rank" class="form-select form-select-sm" style="width:120px">
                        <option value="1">1st Prize</option>
                        <option value="2">2nd Prize</option>
                        <option value="3">3rd Prize</option>
                        <option value="4">Participation</option>
                    </select>
                </div>
                <div>
                    <div style="font-size:9px;color:#888;line-height:1.2">Preview Gender</div>
                    <select id="preview-gender" class="form-select form-select-sm" style="width:100px">
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                    </select>
                </div>
                <div style="padding-top:14px">
                    <button id="btn-preview" class="btn btn-sm btn-info shadow-sm text-white"><i class="bi bi-eye"></i> Preview PDF</button>
                </div>
            </div>
            <button id="btn-save" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-save"></i> Save Draft</button>
            <span id="dirty-indicator" style="display:none;font-size:12px;" class="text-warning"><i class="bi bi-circle-fill" style="font-size:8px"></i> Unsaved changes</span>
            <span id="saved-indicator" style="display:none;font-size:12px;" class="text-success"><i class="bi bi-check-circle-fill"></i> Saved</span>
        </div>
    </div>

    <div class="alert alert-info py-1 px-3 mb-2" style="font-size:12px">
        <strong>Instructions:</strong>
        Double-click a field in the palette to add it.&nbsp;&middot;&nbsp;
        Drag to position.&nbsp;&middot;&nbsp;
        Resize using handles.&nbsp;&middot;&nbsp;
        <strong>Preview PDF</strong> uses the <em>current unsaved layout</em>.&nbsp;&middot;&nbsp;
        Page: <code><?= number_format((float)($template['page_width_pt'] ?? 0), 2) ?> &times; <?= number_format((float)($template['page_height_pt'] ?? 0), 2) ?> pt</code>
        &nbsp;&middot;&nbsp; Ctrl+Z undo &nbsp;&middot;&nbsp; Ctrl+Y redo
    </div>

    <div class="row g-3">
        <!-- LEFT: Field Palette -->
        <div class="col-lg-2">
            <div class="card shadow h-100 bg-dark text-white">
                <div class="card-header border-secondary py-2">
                    <h6 class="m-0 font-weight-bold">Fields</h6>
                    <small class="text-muted">Dbl-click to add</small>
                </div>
                <div class="card-body p-2" id="field-palette">
                    <?php
                    $textFields = [
                        'participant_name' => 'Participant Name',
                        'register_number'  => 'Register Number',
                        'department'       => 'Department (Short)',
                        'event_name'       => 'Event Name',
                        'symposium_name'   => 'Symposium Name',
                        'held_on'          => 'Held On',
                        'academic_year'    => 'Academic Year (Roman)',
                        'rank_label'       => 'Rank Label',
                    ];
                    $rankFields = [
                        'rank_check_1' => '1st Prize Tick',
                        'rank_check_2' => '2nd Prize Tick',
                        'rank_check_3' => '3rd Prize Tick',
                    ];
                    $genderFields = [
                        'gender_check_male'   => 'Male Tick',
                        'gender_check_female' => 'Female Tick',
                    ];
                    ?>
                    <div class="text-muted small px-1 mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:1px">Text</div>
                    <?php foreach ($textFields as $key => $label): ?>
                        <div class="badge bg-secondary d-block p-2 mb-1 text-start palette-item"
                             data-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                             data-type="text"
                             style="cursor:pointer;font-size:11px;">
                            <i class="bi bi-input-cursor-text"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-muted small px-1 mt-2 mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:1px">Rank Indicators</div>
                    <?php foreach ($rankFields as $key => $label): ?>
                        <div class="badge bg-success d-block p-2 mb-1 text-start palette-item"
                             data-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                             data-type="rank_indicator"
                             style="cursor:pointer;font-size:11px;">
                            <i class="bi bi-check-square"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-muted small px-1 mt-2 mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:1px">Gender</div>
                    <?php foreach ($genderFields as $key => $label): ?>
                        <div class="badge bg-info d-block p-2 mb-1 text-start palette-item"
                             data-key="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
                             data-type="gender_indicator"
                             style="cursor:pointer;font-size:11px;color:#000">
                            <i class="bi bi-gender-ambiguous"></i> <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endforeach; ?>
                    <div class="text-muted small px-1 mt-2 mb-1" style="font-size:10px;text-transform:uppercase;letter-spacing:1px">Verification</div>
                    <div class="badge d-block p-2 mb-1 text-start palette-item"
                         data-key="qr_code"
                         data-type="qr_code"
                         style="cursor:pointer;font-size:11px;background:#6366f1;color:#fff">
                        <i class="bi bi-qr-code"></i> QR Verification Code
                    </div>
                </div>
            </div>
        </div>

        <!-- CENTER: Canvas Area -->
        <div class="col-lg-8">
            <div class="card shadow h-100">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Canvas</h6>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-secondary" id="btn-zoom-out"><i class="bi bi-zoom-out"></i></button>
                        <button class="btn btn-sm btn-outline-secondary" id="btn-zoom-in"><i class="bi bi-zoom-in"></i></button>
                    </div>
                </div>
                <div class="card-body p-2 overflow-auto bg-secondary" id="canvas-card-body" style="min-height: 400px; display:block;">
                    <div id="canvas-wrapper" style="position: relative; box-shadow: 0 0 20px rgba(0,0,0,0.35); transform-origin: top left; transform: scale(1); display: inline-block;">
                        <canvas id="pdf-bg" style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;pointer-events:none;"></canvas>
                        <div id="cert-canvas" style="position: relative; width: 100%; height: 100%;">
                            <!-- Field divs will be injected here via JS -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Properties Panel -->
        <div class="col-lg-2">
            <div class="card shadow h-100 bg-dark text-white">
                <div class="card-header border-secondary py-2">
                    <h6 class="m-0 font-weight-bold">Properties</h6>
                </div>
                <div class="card-body p-2" id="properties-panel">
                    <div id="no-selection" class="text-muted small text-center mt-3">Select a field to edit its properties.</div>
                    <div id="field-props" style="display:none;">
                        <input type="hidden" id="prop-id">
                        <!-- Type info badge: shown for rank/gender fields (editor metadata) -->
                        <div id="field-type-info" style="display:none;" class="alert alert-secondary py-1 px-2 small mb-2">
                            <strong id="field-type-label"></strong><br>
                            <span id="field-type-meta" class="text-muted" style="font-size:10px;"></span>
                        </div>
                        <div id="text-props-group">
                            <div class="mb-2">
                                <label class="form-label small mb-0">Font Family</label>
                                <select id="prop-font" class="form-select form-select-sm bg-secondary text-white border-dark">
                                    <option value="Helvetica">Helvetica</option>
                                    <option value="Times-Roman">Times</option>
                                    <option value="Courier">Courier</option>
                                </select>
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-0">Font Size (pt)</label>
                                <input type="number" id="prop-size" class="form-control form-control-sm bg-secondary text-white border-dark" min="4" max="144">
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-0">Text Color</label>
                                <input type="color" id="prop-color" class="form-control form-control-sm form-control-color w-100 bg-secondary border-dark" value="#000000">
                            </div>
                            
                            <div class="mb-2">
                                <label class="form-label small mb-0">Alignment</label>
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="prop-align" id="align-L" autocomplete="off" value="L">
                                    <label class="btn btn-outline-light btn-sm" for="align-L"><i class="bi bi-text-left"></i></label>

                                    <input type="radio" class="btn-check" name="prop-align" id="align-C" autocomplete="off" value="C">
                                    <label class="btn btn-outline-light btn-sm" for="align-C"><i class="bi bi-text-center"></i></label>

                                    <input type="radio" class="btn-check" name="prop-align" id="align-R" autocomplete="off" value="R">
                                    <label class="btn btn-outline-light btn-sm" for="align-R"><i class="bi bi-text-right"></i></label>
                                </div>
                            </div>

                            <div class="mb-2 form-check">
                                <input type="checkbox" class="form-check-input" id="prop-bold">
                                <label class="form-check-label small" for="prop-bold">Bold</label>
                            </div>
                            <div class="mb-2 form-check">
                                <input type="checkbox" class="form-check-input" id="prop-italic">
                                <label class="form-check-label small" for="prop-italic">Italic</label>
                            </div>
                        </div>

                        <hr class="border-secondary">
                        <div id="overlap-warning" class="alert alert-warning py-1 px-2 small mb-2" style="display:none;"></div>

                        <div class="mb-2">
                            <label class="form-label small mb-0">Position (pt)</label>
                            <div class="row g-1">
                                <div class="col-6"><input type="number" id="prop-x" class="form-control form-control-sm bg-secondary text-white border-dark" placeholder="X"></div>
                                <div class="col-6"><input type="number" id="prop-y" class="form-control form-control-sm bg-secondary text-white border-dark" placeholder="Y"></div>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small mb-0">Dimensions (pt)</label>
                            <div class="row g-1">
                                <div class="col-6"><input type="number" id="prop-w" class="form-control form-control-sm bg-secondary text-white border-dark" placeholder="W"></div>
                                <div class="col-6"><input type="number" id="prop-h" class="form-control form-control-sm bg-secondary text-white border-dark" placeholder="H"></div>
                            </div>
                        </div>

                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="prop-shrink">
                            <label class="form-check-label small" for="prop-shrink">Auto-shrink</label>
                        </div>

                        <button id="btn-delete-field" class="btn btn-sm btn-danger w-100"><i class="bi bi-trash"></i> Remove Field</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.cert-field {
    position: absolute;
    border: 1px dashed #0d6efd;
    background: rgba(13,110,253,0.07);
    color: #333;
    cursor: move;
    display: flex;
    align-items: center;
    justify-content: center;
/* ── cert-field base ── */
.cert-field {
    position: absolute;
    border: 1px dashed #0d6efd;
    background: rgba(13,110,253,0.07);
    color: #333;
    cursor: move;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    user-select: none;
    box-sizing: border-box;
    overflow: visible;
    white-space: nowrap;
    /* Minimum touch target so tiny fields stay grabbable */
    min-width: 24px;
    min-height: 24px;
}
.cert-field.type-rank_indicator {
    border: 1px dashed #198754;
    background: rgba(25,135,84,0.10);
}
.cert-field.type-gender_indicator {
    border: 1px dashed #0dcaf0;
    background: rgba(13,202,240,0.10);
}
.cert-field.selected {
    border: 2px solid #0d6efd;
    background: rgba(13,110,253,0.18);
    z-index: 100;
}
.cert-field.type-rank_indicator.selected {
    border: 2px solid #198754;
    background: rgba(25,135,84,0.18);
}
.cert-field.type-gender_indicator.selected {
    border: 2px solid #0dcaf0;
    background: rgba(13,202,240,0.18);
}
.cert-field.overlap {
    border-color: #dc3545 !important;
    background: rgba(220,53,69,0.15) !important;
}

/* ── Tick mark shown inside field box (editor-only) ── */
.field-tick-mark {
    font-size: 14px;
    opacity: 0.6;
    pointer-events: none;
    user-select: none;
    line-height: 1;
    flex-shrink: 0;
}
.cert-field.selected .field-tick-mark { opacity: 1; }

/* ── Label badge — shown INSIDE the field, small pill at the top ── */
.field-editor-badge {
    position: absolute;
    top: 1px;
    left: 1px;
    right: 20px;          /* leave room for drag handle on the right */
    font-size: 8px;
    background: rgba(0,0,0,0.50);
    color: #fff;
    padding: 1px 4px;
    border-radius: 3px;
    white-space: nowrap;
    pointer-events: none;
    user-select: none;
    letter-spacing: 0.3px;
    overflow: hidden;
    text-overflow: ellipsis;
    opacity: 0.7;
    transition: opacity 0.15s;
    z-index: 5;
}
.cert-field.selected .field-editor-badge { opacity: 1; }

/* ── Drag handle — always visible bottom-right corner ── */
.field-drag-handle {
    position: absolute;
    bottom: 2px;
    right: 3px;
    font-size: 10px;
    color: rgba(0,0,0,0.45);
    pointer-events: none;
    user-select: none;
    line-height: 1;
    z-index: 6;
    cursor: move;
}
.cert-field.selected .field-drag-handle { color: rgba(0,0,0,0.75); }

@keyframes spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
.spin { display:inline-block; animation:spin 0.8s linear infinite; }
</style>

<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
<script>
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const PDF_WIDTH_PT  = <?= (float)($template['page_width_pt']  ?? 842) ?>;
    const PDF_HEIGHT_PT = <?= (float)($template['page_height_pt'] ?? 595) ?>;
    const TEMPLATE_ID   = <?= (int)($template['certificate_template_id'] ?? 0) ?>;
    const CSRF_TOKEN    = '<?= htmlspecialchars(\App\Core\Session::get('_token', ''), ENT_QUOTES, 'UTF-8') ?>';
    const BASE_URL      = '<?= rtrim(base_url(''), '/') ?>';
    const PALETTE_LABELS = <?= json_encode($paletteLabels) ?>;

    let existingFields  = <?= json_encode($fieldConfig ?? []) ?>;
    let fieldsData      = [];
    let selectedFieldId = null;
    let fieldCounter    = 0;
    let isDirty         = false;
    let undoStack       = [];
    let redoStack       = [];

    const canvasWrapper = document.getElementById('canvas-wrapper');
    const canvas        = document.getElementById('cert-canvas');
    let   zoomLevel     = 1.0;

    // Editor-only placeholder text for text fields (never in PDF)
    const fieldLabels = {
        'participant_name': '[Participant Name]',
        'register_number':  '[Register Number]',
        'department':       '[Department]',
        'venue':            '[Venue Name]',
        'event_name':       '[Event Name]',
        'symposium_name':   '[Symposium Name]',
        'held_on':          '[Held On]',
        'venue':            '[Venue]',
        'academic_year':    '[Academic Year]',
        'rank_label':       '[Rank Label]',
    };

    window.addEventListener('beforeunload', (e) => {
        if (isDirty) { e.preventDefault(); e.returnValue = ''; }
    });

    function updateDirtyState() {
        document.getElementById('dirty-indicator').style.display = isDirty  ? 'inline' : 'none';
        document.getElementById('saved-indicator').style.display = !isDirty ? 'inline' : 'none';
    }

    function saveState() {
        undoStack.push(JSON.stringify(fieldsData));
        redoStack = [];
        if (undoStack.length > 50) undoStack.shift();
        isDirty = true;
        updateDirtyState();
    }

    function showNotification(message, type) {
        const toast = document.getElementById('designer-toast');
        toast.textContent      = message;
        toast.style.background = type === 'success' ? '#198754' : '#dc3545';
        toast.style.color      = '#fff';
        toast.style.display    = 'block';
        toast.style.opacity    = '1';
        clearTimeout(window._toastTimer);
        window._toastTimer = setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => { toast.style.display = 'none'; }, 320);
        }, 3500);
    }
    
    function checkOverlaps() {
        const overlapMessages = [];
        document.querySelectorAll('.cert-field').forEach(el => el.classList.remove('overlap'));
        for (let i = 0; i < fieldsData.length; i++) {
            for (let j = i + 1; j < fieldsData.length; j++) {
                const f1 = fieldsData[i], f2 = fieldsData[j];
                if (f1.x_pt < f2.x_pt + f2.width_pt &&
                    f1.x_pt + f1.width_pt > f2.x_pt &&
                    f1.y_pt < f2.y_pt + f2.height_pt &&
                    f1.y_pt + f1.height_pt > f2.y_pt) {
                    document.getElementById('field-' + f1.id)?.classList.add('overlap');
                    document.getElementById('field-' + f2.id)?.classList.add('overlap');
                    if (selectedFieldId === f1.id || selectedFieldId === f2.id) {
                        const n1 = PALETTE_LABELS[f1.field_key] || f1.field_key;
                        const n2 = PALETTE_LABELS[f2.field_key] || f2.field_key;
                        overlapMessages.push(n1 + ' overlaps ' + n2);
                    }
                }
            }
        }
        const warn = document.getElementById('overlap-warning');
        if (overlapMessages.length) {
            warn.innerHTML     = '<i class="bi bi-exclamation-triangle"></i> ' + overlapMessages.join('; ');
            warn.style.display = 'block';
        } else {
            warn.style.display = 'none';
        }
    }

    function findValidPosition(w, h) {
        for (let y = 50; y < PDF_HEIGHT_PT - h; y += 20) {
            for (let x = 50; x < PDF_WIDTH_PT - w; x += 20) {
                let collision = false;
                for (const f of fieldsData) {
                    if (x < f.x_pt + f.width_pt && x + w > f.x_pt &&
                        y < f.y_pt + f.height_pt && y + h > f.y_pt) {
                        collision = true;
                        break;
                    }
                }
                if (!collision) return {x, y};
            }
        }
        return null;
    }

    canvasWrapper.style.width  = PDF_WIDTH_PT  + 'px';
    canvasWrapper.style.height = PDF_HEIGHT_PT + 'px';

    // ── Auto-fit: scale canvas so it always fits inside the card body without overflow ──
    // The card body is the parent of canvas-wrapper. We measure its inner width (excluding padding)
    // and compute a scale factor so the PDF canvas fills it exactly.
    // This is ONLY a visual transform — all x_pt/y_pt values remain in PDF-point coordinates (1pt = 1px).
    // interact.js drag delta is already divided by zoomLevel, so drag precision is preserved.
    function applyZoom(newZoom) {
        zoomLevel = newZoom;
        canvasWrapper.style.transform       = `scale(${zoomLevel})`;
        canvasWrapper.style.transformOrigin = 'top left';
        // Set min-height on the card body so scaled canvas doesn't get clipped
        const scaledH = PDF_HEIGHT_PT * zoomLevel + 24;
        document.getElementById('canvas-card-body').style.minHeight = scaledH + 'px';
    }

    function fitCanvasToCard() {
        const cardBody   = canvasWrapper.parentElement;
        // Use the card column width minus padding (left col=2 palette + right col=2 props = 4/12)
        // The center column is col-lg-8 but may be smaller on load. Measure actual available width.
        const available  = cardBody.getBoundingClientRect().width - 24; // 24px padding
        if (available > 50) {
            const fit = Math.min(1.0, available / PDF_WIDTH_PT);
            applyZoom(Math.round(fit * 100) / 100); // round to 2dp
        }
    }

    // Fit on load, and re-fit if window resizes
    fitCanvasToCard();
    window.addEventListener('resize', fitCanvasToCard);

    const pdfUrl = '<?= base_url('/certificates/preview/pdf?template_id=' . (int)($template['certificate_template_id'] ?? 0) . '&preview_only=1') ?>';
    const pdfCanvas = document.getElementById('pdf-bg');
    const pdfCtx = pdfCanvas.getContext('2d');

    // Render the raw background PDF at 2× pixel density for crisp display
    pdfjsLib.getDocument(pdfUrl).promise.then(function(pdfDoc) {
        pdfDoc.getPage(1).then(function(page) {
            const renderScale = 2.0;
            const viewport = page.getViewport({ scale: renderScale });
            pdfCanvas.width  = viewport.width;
            pdfCanvas.height = viewport.height;
            page.render({ canvasContext: pdfCtx, viewport: viewport });
        });
    }).catch(function() {
        // If PDF load fails, show a plain white background gracefully
        pdfCanvas.style.background = '#fff';
    });

    document.getElementById('btn-zoom-in').addEventListener('click',  () => applyZoom(Math.min(2.0, Math.round((zoomLevel + 0.1) * 10) / 10)));
    document.getElementById('btn-zoom-out').addEventListener('click', () => applyZoom(Math.max(0.2, Math.round((zoomLevel - 0.1) * 10) / 10)));

    // COORDINATE CONTRACT: x_pt, y_pt, width_pt, height_pt are PDF points (1px = 1pt).
    // Values are applied directly as CSS pixels and passed unchanged to the server/generator.
    // No coordinate conversion is performed here or anywhere else in the client code.
    function applyFieldStyles(div, data) {
        div.style.left   = data.x_pt     + 'px';
        div.style.top    = data.y_pt     + 'px';
        div.style.width  = data.width_pt  + 'px';
        div.style.height = data.height_pt + 'px';
        div.className    = 'cert-field type-' + data.type + (data.id === selectedFieldId ? ' selected' : '');

        if (data.type === 'text') {
            div.style.fontFamily     = data.font_family || 'Helvetica';
            div.style.fontSize       = (data.font_size || 12) + 'pt';
            div.style.color          = data.text_color || '#000000';
            div.style.fontWeight     = (data.font_style || '').includes('B') ? 'bold'   : 'normal';
            div.style.fontStyle      = (data.font_style || '').includes('I') ? 'italic' : 'normal';
            div.style.justifyContent = data.alignment === 'L' ? 'flex-start' : (data.alignment === 'R' ? 'flex-end' : 'center');
            div.innerText            = fieldLabels[data.field_key] || ('[' + data.field_key + ']');

        } else if (data.type === 'rank_indicator' || data.type === 'gender_indicator') {
            // Build inner HTML: tick + label badge + drag handle
            // NONE of these elements are serialised into fieldsData or sent to the server.
            const isRank    = data.type === 'rank_indicator';
            const rankNames = {1:'1st Prize', 2:'2nd Prize', 3:'3rd Prize'};
            const label     = isRank
                ? (rankNames[data.rank] || ('Rank ' + data.rank)) + ' Tick'
                : (data.field_key === 'gender_check_male' ? 'Male Tick' : 'Female Tick');

            div.innerHTML = '';

            // ✓ tick mark centred in field
            const tick = document.createElement('span');
            tick.className   = 'field-tick-mark';
            tick.textContent = '\u2713';
            div.appendChild(tick);

            // Label badge — top-left inside the field
            const badge = document.createElement('div');
            badge.className   = 'field-editor-badge';
            badge.textContent = label;
            div.appendChild(badge);

            // Drag handle — always visible, bottom-right
            const handle = document.createElement('span');
            handle.className   = 'field-drag-handle';
            handle.textContent = '\u2630'; // ☰
            div.appendChild(handle);

        } else if (data.type === 'qr_code') {
            // Visual placeholder for the QR code field in the designer.
            // The actual QR code is generated at certificate-generation time.
            div.style.background = 'repeating-linear-gradient(45deg, #6366f1 0, #6366f1 2px, #eef2ff 2px, #eef2ff 10px)';
            div.style.border     = '2px solid #6366f1';
            div.style.display    = 'flex';
            div.style.flexDirection = 'column';
            div.style.alignItems = 'center';
            div.style.justifyContent = 'center';
            div.style.color      = '#312e81';
            div.style.fontSize   = '10px';
            div.style.fontWeight = '700';
            div.style.textAlign  = 'center';

            div.innerHTML = '<i class="bi bi-qr-code" style="font-size:1.6rem;display:block;margin-bottom:2px"></i>QR Code\n(Verification)';
        }
    }

    function renderField(data) {
        const div = document.createElement('div');
        div.id = 'field-' + data.id;
        div.dataset.key = data.field_key;
        
        applyFieldStyles(div, data);
        
        div.addEventListener('mousedown', () => selectField(data.id));
        canvas.appendChild(div);
        
        interact(div)
            .draggable({
                listeners: {
                    start(event) { saveState(); },
                    move(event) {
                        data.x_pt += (event.dx / zoomLevel);
                        data.y_pt += (event.dy / zoomLevel);
                        
                        // Enforce boundaries
                        data.x_pt = Math.max(0, Math.min(data.x_pt, PDF_WIDTH_PT - data.width_pt));
                        data.y_pt = Math.max(0, Math.min(data.y_pt, PDF_HEIGHT_PT - data.height_pt));
                        
                        applyFieldStyles(event.target, data);
                        checkOverlaps();
                        if (selectedFieldId === data.id) updatePropsPanel(data);
                    },
                    end(event) { isDirty = true; updateDirtyState(); }
                }
            })
            .resizable({
                edges: { left: true, right: true, bottom: true, top: true },
                listeners: {
                    start(event) { saveState(); },
                    move(event) {
                        data.width_pt += (event.deltaRect.width / zoomLevel);
                        data.height_pt += (event.deltaRect.height / zoomLevel);
                        data.x_pt += (event.deltaRect.left / zoomLevel);
                        data.y_pt += (event.deltaRect.top / zoomLevel);

                        data.width_pt = Math.max(10, data.width_pt);
                        data.height_pt = Math.max(10, data.height_pt);

                        // QR code must be square: lock height to width
                        if (data.type === 'qr_code') {
                            const side = Math.max(57, Math.max(data.width_pt, data.height_pt));
                            data.width_pt  = side;
                            data.height_pt = side;
                        }

                        // Enforce boundaries
                        if (data.x_pt < 0) { data.width_pt += data.x_pt; data.x_pt = 0; }
                        if (data.y_pt < 0) { data.height_pt += data.y_pt; data.y_pt = 0; }
                        if (data.x_pt + data.width_pt > PDF_WIDTH_PT) { data.width_pt = PDF_WIDTH_PT - data.x_pt; }
                        if (data.y_pt + data.height_pt > PDF_HEIGHT_PT) { data.height_pt = PDF_HEIGHT_PT - data.y_pt; }
                        
                        applyFieldStyles(event.target, data);
                        checkOverlaps();
                        if (selectedFieldId === data.id) updatePropsPanel(data);
                    },
                    end(event) { isDirty = true; updateDirtyState(); }
                }
            });

        return div;
    }

    function addFieldToCanvas(key, paletteType) {
        if (fieldsData.some(f => f.field_key === key)) {
            showNotification('"' + (PALETTE_LABELS[key] || key) + '" is already on the canvas.', 'error');
            return;
        }

        // Determine type & default size based on palette category
        let type = paletteType || 'text';
        let rank = 0;
        let w = 150;
        let h = 30;
        
        if (type === 'rank_indicator') {
            rank = parseInt(key.replace('rank_check_', '')) || 0;
            w = 28; h = 28;   // minimum visible size — user can resize larger
        } else if (type === 'gender_indicator') {
            w = 28; h = 28;
        } else if (type === 'qr_code') {
            // QR must be square; minimum 57pt (~20mm) as enforced server-side
            if (fieldsData.some(f => f.type === 'qr_code')) {
                showNotification('Only one QR Verification Code field is allowed per template.', 'error');
                return;
            }
            w = 80; h = 80;   // 80pt default (~28mm) — scannable at typical certificate size
        }

        const pos = findValidPosition(w, h);
        if (!pos) {
            showNotification('No space available. Adjust existing fields and try again.', 'error');
            return;
        }

        saveState();

        const newField = {
            id: ++fieldCounter,
            field_key: key,
            type: type,
            rank: rank,
            x_pt: pos.x,
            y_pt: pos.y,
            width_pt: w,
            height_pt: h,
            font_family: 'Helvetica',
            font_size: 12,
            font_style: '',
            text_color: '#000000',
            alignment: 'C',
            auto_shrink: false,
            min_font_size: 8
        };
        fieldsData.push(newField);
        renderField(newField);
        selectField(newField.id);
        checkOverlaps();
    }

    document.querySelectorAll('.palette-item').forEach(item => {
        item.addEventListener('dblclick', function() {
            addFieldToCanvas(this.dataset.key, this.dataset.type || 'text');
        });
    });

    function selectField(id) {
        selectedFieldId = id;
        document.querySelectorAll('.cert-field').forEach(el => el.classList.remove('selected'));
        if (id) {
            const data = fieldsData.find(f => f.id === id);
            const el   = document.getElementById('field-' + id);
            if (el) { el.classList.add('selected'); applyFieldStyles(el, data); }
            document.getElementById('no-selection').style.display = 'none';
            document.getElementById('field-props').style.display  = 'block';
            if (data.type === 'rank_indicator') {
                document.getElementById('text-props-group').style.display = 'none';
                document.getElementById('field-type-info').style.display  = 'block';
                document.getElementById('field-type-label').textContent   = 'Rank Checkmark';
                const rn = {1:'1st Prize', 2:'2nd Prize', 3:'3rd Prize'};
                document.getElementById('field-type-meta').textContent    = 'Rank: ' + (rn[data.rank] || data.rank);
            } else if (data.type === 'gender_indicator') {
                document.getElementById('text-props-group').style.display = 'none';
                document.getElementById('field-type-info').style.display  = 'block';
                document.getElementById('field-type-label').textContent   = 'Gender Checkmark';
                document.getElementById('field-type-meta').textContent    = 'Gender: ' + (data.field_key === 'gender_check_male' ? 'Male' : 'Female');
            } else if (data.type === 'qr_code') {
                document.getElementById('text-props-group').style.display = 'none';
                document.getElementById('field-type-info').style.display  = 'block';
                document.getElementById('field-type-label').textContent   = 'QR Verification Code';
                document.getElementById('field-type-meta').textContent    = 'Always square. Resize using the W field below. H is auto-locked.';
            } else {
                document.getElementById('text-props-group').style.display = 'block';
                document.getElementById('field-type-info').style.display  = 'none';
            }
            updatePropsPanel(data);
        } else {
            document.getElementById('no-selection').style.display = 'block';
            document.getElementById('field-props').style.display  = 'none';
        }
        checkOverlaps();
    }
    
    canvas.addEventListener('mousedown', (e) => {
        if (e.target === canvas || e.target === canvasWrapper) selectField(null);
    });

    function updatePropsPanel(data) {
        document.getElementById('prop-id').value = data.id;
        document.getElementById('prop-x').value  = Math.round(data.x_pt);
        document.getElementById('prop-y').value  = Math.round(data.y_pt);
        document.getElementById('prop-w').value  = Math.round(data.width_pt);
        document.getElementById('prop-h').value  = Math.round(data.height_pt);
        document.getElementById('prop-shrink').checked = data.auto_shrink;
        
        if (data.type === 'text') {
            document.getElementById('prop-font').value = data.font_family;
            document.getElementById('prop-size').value = data.font_size;
            document.getElementById('prop-color').value = data.text_color;
            document.getElementById('align-' + data.alignment).checked = true;
            document.getElementById('prop-bold').checked = data.font_style.includes('B');
            document.getElementById('prop-italic').checked = data.font_style.includes('I');
        }
    }

    const reapplyStyle = () => {
        if (!selectedFieldId) return;
        const data = fieldsData.find(f => f.id === selectedFieldId);
        const el = document.getElementById('field-' + selectedFieldId);
        if (data && el) applyFieldStyles(el, data);
        checkOverlaps();
    };

    const bindInput = (id, key, type = 'value', parser = x => x) => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', () => {
            if (!selectedFieldId) return;
            saveState();
            const data = fieldsData.find(f => f.id === selectedFieldId);
            if (data) {
                let val = parser(el[type]);
                if (key === 'width_pt' || key === 'height_pt') {
                     val = Math.max(10, val);
                }
                data[key] = val;
                
                // Enforce boundaries
                if (data.x_pt + data.width_pt > PDF_WIDTH_PT) data.width_pt = PDF_WIDTH_PT - data.x_pt;
                if (data.y_pt + data.height_pt > PDF_HEIGHT_PT) data.height_pt = PDF_HEIGHT_PT - data.y_pt;
                if (id === 'prop-w') el.value = Math.round(data.width_pt);
                if (id === 'prop-h') el.value = Math.round(data.height_pt);

                reapplyStyle();
            }
        });
    };

    bindInput('prop-font',   'font_family');
    bindInput('prop-size',   'font_size',   'value', parseInt);
    bindInput('prop-color',  'text_color');
    bindInput('prop-x',      'x_pt',        'value', parseFloat);
    bindInput('prop-y',      'y_pt',        'value', parseFloat);
    bindInput('prop-w',      'width_pt',    'value', parseFloat);
    bindInput('prop-h',      'height_pt',   'value', parseFloat);
    bindInput('prop-shrink', 'auto_shrink', 'checked');

    ['L','C','R'].forEach(a => {
        const el = document.getElementById('align-' + a);
        if (el) {
            el.addEventListener('change', (e) => {
                if (e.target.checked && selectedFieldId) {
                    saveState();
                    fieldsData.find(f => f.id === selectedFieldId).alignment = a;
                    reapplyStyle();
                }
            });
        }
    });

    const updateStyle = () => {
        if (!selectedFieldId) return;
        saveState();
        const b = document.getElementById('prop-bold').checked;
        const i = document.getElementById('prop-italic').checked;
        let s = '';
        if (b) s += 'B';
        if (i) s += 'I';
        fieldsData.find(f => f.id === selectedFieldId).font_style = s;
        reapplyStyle();
    };
    
    document.getElementById('prop-bold')?.addEventListener('change', updateStyle);
    document.getElementById('prop-italic')?.addEventListener('change', updateStyle);

    document.getElementById('btn-delete-field')?.addEventListener('click', () => {
        if (!selectedFieldId) return;
        saveState();
        document.getElementById('field-' + selectedFieldId).remove();
        fieldsData = fieldsData.filter(f => f.id !== selectedFieldId);
        selectField(null);
    });

    // Preview: POST current unsaved fieldsData to the new designer/preview endpoint.
    // Checks Content-Type before deciding how to handle the response.
    // Designer tick transparency is editor-only CSS (opacity:0.55).
    // Final PDF tick is fully opaque black via drawCheckmark() — no transparency.
    document.getElementById('btn-preview')?.addEventListener('click', async () => {
        const btn      = document.getElementById('btn-preview');
        const origHtml = btn.innerHTML;
        btn.disabled   = true;
        btn.innerHTML  = '<i class="bi bi-arrow-repeat spin"></i> Generating...';
        const rank     = parseInt(document.getElementById('preview-rank').value, 10);
        const gender   = document.getElementById('preview-gender').value;
        // Strip editor-only 'id' from payload.
        // NOTE: server-side whitelistField() is the authoritative security boundary.
        const payload = fieldsData.map(f => { const o = {...f}; delete o.id; return o; });
        try {
            const resp = await fetch(BASE_URL + '/certificates/designer/preview', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    _token: CSRF_TOKEN, template_id: TEMPLATE_ID,
                    preview_rank: rank, preview_gender: gender, fields: payload,
                }),
            });
            const ct = resp.headers.get('content-type') || '';
            if (resp.ok && ct.includes('application/pdf')) {
                const blob = await resp.blob();
                const url  = URL.createObjectURL(blob);
                window.open(url, '_blank');
                setTimeout(() => URL.revokeObjectURL(url), 60000);
            } else {
                const err = await resp.json().catch(() => ({ message: 'Server error (HTTP ' + resp.status + ').' }));
                showNotification('Preview failed: ' + (err.message || resp.status), 'error');
            }
        } catch (e) {
            showNotification('Preview request failed. Check your network connection.', 'error');
        } finally {
            btn.disabled  = false;
            btn.innerHTML = origHtml;
        }
    });

    document.getElementById('btn-save')?.addEventListener('click', () => {
        const btn = document.getElementById('btn-save');
        btn.disabled  = true;
        btn.innerHTML = '<i class="bi bi-arrow-repeat spin"></i> Saving...';
        fetch(BASE_URL + '/certificates/designer/save', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                _token: CSRF_TOKEN, template_id: TEMPLATE_ID,
                fields: fieldsData.map(f => { const o = {...f}; delete o.id; return o; }),
            }),
        })
        .then(r => r.json())
        .then(res => {
            if (res.success) {
                isDirty = false;
                updateDirtyState();
                showNotification('Design saved successfully \u2713', 'success');
            } else {
                showNotification('Save failed: ' + (res.message || 'Unknown error.'), 'error');
            }
        })
        .catch(() => showNotification('Network error — could not save layout.', 'error'))
        .finally(() => { btn.disabled = false; btn.innerHTML = '<i class="bi bi-save"></i> Save Draft'; });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return;
        if ((e.key === 'Delete' || e.key === 'Backspace') && selectedFieldId) {
            e.preventDefault();
            saveState();
            document.getElementById('field-' + selectedFieldId)?.remove();
            fieldsData = fieldsData.filter(f => f.id !== selectedFieldId);
            selectField(null);
        }
        // Ctrl+Z — Undo
        if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
            e.preventDefault();
            if (undoStack.length > 0) {
                redoStack.push(JSON.stringify(fieldsData));
                fieldsData.forEach(f => document.getElementById('field-' + f.id)?.remove());
                fieldsData = JSON.parse(undoStack.pop());
                fieldsData.forEach(f => renderField(f));
                selectField(null); checkOverlaps(); isDirty = true; updateDirtyState();
            }
        }
        // Ctrl+Y or Ctrl+Shift+Z — Redo
        if (e.ctrlKey && (e.key === 'y' || (e.shiftKey && e.key === 'z'))) {
            e.preventDefault();
            if (redoStack.length > 0) {
                undoStack.push(JSON.stringify(fieldsData));
                fieldsData.forEach(f => document.getElementById('field-' + f.id)?.remove());
                fieldsData = JSON.parse(redoStack.pop());
                fieldsData.forEach(f => renderField(f));
                selectField(null); checkOverlaps(); isDirty = true; updateDirtyState();
            }
        }
    });

    // Initialise from saved config
    if (existingFields && existingFields.length) {
        existingFields.forEach(f => { f.id = ++fieldCounter; fieldsData.push(f); renderField(f); });
        checkOverlaps();
    }
    updateDirtyState();
});
</script>