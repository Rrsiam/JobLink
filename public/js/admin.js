document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-approve').forEach(btn => {
        btn.addEventListener('click', () => alert('Approved (demo)'));
    });
    document.querySelectorAll('.btn-suspend').forEach(btn => {
        btn.addEventListener('click', () => alert('Suspended (demo)'));
    });
    document.querySelector('#add-category')?.addEventListener('click', function() {
        const name = prompt('Enter category name:');
        if (name) alert(`Category "${name}" added (demo)`);
    });
});