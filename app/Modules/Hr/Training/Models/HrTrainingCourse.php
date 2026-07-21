<?php
namespace App\Modules\Hr\Training\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class HrTrainingCourse extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','code','title','category','description','duration_minutes','passing_score','certificate_validity_months','is_mandatory','is_active','created_by']; protected function casts():array{return ['duration_minutes'=>'integer','passing_score'=>'decimal:2','certificate_validity_months'=>'integer','is_mandatory'=>'boolean','is_active'=>'boolean'];} public function sessions():HasMany{return $this->hasMany(HrTrainingSession::class,'course_id');} }
