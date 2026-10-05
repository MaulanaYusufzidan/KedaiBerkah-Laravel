import './bootstrap';

// Konfirmasi sebelum aksi yang menghapus data. Server tetap memvalidasi sendiri.
document.addEventListener('submit', (event) => {
    const message = event.target.dataset?.confirm;

    if (message && !window.confirm(message)) {
        event.preventDefault();
    }
});

// Pratinjau gambar sebelum diunggah. Validasi sebenarnya tetap dilakukan server.
document.addEventListener('change', (event) => {
    const input = event.target;
    const previewId = input.dataset?.imageInput;

    if (!previewId || !input.files?.length) {
        return;
    }

    const preview = document.getElementById(previewId);
    const file = input.files[0];

    if (preview && file.type.startsWith('image/')) {
        if (preview.dataset.objectUrl) {
            URL.revokeObjectURL(preview.dataset.objectUrl);
        }
        preview.dataset.objectUrl = URL.createObjectURL(file);
        preview.src = preview.dataset.objectUrl;
    }
});
