document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.action-shortlist').forEach(btn => {
        btn.addEventListener('click', () => alert('Shortlisted (demo)'));
    });
    document.querySelectorAll('.action-reject').forEach(btn => {
        btn.addEventListener('click', () => alert('Rejected (demo)'));
    });
});