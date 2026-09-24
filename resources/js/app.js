const toggleTheme = () => {
    const dark = document.documentElement.classList.toggle('dark');
    localStorage.theme = dark ? 'dark' : 'light';
};

const manifest = document.createElement('link'); manifest.rel = 'manifest'; manifest.href = '/manifest.webmanifest'; document.head.append(manifest);
if ('serviceWorker' in navigator) window.addEventListener('load', () => navigator.serviceWorker.register('/service-worker.js'));

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-theme-toggle]')) toggleTheme();
});

document.addEventListener('keydown', (event) => {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        const url = document.body.dataset.searchUrl;
        if (url) window.location.assign(url);
    }
});
import QRCode from 'qrcode';

document.querySelectorAll('[data-qr-value]').forEach((canvas) => {
    QRCode.toCanvas(canvas, canvas.dataset.qrValue, { width: 220, margin: 2, errorCorrectionLevel: 'M' });
});
