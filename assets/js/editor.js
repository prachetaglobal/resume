/* ── Resume Editor JS ─────────────────────────────────────── */

$(function () {

    let saveTimer = null;
    let isDirty   = false;

    // ── Helpers ──────────────────────────────────────────────

    function apiPost(url, data, cb) {
        data.csrf_token = CSRF_TOKEN;
        data.resume_id  = RESUME_ID;
        $.post(APP_URL + url, data, cb, 'json').fail(function () {
            setStatus('error', 'Save failed');
        });
    }

    function setStatus(state, msg) {
        const $s = $('#saveStatus');
        $s.removeClass('bg-secondary bg-warning bg-success bg-danger');
        if (state === 'saving') { $s.addClass('bg-warning').text('Saving…'); }
        else if (state === 'saved')  { $s.addClass('bg-success').text('Saved'); }
        else if (state === 'error')  { $s.addClass('bg-danger').text(msg || 'Error'); }
        else { $s.addClass('bg-secondary').text(msg || 'Saved'); }
    }

    function markDirty() {
        isDirty = true;
        setStatus('saving');
        clearTimeout(saveTimer);
        saveTimer = setTimeout(triggerAutoSave, 1500);
    }

    function triggerAutoSave() {
        if (!isDirty) return;
        // Collect all dirty fields and batch-save
        // Individual field saves happen on blur; this is a safety net
        isDirty = false;
        setStatus('saved');
    }

    function reloadPreview() {
        const iframe = document.getElementById('previewIframe');
        if (iframe) {
            iframe.src = iframe.src.split('?')[0] + '?id=' + RESUME_ID + '&embed=1&t=' + Date.now();
        }
    }

    // ── Resume Title ─────────────────────────────────────────

    let titleTimer = null;
    $('#resumeTitle').on('input', function () {
        clearTimeout(titleTimer);
        titleTimer = setTimeout(() => {
            apiPost('/api/resume.php', {action: 'save_title', title: $(this).val()}, function (r) {
                if (r.ok) setStatus('saved');
            });
        }, 800);
    });

    // ── Field inputs: save on blur ───────────────────────────

    $(document).on('blur', '.field-input', function () {
        const $el   = $(this);
        const itemId = $el.data('item-id');
        const key    = $el.data('key');
        const val    = $el.val();

        if (!itemId) return;

        setStatus('saving');
        apiPost('/api/resume.php', {
            action:  'save_fields',
            item_id: itemId,
            fields:  {[key]: val}
        }, function (r) {
            if (r.ok) {
                setStatus('saved');
                reloadPreview();
            }
        });
    });

    $(document).on('input', '.field-input', function () {
        markDirty();
    });

    // ── Section accordion toggle ─────────────────────────────

    $(document).on('click', '.section-header', function (e) {
        if ($(e.target).closest('.form-switch, .section-toggle').length) return;
        const $block = $(this).closest('.section-block');
        const $body  = $block.find('.section-body');
        $block.toggleClass('open');
        $body.slideToggle(180);
    });

    // ── Section visibility toggle ────────────────────────────

    $(document).on('change', '.section-toggle', function () {
        const sectionId = $(this).data('section-id');
        const visible   = this.checked ? 1 : 0;
        apiPost('/api/resume.php', {
            action: 'toggle_section', section_id: sectionId, visible: visible
        }, function (r) {
            if (r.ok) { setStatus('saved'); reloadPreview(); }
        });
    });

    // ── Add Item ─────────────────────────────────────────────

    $(document).on('click', '.add-item-btn', function (e) {
        e.preventDefault();
        const sectionId = $(this).data('section-id');
        const $list     = $(this).closest('.section-body').find('.items-list');
        const $btn      = $(this);

        apiPost('/api/resume.php', {action: 'add_item', section_id: sectionId}, function (r) {
            if (!r.ok) return;
            // Reload preview; form will show new item after page reload
            // For a nicer UX, insert a placeholder and let user type
            const type  = $list.closest('[data-type]').data('type') || 'custom';
            const newHtml = buildItemHtml(type, r.item_id);
            $list.append(newHtml);
            reloadPreview();
        });
    });

    function buildItemHtml(type, itemId) {
        if (type === 'experience') {
            return `<div class="item-block card mb-2 border-0 bg-light" data-item-id="${itemId}">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">Job Title</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="job_title" value=""></div>
                        <div class="col-md-6"><label class="form-label small">Company</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="company" value=""></div>
                        <div class="col-md-4"><label class="form-label small">Start Date</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="start_date" value=""></div>
                        <div class="col-md-4"><label class="form-label small">End Date</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="end_date" value=""></div>
                        <div class="col-md-4"><label class="form-label small">Location</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="location" value=""></div>
                        <div class="col-12"><label class="form-label small">Description</label>
                            <textarea class="form-control form-control-sm field-input" rows="3" data-item-id="${itemId}" data-key="description"></textarea></div>
                    </div>
                    <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="${itemId}">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                </div>
            </div>`;
        }
        if (type === 'education') {
            return `<div class="item-block card mb-2 border-0 bg-light" data-item-id="${itemId}">
                <div class="card-body p-2">
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">Degree</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="degree" value=""></div>
                        <div class="col-md-6"><label class="form-label small">Institution</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="institution" value=""></div>
                        <div class="col-md-4"><label class="form-label small">Start</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="start_date" value=""></div>
                        <div class="col-md-4"><label class="form-label small">End</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="end_date" value=""></div>
                        <div class="col-md-4"><label class="form-label small">GPA</label>
                            <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="gpa" value=""></div>
                    </div>
                    <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="${itemId}">
                        <i class="bi bi-trash me-1"></i>Remove
                    </button>
                </div>
            </div>`;
        }
        // Generic
        return `<div class="item-block card mb-2 border-0 bg-light" data-item-id="${itemId}">
            <div class="card-body p-2">
                <div class="row g-2">
                    <div class="col-md-8"><label class="form-label small">Title</label>
                        <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="title" value=""></div>
                    <div class="col-md-4"><label class="form-label small">Date</label>
                        <input type="text" class="form-control form-control-sm field-input" data-item-id="${itemId}" data-key="date" value=""></div>
                    <div class="col-12"><label class="form-label small">Description</label>
                        <textarea class="form-control form-control-sm field-input" rows="2" data-item-id="${itemId}" data-key="description"></textarea></div>
                </div>
                <button class="btn btn-xs btn-outline-danger mt-2 delete-item-btn" data-item-id="${itemId}">
                    <i class="bi bi-trash me-1"></i>Remove
                </button>
            </div>
        </div>`;
    }

    // ── Delete Item ──────────────────────────────────────────

    $(document).on('click', '.delete-item-btn', function () {
        const itemId = $(this).data('item-id');
        const $block = $(this).closest('.item-block');
        if (!confirm('Remove this entry?')) return;
        apiPost('/api/resume.php', {action: 'delete_item', item_id: itemId}, function (r) {
            if (r.ok) { $block.remove(); reloadPreview(); }
        });
    });

    // ── Template Switch ───────────────────────────────────────

    $(document).on('change', '.tpl-radio', function () {
        const templateId = $(this).val();
        document.querySelectorAll('.tpl-option').forEach(el => el.classList.remove('border-primary'));
        $(this).next('.tpl-option').addClass('border-primary');
        setStatus('saving');
        apiPost('/api/resume.php', {action: 'switch_template', template_id: templateId}, function (r) {
            if (r.ok) {
                setStatus('saved');
                // Reload template stylesheet and preview
                const iframe = document.getElementById('previewIframe');
                if (iframe) {
                    iframe.src = APP_URL + '/preview.php?id=' + RESUME_ID + '&embed=1&t=' + Date.now();
                }
            }
        });
    });

    // ── Add Section ───────────────────────────────────────────

    $(document).on('click', '.add-section-btn', function () {
        const type  = $(this).data('type');
        const label = $(this).data('label');
        bootstrap.Modal.getInstance(document.getElementById('addSectionModal'))?.hide();
        apiPost('/api/resume.php', {action: 'add_section', section_type: type, title: label}, function (r) {
            if (r.ok) location.reload();
        });
    });

    // ── Preview zoom ─────────────────────────────────────────

    function applyZoom(scale) {
        const iframe = document.getElementById('previewIframe');
        if (!iframe) return;
        iframe.style.transform = `scale(${scale})`;
        iframe.style.transformOrigin = 'top left';
        const w = Math.round(210 * 3.7795 * scale);
        const h = Math.round(297 * 3.7795 * scale);
        document.getElementById('previewFrame').style.width  = w + 'px';
        document.getElementById('previewFrame').style.height = h + 'px';
    }

    $('#zoomLevel').on('change', function () { applyZoom(parseFloat($(this).val())); });
    applyZoom(0.9);

    $('#previewDesktop').on('click', function () {
        $(this).addClass('active');
        $('#previewMobile').removeClass('active');
        applyZoom(parseFloat($('#zoomLevel').val()));
    });
    $('#previewMobile').on('click', function () {
        $(this).addClass('active');
        $('#previewDesktop').removeClass('active');
        const iframe = document.getElementById('previewIframe');
        if (iframe) {
            iframe.style.width  = '375px';
            iframe.style.height = '812px';
            iframe.style.transform = 'none';
            document.getElementById('previewFrame').style.cssText = 'width:375px;height:812px';
        }
    });

    // ── Auto-save every 30s ───────────────────────────────────
    setInterval(function () {
        if (isDirty) { triggerAutoSave(); }
    }, 30000);

});
