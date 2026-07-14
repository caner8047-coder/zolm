<?php

namespace App\Services\Support\Policy;

class SupportPolicyEngine
{
    public const VERSION = '2026.08.1';
    /**
     * Kanal bazlı policy profilleri (limitler ve yasaklar)
     */
    protected array $profiles = [
        'trendyol' => [
            'max_chars' => 4000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['n11', 'hepsiburada', 'amazon', 'kapida odeme', 'havale', 'iban', 'whatsapp'],
        ],
        'hepsiburada' => [
            'max_chars' => 2000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['n11', 'trendyol', 'amazon', 'kapida odeme', 'havale', 'iban', 'whatsapp'],
        ],
        'n11' => [
            'max_chars' => 2000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['trendyol', 'hepsiburada', 'amazon', 'kapida odeme', 'havale', 'iban', 'whatsapp'],
        ],
        'whatsapp' => [
            'max_chars' => 1000,
            'allow_links' => true,
            'allow_contacts' => true,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban'],
        ],
        'instagram' => [
            'max_chars' => 2000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban'],
        ],
        'instagram_comment' => [
            'max_chars' => 500,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban', 'siparis no', 'kargo no', 'takip no', 'adresiniz', 'telefonunuz'],
        ],
        'facebook' => [
            'max_chars' => 2000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban'],
        ],
        'facebook_comment' => [
            'max_chars' => 500,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban', 'siparis no', 'kargo no', 'takip no', 'adresiniz', 'telefonunuz'],
        ],
        'google_business' => [
            'max_chars' => 1000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => [
                'kapida odeme', 'havale', 'iban',
                'dm atin', 'direkt mesaj', 'whatsapp', 'mail atin', 'bize e-posta gonderin', 'e-posta gonderin',
                'hata bizde degil', 'suc bizde degil', 'sorumluluk bizde degil', 'kusur bizde degil'
            ],
        ],
        'web_chat' => [
            'max_chars' => 2000,
            'allow_links' => true,
            'allow_contacts' => true,
            'forbidden_keywords' => ['kapida odeme', 'havale', 'iban'],
        ]
    ];

    /**
     * Verilen kanala göre mesajı politikadan geçirir.
     */
    public function validate(string $message, string $channelKey): array
    {
        $result = $this->validateProfile($message, $channelKey);
        $result['version'] = self::VERSION;
        $result['validator_set'] = [
            'length', 'contact', 'external_link', 'forbidden_expression',
            'personal_data', 'definite_promise', 'health_legal_claim', 'template_placeholder',
        ];
        return $result;
    }

    protected function validateProfile(string $message, string $channelKey): array
    {
        $profile = $this->profiles[$channelKey] ?? [
            'max_chars' => 2000,
            'allow_links' => false,
            'allow_contacts' => false,
            'forbidden_keywords' => [],
        ];

        // 1. Maksimum Karakter Limiti
        if (mb_strlen($message) > $profile['max_chars']) {
            return [
                'allowed' => false,
                'reason' => "Karakter limiti aşıldı. Maksimum limit: {$profile['max_chars']} karakter (Mevcut: " . mb_strlen($message) . ")."
            ];
        }

        // 2. Telefon ve E-posta Paylaşımı
        if (!$profile['allow_contacts']) {
            // E-posta regex
            if (preg_match('/[a-zA-Z0-9_\-\.]+@[a-zA-Z0-9_\-\.]+\.[a-zA-Z]{2,5}/', $message)) {
                return [
                    'allowed' => false,
                    'reason' => 'Kanal kuralları gereği e-posta adresi paylaşımı yasaktır.'
                ];
            }
            // Telefon regex (Basit Türkiye telefonları veya genel)
            if (preg_match('/(05[0-9]{2})[ -]*([0-9]{3})[ -]*([0-9]{2})[ -]*([0-9]{2})|(\+90[ -]*5[0-9]{2})[ -]*([0-9]{3})[ -]*([0-9]{2})[ -]*([0-9]{2})/', $message)) {
                return [
                    'allowed' => false,
                    'reason' => 'Kanal kuralları gereği telefon numarası paylaşımı yasaktır.'
                ];
            }
        }

        // 3. Dış Link Kontrolü
        if (!$profile['allow_links']) {
            // Link regex (http, https, www)
            if (preg_match('/https?:\/\/[^\s]+|www\.[^\s]+/', $message)) {
                return [
                    'allowed' => false,
                    'reason' => 'Kanal kuralları gereği harici link (URL) paylaşımı yasaktır.'
                ];
            }
        }

        $normalizedMessage = $this->normalizeText($message);

        // 4. Harici Kampanya / Ödeme / Yasaklı Kelimeler
        foreach ($profile['forbidden_keywords'] as $keyword) {
            $normalizedKeyword = $this->normalizeText($keyword);
            if (str_contains($normalizedMessage, $normalizedKeyword)) {
                return [
                    'allowed' => false,
                    'reason' => "Kanal kuralları gereği yasaklı ifade/yönlendirme tespit edildi: '{$keyword}'."
                ];
            }
        }

        // 5. Kişisel Veri (T.C. Kimlik)
        if (preg_match('/(?<!\d)(\d{11})(?!\d)/', $message)) {
            return [
                'allowed' => false,
                'reason' => 'Kişisel veri (T.C. Kimlik Numarası) paylaşımı yasaktır.'
            ];
        }

        // 6. Kesin Teslimat/İade/Para İadesi Vaadi
        $forbiddenPromises = ['kesinlikle iade', 'para iadesi yapıyoruz', 'kesin teslim', 'garanti teslim', 'yarin kapinizda', 'yarın kapınızda', 'kesinlikle gönderilecek'];
        foreach ($forbiddenPromises as $promise) {
            $normalizedPromise = $this->normalizeText($promise);
            if (str_contains($normalizedMessage, $normalizedPromise)) {
                return [
                    'allowed' => false,
                    'reason' => "Kanal kuralları gereği kesin teslimat/iade/para iadesi taahhüdü verilemez: '{$promise}'."
                ];
            }
        }

        // 7. Sağlık/hukuk gibi yüksek riskli kesin iddialar
        $highRiskClaims = [
            'kesin tedavi eder', 'doktor onaylıdır', 'yan etkisi yoktur',
            'hukuken garantidir', 'kesin dava kazanırsınız', 'yasal olarak kesin',
        ];
        foreach ($highRiskClaims as $claim) {
            if (str_contains($normalizedMessage, $this->normalizeText($claim))) {
                return [
                    'allowed' => false,
                    'reason' => "Doğrulanmamış sağlık/hukuk iddiası gönderilemez: '{$claim}'.",
                ];
            }
        }

        // 8. WhatsApp Template Placeholder Kontrolleri
        if ($channelKey === 'whatsapp') {
            // Eğer WhatsApp mesajı sihirli placeholder'lar veya template formatı içeriyorsa ({{1}}, {{2}} gibi)
            if (preg_match('/\{\{\d\}\}/', $message)) {
                return [
                    'allowed' => false,
                    'reason' => 'WhatsApp template parametreleri ({{1}} vb.) doldurulmadan gönderilemez.'
                ];
            }
        }

        return ['allowed' => true, 'reason' => null];
    }

    /**
     * Türkçe karakterleri normalize eder, küçük harfe dönüştürür ve çoklu boşlukları temizler.
     */
    protected function normalizeText(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $turkishChars = ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'â', 'î', 'û'];
        $englishChars = ['i', 'g', 'u', 's', 'o', 'c', 'a', 'i', 'u'];
        $text = str_replace($turkishChars, $englishChars, $text);

        // Çoklu boşlukları temizle
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
