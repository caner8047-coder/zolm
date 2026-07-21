import './bootstrap';

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/hr-sw.js').catch(() => {
            // PWA kurulumu başarısız olsa bile ana uygulama çalışmaya devam eder.
        });
    });
}
