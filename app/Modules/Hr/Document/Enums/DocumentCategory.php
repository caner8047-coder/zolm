<?php

namespace App\Modules\Hr\Document\Enums;

enum DocumentCategory: string
{
    case Identity = 'identity';
    case Contract = 'contract';
    case Education = 'education';
    case Residence = 'residence';
    case CriminalRecord = 'criminal_record';
    case Health = 'health';
    case Certificate = 'certificate';
    case Kvkk = 'kvkk';
    case OccupationalSafety = 'occupational_safety';
    case Payroll = 'payroll';
    case Termination = 'termination';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Identity => 'Kimlik',
            self::Contract => 'Sözleşme',
            self::Education => 'Eğitim',
            self::Residence => 'İkamet',
            self::CriminalRecord => 'Sabıka Kaydı',
            self::Health => 'Sağlık',
            self::Certificate => 'Sertifika',
            self::Kvkk => 'KVKK',
            self::OccupationalSafety => 'İSG',
            self::Payroll => 'Bordro',
            self::Termination => 'İşten Çıkış',
            self::Other => 'Diğer',
        };
    }
}
