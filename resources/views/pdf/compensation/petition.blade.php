<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Tazmin Dilekçesi - Port Kargo</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; line-height: 1.5; font-size: 11pt; }
        .header { font-weight: bold; margin-bottom: 30px; margin-top: 40px; }
        .subject { margin-bottom: 25px; font-weight: bold; }
        .content { margin-bottom: 30px; text-align: justify; }
        .footer { margin-top: 50px; }
        .signature-block { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="header">
        Port Kargo ve Lojistik A.Ş. Genel Müdürlügüne
    </div>

    <div class="subject">
        Konu: Kayıp gönderi nedeniyle tazmin talebi
    </div>

    <div class="content">
        @if(!empty($compensation->dilekce_icerigi))
            {!! nl2br(e($compensation->dilekce_icerigi)) !!}
        @else
            <p>Sayın Yetkili,</p>

            @if($compensation->sebep == 'kayip_urun' || $compensation->sebep == 'iade_kayip')
                <p>
                    Trendyol üzerinden müşterimize gönderdiğimiz ürün, Sürat Kargo aracılığıyla tarafımıza verilen 
                    <strong>{{ $compensation->takip_kodu }}</strong> numaralı kargo ile sevk edilmiştir. 
                    Ancak söz konusu gönderi, alıcı tarafından teslim alınmamıştır. Ürün, tarafımıza veya alıcı müşterimize teslim edilmemiştir. 
                    Ürünün taşıma durumunda kayıp olduğu tespit edilmiştir.
                </p>
                <p>
                    Bu nedenle, ilgili gönderinin ürün bedelinin tazmini hususunda gereğinin yapılmasını arz ederiz.
                </p>

            @elseif($compensation->sebep == 'hasarli_urun')
                <p>
                    Trendyol üzerinden müşterimize gönderdiğimiz ürün, Sürat Kargo aracılığıyla tarafımıza verilen 
                    <strong>{{ $compensation->takip_kodu }}</strong> numaralı kargo ile sevk edilmiştir. 
                    Söz konusu gönderinin taşıma esnasında hasar gördüğü, ürünün kullanılamaz/satılamaz hale geldiği tespit edilmiştir. 
                    Hasar tespit tutanağı ve ilgili görseller ekte sunulmuştur.
                </p>
                <p>
                    Bu nedenle, hasar gören ürün bedelinin tazmini hususunda gereğinin yapılmasını arz ederiz.
                </p>

            @elseif($compensation->sebep == 'desi_fazla')
                <p>
                    Sürat Kargo aracılığıyla gönderilen <strong>{{ $compensation->takip_kodu }}</strong> numaralı kargonun desi ölçümü hatalı yapılmıştır.
                    Sistemde faturalandırılan desi miktarı ile ürünün gerçek desi miktarı arasında fahiş fark bulunmaktadır.
                    Ürünün gerçek ölçüleri ve olması gereken desi bilgileri ekte sunulmuştur.
                </p>
                <p>
                    Hatalı ölçümden kaynaklanan fazla kargo ücretinin tarafımıza iade edilmesi hususunda gereğini arz ederiz.
                </p>

            @elseif($compensation->sebep == 'tutar_fazla')
                <p>
                    Sürat Kargo aracılığıyla gönderilen <strong>{{ $compensation->takip_kodu }}</strong> numaralı kargo için faturalandırılan tutar, 
                    anlaşma fiyatlarımıza ve gönderi desi/ağırlık bilgilerine uymamaktadır.
                    Hesaplanan tutar ile fatura edilen tutar arasında fark oluşmuştur.
                </p>
                <p>
                    Hatalı hesaplamadan kaynaklanan fiyat farkının tarafımıza iade edilmesi hususunda gereğini arz ederiz.
                </p>

            @else
                <p>
                    Şirketiniz aracılığıyla göndermiş olduğumuz <strong>{{ $compensation->takip_kodu }}</strong> numaralı kargo gönderisinde 
                    <strong>{{ $compensation->sebep_info['label'] }}</strong> durumu nedeniyle mağduriyet yaşamaktayız.
                    Konuyla ilgili detaylar ve belgeler ekte sunulmuştur.
                </p>
                <p>
                    Mağduriyetimizin giderilmesi ve söz konusu zararın tazmini hususunda gereğinin yapılmasını arz ederiz.
                </p>
            @endif

            <p>
                Bilgilerinize arz eder, gereğini rica ederiz.
            </p>
        @endif
    </div>

    <div class="footer">
        <p>Saygılarımla,</p>

        <div class="signature-block">
            <strong>Zem Dayanıklı Tüketim Malları İthalat İhracat Sanayi ve Ticaret Limited Şirketi</strong><br>
            Adres: Eskihisar Mah. 8018 Sk. No:5 İç Kapı No:1<br>
            Telefon: 0 507 298 40 85<br>
            E-posta: zemhomedestek@gmail.com<br>
            Tarih: {{ now()->format('d.m.Y') }}<br>
            <br>
            İmza
        </div>
    </div>
</body>
</html>
