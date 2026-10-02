import './bootstrap';

// Konfirmasi sebelum aksi yang menghapus data. Server tetap memvalidasi sendiri.
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});
