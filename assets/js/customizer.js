/* ── Style Customizer JS ──────────────────────────────────── */

$(function () {

    let customizeTimer = null;

    function saveCustomization() {
        clearTimeout(customizeTimer);
        customizeTimer = setTimeout(function () {
            const data = {
                csrf_token:       CSRF_TOKEN,
                resume_id:        RESUME_ID,
                primary_color:    $('#primaryColor').val(),
                accent_color:     $('#accentColor').val(),
                font_heading:     $('#fontHeading').val(),
                font_body:        $('#fontBody').val(),
                font_size_heading:$('#fontSizeHeading').val(),
                font_size_body:   $('#fontSizeBody').val(),
                line_height:      (parseInt($('#lineHeight').val()) / 10).toFixed(1),
                section_spacing:  $('#sectionSpacing').val(),
            };
            $.post(APP_URL + '/api/customization.php', data, function (r) {
                if (r.ok) {
                    $('#saveStatus').removeClass('bg-warning').addClass('bg-success').text('Saved');
                    reloadPreviewStyles();
                }
            }, 'json');
        }, 600);
    }

    function reloadPreviewStyles() {
        const iframe = document.getElementById('previewIframe');
        if (iframe) {
            iframe.src = APP_URL + '/preview.php?id=' + RESUME_ID + '&embed=1&t=' + Date.now();
        }
    }

    // Live range label updates
    $('#fontSizeHeading').on('input', function () {
        $('#fontSizeHVal').text($(this).val() + 'px');
        saveCustomization();
    });
    $('#fontSizeBody').on('input', function () {
        $('#fontSizeBVal').text($(this).val() + 'px');
        saveCustomization();
    });
    $('#sectionSpacing').on('input', function () {
        $('#sectionSpacingVal').text($(this).val() + 'px');
        saveCustomization();
    });
    $('#lineHeight').on('input', function () {
        $('#lineHeightVal').text((parseInt($(this).val()) / 10).toFixed(1));
        saveCustomization();
    });

    // Color pickers
    $('#primaryColor, #accentColor').on('input', saveCustomization);

    // Font selectors
    $('#fontHeading, #fontBody').on('change', saveCustomization);

    // Theme presets
    $(document).on('click', '.theme-preset', function () {
        const primary = $(this).data('primary');
        const accent  = $(this).data('accent');
        $('#primaryColor').val(primary);
        $('#accentColor').val(accent);
        saveCustomization();
    });

});
