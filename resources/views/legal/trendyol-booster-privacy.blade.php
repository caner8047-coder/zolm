<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ZOLM Trendyol Booster Companion — Gizlilik</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 text-slate-900 antialiased">
    <main class="flex min-h-screen flex-col p-4 sm:flex-row lg:p-6">
        <div class="mx-auto w-full max-w-4xl space-y-4 lg:space-y-6">
            <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                <p class="text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-500">ZOLM · Gizlilik</p>
                <h1 class="mt-2 text-xl font-bold text-slate-900 lg:text-2xl">Trendyol Booster Companion Gizlilik Politikası</h1>
                <p class="mt-2 text-sm text-slate-500">Son güncelleme: 22 Temmuz 2026</p>
                <p class="mt-4 text-sm leading-6 text-slate-600">Bu politika, ZOLM Trendyol Booster Companion Chrome eklentisinin hangi verileri hangi amaçla kullandığını açıklar.</p>
            </section>

            @foreach([
                ['Toplanan veriler', [
                    'Analiz başlatılan Trendyol liste, ürün ve mağaza sayfalarındaki görünür ürün, fiyat, stok, satıcı, puan, yorum, favori ve etkileşim verileri.',
                    'Trendyol Seller Panel fiyatlandırma, kampanya ve sipariş ekranlarında kârlılık hesabı için gereken görünür satır verileri.',
                    'ZOLM adresi, marj eşikleri, hizmet bedeli ve stopaj tercihi gibi eklenti ayarları.',
                    'Hızlı keşif alanındaki en fazla beş son arama; yalnız kolay yeniden kullanım için Chrome yerel depolamasında tutulur.',
                    'Kullanıcının başlattığı toplu karar kuyruğundaki en fazla 40 ürün bağlantısı ve işlem durumu, kuyruk tamamlanana veya kullanıcı temizleyene kadar Chrome yerel depolamasında tutulur.',
                    'Ürün medya merkezinde kullanıcının seçtiği Trendyol görselleri, yalnız yerel indirme dosyasını hazırlamak için geçici olarak işlenir.',
                    'Kullanıcı Tedarikçi Radar işlemini başlattığında ürün kimliği güçlü eşleşen Google Alışveriş sonuçları.',
                ]],
                ['Kullanım amacı', [
                    'Ürün ve rakip araştırması, stok/fiyat takibi ve satış sinyali üretmek.',
                    'Kampanya, fiyatlandırma ve sipariş bazında tahmini veya kesinleşmiş kârı göstermek.',
                    'Kullanıcının başlattığı analizleri kendi ZOLM hesabına kaydetmek.',
                ]],
                ['Saklama ve güvenlik', [
                    'Eklenti tercihleri Chrome senkron depolamasında saklanır.',
                    'Son keşif aramaları popup içindeki “Geçmişi temizle” düğmesiyle silinebilir.',
                    'Toplu karar kuyruğu liste panelindeki “Kuyruğu temizle” düğmesiyle silinebilir.',
                    'Analiz kayıtları yalnız kullanıcının yapılandırdığı ZOLM hesabına gönderilir.',
                    'Trendyol parolası ve ödeme kartı bilgileri eklenti tarafından saklanmaz.',
                    'Genel tarama geçmişinin bir kopyası oluşturulmaz.',
                    'İndirilen ürün görselleri ZOLM sunucusuna gönderilmez ve eklenti depolamasında tutulmaz.',
                ]],
                ['Paylaşım ve satış', [
                    'Veriler reklam hedefleme, kredi değerliliği veya eklentinin temel işlevi dışındaki amaçlarla kullanılmaz.',
                    'Kişisel veriler ve analiz verileri üçüncü taraflara satılmaz.',
                ]],
                ['Silme ve iletişim', [
                    'Eklenti kaldırılarak tarayıcıda tutulan ayarların kaldırılması başlatılabilir.',
                    'ZOLM hesabındaki analiz kayıtları ilgili panelden silinebilir.',
                    'Ek talepler için ZOLM hesabında gösterilen destek kanalı veya '.config('mail.from.address').' adresi kullanılabilir.',
                ]],
            ] as [$title, $items])
                <section class="rounded-[10px] border border-slate-200 bg-white p-4 shadow-sm lg:p-6">
                    <h2 class="text-lg font-semibold text-slate-900">{{ $title }}</h2>
                    <ul class="mt-3 space-y-2 text-sm leading-6 text-slate-600">
                        @foreach($items as $item)
                            <li class="flex gap-3"><span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-[2px] bg-slate-400"></span><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </main>
</body>
</html>
