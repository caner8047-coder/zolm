<?php
namespace App\Modules\Hr\Engagement\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use App\Modules\Hr\Personnel\Models\HrEmployee; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HrRecognition extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','sender_employee_id','recipient_employee_id','category','message','is_public','recognized_at']; protected function casts():array{return ['is_public'=>'boolean','recognized_at'=>'datetime'];} public function sender():BelongsTo{return $this->belongsTo(HrEmployee::class,'sender_employee_id');} public function recipient():BelongsTo{return $this->belongsTo(HrEmployee::class,'recipient_employee_id');} }
