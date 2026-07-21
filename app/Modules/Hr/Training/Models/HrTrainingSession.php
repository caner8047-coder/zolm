<?php
namespace App\Modules\Hr\Training\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo; use Illuminate\Database\Eloquent\Relations\HasMany;
class HrTrainingSession extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','course_id','delivery_type','instructor','location','starts_at','ends_at','capacity','status','created_by']; protected function casts():array{return ['starts_at'=>'datetime','ends_at'=>'datetime','capacity'=>'integer'];} public function course():BelongsTo{return $this->belongsTo(HrTrainingCourse::class,'course_id');} public function enrollments():HasMany{return $this->hasMany(HrTrainingEnrollment::class,'session_id');} }
