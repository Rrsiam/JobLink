document.addEventListener('DOMContentLoaded', function() {

    const uploadZone = document.getElementById('logo-upload-zone');
    const fileInput = document.getElementById('logo_upload');
    if (uploadZone && fileInput) {
        uploadZone.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            const f = fileInput.files[0];
            const nameEl = document.getElementById('logo-filename');
            if (nameEl) {
                nameEl.textContent = f ? 'Selected: ' + f.name : '';
            }
        });
    }
});