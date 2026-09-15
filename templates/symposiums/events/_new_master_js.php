<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Auto Event Code Generation on Event Name input
    const nameInput = document.getElementById('event_name');
    const codeInput = document.getElementById('event_code');

    let debounceTimer;
    nameInput.addEventListener('input', function() {
        clearTimeout(debounceTimer);
        const nameVal = this.value.trim();
        if (nameVal.length === 0) {
            codeInput.value = '';
            return;
        }

        debounceTimer = setTimeout(() => {
            fetch('<?= base_url() ?>/admin/events/ajax/generate-code?name=' + encodeURIComponent(nameVal))
                .then(res => res.json())
                .then(data => {
                    if (data.code) {
                        codeInput.value = data.code;
                    }
                })
                .catch(err => console.error('Error generating code:', err));
        }, 300);
    });

    // 2. Participation Type Toggle (Individual vs Team)
    const participationSelect = document.getElementById('participation_type');
    const teamSizeSection = document.getElementById('team_size_section');

    function toggleTeamSection() {
        const isTeam = participationSelect.value !== 'Individual';
        teamSizeSection.style.display = isTeam ? 'block' : 'none';
    }
    participationSelect.addEventListener('change', toggleTeamSection);
    toggleTeamSection();

    // Duration Mode Toggle
    const durModeMinutes = document.getElementById('dur_mode_minutes');
    const durModeSlot    = document.getElementById('dur_mode_slot');
    const durRadios      = document.querySelectorAll('input[name="master[duration_type]"]');

    function toggleDurMode() {
        const isSlot = document.getElementById('dur_slot').checked;
        durModeMinutes.style.display = isSlot ? 'none' : 'block';
        durModeSlot.style.display    = isSlot ? 'block' : 'none';
        if (isSlot && typeof updateDurationPreview === 'function') updateDurationPreview();
    }
    durRadios.forEach(r => r.addEventListener('change', toggleDurMode));

    // Time Slot Live Preview
    const startInput = document.querySelector('input[name="master[start_time]"]');
    const endInput   = document.querySelector('input[name="master[end_time]"]');
    const daysInput  = document.querySelector('input[name="master[duration_days]"]');
    const preview    = document.getElementById('duration_preview');

    function updateDurationPreview() {
        if (!startInput || !endInput || !daysInput || !preview) return;
        const s = startInput.value, e = endInput.value, d = parseInt(daysInput.value) || 1;
        
        if (s && e) {
            const fmt = t => { const [h,m] = t.split(':'); const hr = +h; return (hr%12||12)+':'+m+' '+(hr<12?'AM':'PM'); };
            const [sh,sm] = s.split(':').map(Number);
            const [eh,em] = e.split(':').map(Number);
            const totalMins = ((eh * 60 + em) - (sh * 60 + sm)) * d;
            const dayLabel = d > 1 ? `${d} Days` : '1 Day';
            const minBadge = totalMins > 0 ? ` &nbsp;<span class="badge bg-secondary">${totalMins} mins total</span>` : '';
            preview.style.display = 'block';
            preview.innerHTML = `<i class="bi bi-clock text-primary me-2"></i><strong>${fmt(s)} &ndash; ${fmt(e)}</strong> &nbsp;&middot;&nbsp; <span class="badge bg-primary">${dayLabel}</span>${minBadge}`;
        } else {
            preview.style.display = 'none';
            preview.innerHTML = '';
        }
    }
    if (startInput) startInput.addEventListener('input', updateDurationPreview);
    if (endInput)   endInput.addEventListener('input', updateDurationPreview);
    if (daysInput)  daysInput.addEventListener('input', updateDurationPreview);
    updateDurationPreview();

    // 3. Prelims Toggle - Removed as no longer needed

    // 4. Registration Cascade Rule (Registration OFF => Attendance, Eval, Cert OFF)
    const flagReg = document.getElementById('flag_reg');
    const flagAtt = document.getElementById('flag_att');
    const flagEval = document.getElementById('flag_eval');
    const flagCert = document.getElementById('flag_cert');

    flagReg.addEventListener('change', function() {
        if (!this.checked) {
            flagAtt.checked = false;
            flagEval.checked = false;
            flagCert.checked = false;
            flagAtt.disabled = true;
            flagEval.disabled = true;
            flagCert.disabled = true;
        } else {
            flagAtt.disabled = false;
            flagEval.disabled = false;
            flagCert.disabled = false;
        }
    });

    // 5. Word-Style Rules Editor Tab Key Indentation & Auto-Bullet Continuation
    const rulesEditor = document.getElementById('rules_text_editor');
    if (rulesEditor) {
        rulesEditor.addEventListener('keydown', function(e) {
            const start = this.selectionStart;
            const end = this.selectionEnd;
            const value = this.value;

            // Handle Rich Text Formatting Shortcuts (Ctrl+B, Ctrl+I, Ctrl+U)
            if (e.ctrlKey) {
                const char = e.key.toLowerCase();
                let tag = null;
                if (char === 'b') tag = 'b';
                else if (char === 'i') tag = 'i';
                else if (char === 'u') tag = 'u';

                if (tag) {
                    e.preventDefault();
                    const selectedText = value.substring(start, end);
                    const wrappedText = `<${tag}>${selectedText}</${tag}>`;
                    this.value = value.substring(0, start) + wrappedText + value.substring(end);
                    // Move cursor inside the tags if no text was selected, otherwise after
                    const newCursorPos = start === end ? start + tag.length + 2 : start + wrappedText.length;
                    this.selectionStart = this.selectionEnd = newCursorPos;
                    return;
                }
            }

            // Handle Tab & Shift+Tab
            if (e.key === 'Tab') {
                e.preventDefault();
                indentText(!e.shiftKey);
                return;
            }

            // Handle Enter Key for Auto-Bullet Continuation
            if (e.key === 'Enter') {
                const lineStart = value.lastIndexOf('\n', start - 1) + 1;
                const currentLine = value.substring(lineStart, start);

                const bulletMatch = currentLine.match(/^(\s*)([•▪➢\-*]|\d+\.|\w\.)\s+/);
                if (bulletMatch) {
                    e.preventDefault();
                    let prefix = bulletMatch[0];
                    const numMatch = bulletMatch[2].match(/^(\d+)\.$/);
                    if (numMatch) {
                        const nextNum = parseInt(numMatch[1], 10) + 1;
                        prefix = bulletMatch[1] + nextNum + '. ';
                    }
                    this.value = value.substring(0, start) + '\n' + prefix + value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 1 + prefix.length;
                }
            }
        });
    }
});

function indentText(isIndent = true) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;
    const indentStr = '    '; // 4 spaces for clean tab alignment

    if (start === end) {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        if (isIndent) {
            editor.value = value.substring(0, start) + indentStr + value.substring(end);
            editor.selectionStart = editor.selectionEnd = start + indentStr.length;
        } else {
            const line = value.substring(lineStart, start);
            if (line.startsWith(indentStr)) {
                editor.value = value.substring(0, lineStart) + value.substring(lineStart + indentStr.length);
                editor.selectionStart = editor.selectionEnd = Math.max(lineStart, start - indentStr.length);
            } else if (line.startsWith('\t')) {
                editor.value = value.substring(0, lineStart) + value.substring(lineStart + 1);
                editor.selectionStart = editor.selectionEnd = Math.max(lineStart, start - 1);
            }
        }
    } else {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lineEnd = value.indexOf('\n', end);
        const realEnd = lineEnd === -1 ? value.length : lineEnd;
        const selectedLines = value.substring(lineStart, realEnd).split('\n');

        let newText = '';
        if (isIndent) {
            newText = selectedLines.map(line => indentStr + line).join('\n');
        } else {
            newText = selectedLines.map(line => {
                if (line.startsWith(indentStr)) return line.substring(indentStr.length);
                if (line.startsWith('\t')) return line.substring(1);
                return line;
            }).join('\n');
        }

        editor.value = value.substring(0, lineStart) + newText + value.substring(realEnd);
        editor.selectionStart = lineStart;
        editor.selectionEnd = lineStart + newText.length;
    }
    editor.focus();
}

function insertBullet(bullet) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;

    const selectedText = value.substring(start, end);
    if (selectedText.length > 0) {
        const lines = selectedText.split('\n');
        const bulleted = lines.map(line => bullet + line.replace(/^[•▪➢\-*]\s+/, '')).join('\n');
        editor.value = value.substring(0, start) + bulleted + value.substring(end);
        editor.selectionStart = start;
        editor.selectionEnd = start + bulleted.length;
    } else {
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        editor.value = value.substring(0, lineStart) + bullet + value.substring(lineStart);
        editor.selectionStart = editor.selectionEnd = start + bullet.length;
    }
    editor.focus();
}

function applyFormat(style) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const end = editor.selectionEnd;
    const value = editor.value;
    const selectedText = value.substring(start, end) || 'text';

    let openTag = '', closeTag = '';
    switch(style) {
        case 'bold': openTag = '<b>'; closeTag = '</b>'; break;
        case 'italic': openTag = '<i>'; closeTag = '</i>'; break;
        case 'underline': openTag = '<u>'; closeTag = '</u>'; break;
        case 'strikethrough': openTag = '<s>'; closeTag = '</s>'; break;
    }

    const replacement = openTag + selectedText + closeTag;
    editor.value = value.substring(0, start) + replacement + value.substring(end);
    editor.selectionStart = start + openTag.length;
    editor.selectionEnd = start + openTag.length + selectedText.length;
    editor.focus();
}

function insertHeader(headerTitle) {
    const editor = document.getElementById('rules_text_editor');
    if (!editor) return;
    const start = editor.selectionStart;
    const value = editor.value;
    const headerText = `\n[${headerTitle}]\n`;
    
    editor.value = value.substring(0, start) + headerText + value.substring(start);
    editor.selectionStart = editor.selectionEnd = start + headerText.length;
    editor.focus();
}
</script>