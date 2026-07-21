<?php
namespace App\Modules\Hr\Asset\Enums;
enum AssetStatus:string { case Available='available'; case Assigned='assigned'; case Maintenance='maintenance'; case Retired='retired'; case Lost='lost'; public function label():string{return match($this){self::Available=>'Hazır',self::Assigned=>'Zimmetli',self::Maintenance=>'Bakımda',self::Retired=>'Kullanım dışı',self::Lost=>'Kayıp'};} }
