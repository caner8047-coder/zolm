<?php
namespace App\Modules\Hr\Asset\Enums;
enum AssetAssignmentStatus:string { case Assigned='assigned'; case Returned='returned'; case Lost='lost'; public function label():string{return match($this){self::Assigned=>'Zimmetli',self::Returned=>'İade edildi',self::Lost=>'Kayıp'};} }
