<?php
namespace App\Modules\Hr\Engagement\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\HasMany;
class HrSurvey extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','title','description','questions','starts_on','ends_on','status','is_anonymous','minimum_report_count','created_by']; protected function casts():array{return ['questions'=>'array','starts_on'=>'date','ends_on'=>'date','is_anonymous'=>'boolean','minimum_report_count'=>'integer'];} public function responses():HasMany{return $this->hasMany(HrSurveyResponse::class,'survey_id');} }
