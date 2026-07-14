<?php

namespace App\Services\Support;

interface GoogleBusinessConnectorInterface
{
    /**
     * Google Business Profile (Google Maps) yorumuna yanıt yayınlar.
     *
     * @param string $reviewId Yanıtlanacak yorumun benzersiz dış ID'si
     * @param string $message Gönderilecek yanıt gövdesi
     * @return string Benzersiz dış platform yanıt ID'si
     */
    public function reply(string $reviewId, string $message): string;
}
