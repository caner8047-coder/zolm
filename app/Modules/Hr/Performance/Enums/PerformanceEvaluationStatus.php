<?php
namespace App\Modules\Hr\Performance\Enums;
enum PerformanceEvaluationStatus:string { case Draft='draft'; case Submitted='submitted'; case Calibrated='calibrated'; public function label():string{return match($this){self::Draft=>'Taslak',self::Submitted=>'Gönderildi',self::Calibrated=>'Kalibre edildi'};} }
