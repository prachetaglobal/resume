/* ── Global App JS ────────────────────────────────────────── */

// Auto-dismiss alerts after 4 seconds
document.querySelectorAll('.alert-dismissible').forEach(el => {
    setTimeout(() => {
        const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
        if (bsAlert) bsAlert.close();
    }, 4000);
});
