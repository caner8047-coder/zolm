<?php

namespace App\Modules\Hr\Performance\Notifications;

use App\Modules\Hr\Performance\Models\HrPerformanceEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PerformanceEvaluationReminderNotification extends Notification
{
    use Queueable;

    public function __construct(private HrPerformanceEvaluation $evaluation) {}

    public function via(object $notifiable): array { return ['mail']; }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage())
            ->subject('Performans değerlendirmeniz bekliyor')
            ->greeting('Merhaba,')
            ->line($this->evaluation->cycle->name.' dönemindeki performans değerlendirmeniz henüz tamamlanmadı.')
            ->line('Son tarih: '.$this->evaluation->cycle->evaluation_ends_on->format('d.m.Y'))
            ->action('Değerlendirmeyi tamamla', route('hr.my-performance'))
            ->line('Bu bildirim ZOLM Performans tarafından gönderildi.');
    }
}
