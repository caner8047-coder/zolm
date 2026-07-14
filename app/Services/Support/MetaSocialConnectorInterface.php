<?php

namespace App\Services\Support;

interface MetaSocialConnectorInterface
{
    /**
     * Meta Social (Instagram/Facebook) üzerinden mesaj veya yorum yanıtı gönderir.
     *
     * @param string $key 'instagram' veya 'facebook'
     * @param string $threadId Alıcı konuşma/yorum ID'si
     * @param string $message Gönderilecek mesaj gövdesi
     * @return string Benzersiz dış platform mesaj/yorum yanıtı ID'si (channel_message_id)
     */
    public function send(string $key, string $threadId, string $message): string;
}
