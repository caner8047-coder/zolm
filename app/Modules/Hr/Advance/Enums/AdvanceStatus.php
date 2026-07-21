<?php
namespace App\Modules\Hr\Advance\Enums;
enum AdvanceStatus:string { case PendingManager='pending_manager'; case PendingHr='pending_hr'; case Approved='approved'; case Paid='paid'; case Settled='settled'; case Rejected='rejected'; case Cancelled='cancelled'; public function label():string{return match($this){self::PendingManager=>'Yönetici bekliyor',self::PendingHr=>'İK bekliyor',self::Approved=>'Onaylandı',self::Paid=>'Ödendi',self::Settled=>'Kapandı',self::Rejected=>'Reddedildi',self::Cancelled=>'İptal edildi'};} }
