<?php
namespace App\Modules\Hr\Compensation\Models;use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;use Illuminate\Database\Eloquent\Model;
class HrEmployeeBenefit extends Model{use BelongsToLegalEntity;protected $fillable=['legal_entity_id','employee_id','benefit_id','starts_on','ends_on','status','employee_contribution_encrypted','created_by'];protected $hidden=['employee_contribution_encrypted'];protected function casts():array{return ['employee_contribution_encrypted'=>'encrypted','starts_on'=>'date','ends_on'=>'date'];}}
