<?php

namespace App\Enums;

enum AdImportStatus: string
{
    case Uploaded = 'uploaded';
    case Parsing = 'parsing';
    case PreviewReady = 'preview_ready';
    case Imported = 'imported';
    case Failed = 'failed';
    case Duplicate = 'duplicate';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Yüklendi',
            self::Parsing => 'İşleniyor',
            self::PreviewReady => 'Önizleme Hazır',
            self::Imported => 'İçe Aktarıldı',
            self::Failed => 'Başarısız',
            self::Duplicate => 'Tekrar',
            self::Cancelled => 'İptal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Uploaded => 'bg-slate-100 text-slate-700',
            self::Parsing => 'bg-blue-100 text-blue-700',
            self::PreviewReady => 'bg-amber-100 text-amber-700',
            self::Imported => 'bg-emerald-100 text-emerald-700',
            self::Failed => 'bg-rose-100 text-rose-700',
            self::Duplicate => 'bg-purple-100 text-purple-700',
            self::Cancelled => 'bg-gray-100 text-gray-500',
        };
    }
}
