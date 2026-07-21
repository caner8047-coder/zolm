<?php
namespace App\Modules\Hr\Performance\Enums;
enum PerformanceCycleStatus:string { case Draft='draft'; case Active='active'; case Evaluation='evaluation'; case Calibration='calibration'; case Closed='closed'; public function label():string{return match($this){self::Draft=>'Taslak',self::Active=>'Hedef dönemi',self::Evaluation=>'Değerlendirme',self::Calibration=>'Kalibrasyon',self::Closed=>'Kapandı'};} }
