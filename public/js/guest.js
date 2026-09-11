document.addEventListener('DOMContentLoaded', function() {
    const searchBtn = document.querySelector('.search-bar button');
    if (searchBtn) {
        searchBtn.addEventListener('click', function(e) {
            e.preventDefault();
            const inputs = document.querySelectorAll('.search-bar input');
            alert(`Searching for "${inputs[0]?.value}" in "${inputs[1]?.value}" (demo)`);
        });
    }

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