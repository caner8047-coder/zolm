<?php
namespace App\Modules\Hr\Payroll\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model;
class HrPayrollRule extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','code','name','version','configuration','configuration_hash','effective_from','effective_until','is_active','status','created_by','approved_by','approved_at']; protected function casts():array{return ['version'=>'integer','configuration'=>'array','effective_from'=>'date','effective_until'=>'date','is_active'=>'boolean','approved_at'=>'datetime'];} }
