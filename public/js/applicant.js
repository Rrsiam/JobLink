document.addEventListener('DOMContentLoaded', function() {
    const upload = document.querySelector('#resume-upload');
    const drop = document.querySelector('#resume-drop');
    const hint = document.querySelector('#file-name');
    const MAX_BYTES = 5 * 1024 * 1024;
    const ACCEPTED = ['pdf', 'doc', 'docx'];

    const describe = file => {
        if (!file) return 'No file selected.';
        const mb = (file.size / 1048576).toFixed(1);
        return `Selected: ${file.name} (${mb} MB)`;
    };

    if (upload) {
        upload.addEventListener('change', function() {
            const file = this.files && this.files[0];
            if (hint) hint.textContent = describe(file);

            if (file) {
                const ext = (file.name.split('.').pop() || '').toLowerCase();
                if (ACCEPTED.indexOf(ext) === -1) {
                    upload.value = '';
                    if (hint) hint.textContent = 'Unsupported format. Use PDF, DOC or DOCX.';
                } else if (file.size > MAX_BYTES) {
                    upload.value = '';
                    if (hint) hint.textContent = 'File is larger than 5 MB.';
                }
            }
        });
    }

    // Drag and drop support for the resume drop zone.
    if (drop && upload) {
        ['dragenter', 'dragover'].forEach(evt => {
            drop.addEventListener(evt, e => {
                e.preventDefault();
                drop.classList.add('is-dragging');
            });
        });
        ['dragleave', 'drop'].forEach(evt => {
            drop.addEventListener(evt, e => {
                e.preventDefault();
                drop.classList.remove('is-dragging');
            });
        });
        drop.addEventListener('drop', e => {
            const file = e.dataTransfer && e.dataTransfer.files[0];
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            upload.files = dt.files;
            upload.dispatchEvent(new Event('change'));
        });
    }
});