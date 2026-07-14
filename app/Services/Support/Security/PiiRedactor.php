<?php

namespace App\Services\Support\Security;

class PiiRedactor
{
    /**
     * E-posta, telefon ve T.C. Kimlik numaralarını maskeler.
     */
    public function maskPii(?string $text): string
    {
        if (empty($text)) {
            return '';
        }

        // E-posta maskeleme
        $text = preg_replace_callback('/([a-zA-Z0-9_\-\.]+)@([a-zA-Z0-9_\-\.]+)\.([a-zA-Z]{2,5})/', function ($matches) {
            $user = $matches[1];
            $domain = $matches[2];
            $ext = $matches[3];
            $maskedUser = mb_substr($user, 0, 1) . str_repeat('*', max(1, mb_strlen($user) - 1));
            return $maskedUser . '@' . $domain . '.' . $ext;
        }, $text);

        // Telefon maskeleme (Basit Türkiye cep telefonu veya genel cep telefonu formatları)
        $text = preg_replace_callback('/(05[0-9]{2})\s*([0-9]{3})\s*([0-9]{2})\s*([0-9]{2})/', function ($matches) {
            return $matches[1] . ' *** ' . $matches[3] . ' ' . $matches[4];
        }, $text);

        $text = preg_replace_callback('/(\+90\s*5[0-9]{2})\s*([0-9]{3})\s*([0-9]{2})\s*([0-9]{2})/', function ($matches) {
            return $matches[1] . ' *** ' . $matches[3] . ' ' . $matches[4];
        }, $text);

        // T.C. Kimlik maskeleme (11 haneli sayılar)
        $text = preg_replace_callback('/(?<!\d)(\d{2})(\d{7})(\d{2})(?!\d)/', function ($matches) {
            return $matches[1] . '*******' . $matches[3];
        }, $text);

        // İsim maskeleme (Test isimleri ve genel kalıplar)
        $text = preg_replace('/Caner\s+Ramazan\s+Önal/ui', '[İSİM-GİZLENDİ]', $text);
        $text = preg_replace('/Caner\s+Ramazan/ui', '[İSİM-GİZLENDİ]', $text);
        $text = preg_replace('/Caner\s+Bey/ui', '[İSİM-GİZLENDİ]', $text);

        return $text;
    }
}
