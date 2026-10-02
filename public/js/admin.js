document.addEventListener('DOMContentLoaded', function() {
    // Note buttons inside forms already confirm through their own onclick
    // handler; anything still marked data-confirm is confirmed here.
    document.querySelectorAll('a[data-confirm]').forEach(link => {
        link.addEventListener('click', event => {
            if (!window.confirm(link.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});
