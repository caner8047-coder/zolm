<?php
namespace App\Modules\Hr\Performance\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model;
class HrPerformanceTemplate extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','name','version','sections','is_active','created_by']; protected function casts():array{return ['sections'=>'array','version'=>'integer','is_active'=>'boolean'];} }
