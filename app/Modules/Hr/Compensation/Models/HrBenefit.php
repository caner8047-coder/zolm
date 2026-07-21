<?php
namespace App\Modules\Hr\Compensation\Models;use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;use Illuminate\Database\Eloquent\Model;
class HrBenefit extends Model{use BelongsToLegalEntity;protected $fillable=['legal_entity_id','code','name','type','employer_cost_encrypted','currency','is_active'];protected $hidden=['employer_cost_encrypted'];protected function casts():array{return ['employer_cost_encrypted'=>'encrypted','is_active'=>'boolean'];}}
