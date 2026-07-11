# ZOLM & Parola Eşdeğerlik ve Ürünleşme Kabul Matrisi

Bu doküman, ZOLM Ön Muhasebe / ERP modülünün Parola'nın herkese açık web sitesinde vadettiği işlevlerle karşılaştırılması, teknik olgunluk dereceleri, bilinen eksik ve riskleri ile kabul durumlarını kayıt altına almak için hazırlanmıştır.

Referans kaynaklar:
- https://parola.com/
- https://parola.com/ozellikler/

Son kontrol tarihi: 2026-07-11

---

## 1. Parola Modül Eşdeğerlik Matrisi

| Parola Modülü | ZOLM Ekranı / Servisi | Durum | Eksik / Risk | Kabul Notu |
| :--- | :--- | :--- | :--- | :--- |
| **Ön Muhasebe Dashboard** | `Livewire\Accounting\AccountingDashboard` | Kabul Edildi | Yok | Kasa/Banka, Cari, Stok ve Satış KPI'ları demo veriyle dinamik ve tutarlı şekilde yükleniyor. |
| **Cariler** | `Livewire\Accounting\Parties` | Kabul Edildi | Yok | Rol (Müşteri/Tedarikçi), iletişim, vergi bilgileri ve net bakiye izleme mevcut. |
| **Stok** | `Livewire\Accounting\Stock` ve `Products` | Kabul Edildi | Yok | Depo bazlı stok yönetimi, giriş/çıkış hareketleri ve yetersiz stok çıkış engeli mevcut. |
| **Satışlar** | `Livewire\Accounting\Sales` | Kabul Edildi | Yok | Taslak/Onay/İptal akışı, İskonto, KDV, stok düşümü ve yevmiye entegrasyonu tamdır. |
| **Hızlı Satış / POS** | `Livewire\Accounting\Pos` | MVP Kabul | Donanım, fiş yazıcı, barkod okuyucu ve ödeme terminali entegrasyonu yok. | Web POS, vardiya, ödeme yöntemi, stok düşümü, tahsilat ve iptal akışı MVP seviyesinde çalışıyor. |
| **Tahsilat / Ödeme** | `Livewire\Accounting\CollectionsPayments` | Kabul Edildi | Yok | Fatura kapatma (receivable/payable allocation), tahsilat/ödeme eşleme ve void güvenliği tamdır. |
| **Satın Alma** | `Livewire\Accounting\Purchases` | Kabul Edildi | Yok | Tedarikçi siparişleri, otomatik stok girişi, cari borçlanma ve yevmiye entegrasyonu tamdır. |
| **Kasa & Banka** | `Livewire\Accounting\CashBank` | Kabul Edildi | Yok | Kasa ve Banka hesap yönetimi, hesap ekstre görünümleri mevcuttur. |
| **Virman** | `CashBankService::transferFunds` | Kabul Edildi | Yok | Hesaplar arası transfer, virman iptali (void) ve otomatik dengeli journal üretimi mevcuttur. |
| **e-Fatura / e-Arşiv** | `Livewire\Accounting\EDocuments` | MVP Kabul | Gerçek özel entegratör/GİB entegrasyonu yok; simüle provider ve sıralı numara akışı var. | Belge taslak, sıra numarası, simüle gönderim/kabul ve iptal akışı ürün demosu için yeterli; canlı e-belge entegrasyonu ayrı fazdır. |
| **Raporlar** | `Livewire\Accounting\Reports` | Kabul Edildi | PDF ve gelişmiş dışa aktarım kapsamı eksik, Excel çıktısı mevcut. | Yaşlandırma (receivables/payables aging), nakit akış, gelir/gider, stok değerleme ve yönetici özeti mevcuttur. PDF çıktısı sonraki fazdır. |
| **AI Asistan** | `Livewire\Accounting\Assistant` | MVP Kabul | Salt okunur/kural tabanlı; gerçek LLM, aksiyon taslağı ve onaylı işlem yürütme sonraki faz. | Finansal işlem yapması engellenmiş güvenli raporlama asistanı olarak kabul edildi. |
| **CRM 360 Entegrasyonu** | `Livewire\CrmCustomerLedger` | MVP Kabul | Derin CRM muhasebe aksiyonları sonraki faz. | Müşteri profilinde cari bakiye, KPI'lar ve Cari Açık Hesap ekranına güvenli yönlendirme mevcuttur. |
| **Pazaryeri Finans Köprüsü** | `Livewire\Accounting\MarketplaceBridge` | Kabul Edildi | Canlı pazaryeri entegrasyonları gerçek mağaza verisiyle ayrıca pilotlanmalı. | Trendyol vb. pazaryeri sipariş ve finansal hareketlerinin cariye/stoğa yansıtılması entegre şekilde çalışmaktadır. |

---

## 2. Ürün Kabul Kriterleri Doğrulaması

### 2.1. Demo Veri Kurulumu
Demo veriler `--reset` parametresiyle idempotent şekilde kurulabilmektedir. `SeedAccountingDemoCommand` komutu, gerçek kullanıcı verilerine ve yevmiye satırlarına dokunmadan sadece demo verileri sıfırlar ve temiz bir şekilde yeniden kurar.

### 2.2. Arayüz ve Mobil Responsive Uyumluluğu
ZOLM Kurumsal Açık Panel standartlarına uyum; kod standardı, Livewire smoke testleri ve sınıf kontrolleriyle temel seviyede doğrulanmıştır. Tam görsel kabul için ayrı browser/viewport QA turu önerilir.

---

## 3. Sonraki Faz Adayları

- Gerçek özel entegratör/GİB e-Fatura/e-Arşiv entegrasyonu
- POS donanım, fiş yazıcı, barkod okuyucu ve ödeme terminali entegrasyonları
- Cari ekstre PDF/Excel çıktı iyileştirmeleri
- Satış/alış iade süreçlerinin ürünleşmesi
- Gelişmiş rol/yetki matrisi: yönetici, muhasebe, kasiyer, satış
- AI Asistan için LLM destekli analiz ve güvenli aksiyon taslakları
- Browser/viewport tabanlı tam görsel QA
