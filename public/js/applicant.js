document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-apply').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            alert('Application submitted! (demo)');
        });
    });
    const upload = document.querySelector('#resume-upload');
    if (upload) {
        upload.addEventListener('change', function() {
            alert(`File "${this.files[0]?.name}" selected.`);
        });
    }
});