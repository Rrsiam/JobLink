document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('[data-confirm]').forEach(link => {
        link.addEventListener('click', event => {
            if (!window.confirm(link.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
