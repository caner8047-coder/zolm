<?php
namespace App\Modules\Hr\Engagement\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use App\Modules\Hr\Personnel\Models\HrEmployee; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HrSurveyResponse extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','survey_id','employee_id','respondent_hash','answers','enps_score','submitted_at']; protected $hidden=['respondent_hash']; protected function casts():array{return ['answers'=>'array','enps_score'=>'integer','submitted_at'=>'datetime'];} public function survey():BelongsTo{return $this->belongsTo(HrSurvey::class,'survey_id');} public function employee():BelongsTo{return $this->belongsTo(HrEmployee::class);} }
