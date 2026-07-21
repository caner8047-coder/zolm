<?php
namespace App\Modules\Hr\Performance\Enums;
enum ReviewerType:string { case Self='self'; case Manager='manager'; case Peer='peer'; case DirectReport='direct_report'; case Hr='hr'; public function label():string{return match($this){self::Self=>'Öz değerlendirme',self::Manager=>'Yönetici',self::Peer=>'Çalışma arkadaşı',self::DirectReport=>'Bağlı çalışan',self::Hr=>'İK'};} }
