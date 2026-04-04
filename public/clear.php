<?php

// Zolm Cache Clear & Diagnostic Tool
// Bu dosyayı CWP'de public/ (veya public_html/production/) ana dizininize yükleyin ve tarayıcınızdan /clear.php olarak çağırın.

$app_path = __DIR__ . '/../'; // Varsayılan public/ dizininde olduğunu varsayıyoruz

echo "<h1>Zolm Sunucu Hata Ayıklama ve Önbellek Temizleme (v0.6)</h1>";

// 1. Vendor kontrolü
if (!file_exists($app_path . 'vendor/autoload.php')) {
    echo "<h2 style='color:red;'>🚨 KRİTİK HATA: 'vendor' klasörü bulunamadı!</h2>";
    echo "<p>Sunucuda PHP paketleri (vendor klasörü) eksik. Lütfen Mac cihazınızdaki güncel <b>vendor</b> klasörünü .zip yapıp sunucudaki proje ana dizinine yükleyerek çıkartın.</p>";
    exit;
}

require $app_path . 'vendor/autoload.php';
$app = require_once $app_path . 'bootstrap/app.php';

try {
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);

    echo "<h2>1. Önbellek (Cache) Temizleme</h2><ul>";
    
    $commands = ['optimize:clear', 'config:clear', 'cache:clear', 'view:clear', 'route:clear'];
    
    foreach ($commands as $command) {
        $kernel->call($command);
        echo "<li><b style='color:green;'>Başarılı:</b> php artisan $command çalıştırıldı.</li>";
    }
    echo "</ul>";

    echo "<p style='color:green; font-weight:bold;'>✅ Önbellekler tamamen temizlendi! <a href='./'>Siteye Dön</a></p>";

    echo "<hr><h2>2. Son Hata Kayıtları (storage/logs/laravel.log)</h2>";
    $log_path = $app_path . 'storage/logs/laravel.log';
    
    if (file_exists($log_path)) {
        // Log dosyasının son 20 satırını oku
        $lines = file($log_path);
        $last_lines = array_slice($lines, -30);
        
        echo "<pre style='background:#1e1e1e; color:#0f0; padding:15px; overflow-x:auto; font-size:12px;'>";
        foreach ($last_lines as $line) {
            echo htmlspecialchars($line);
        }
        echo "</pre>";
    } else {
        echo "<p>Kayıtlı hiçbir hata logu bulunamadı.</p>";
    }

} catch (\Exception $e) {
    echo "<h2 style='color:red;'>Script Çalışırken Sorun Oluştu:</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
}
