<?php
namespace App\Modules\Hr\Asset\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model;
class HrAssetCategory extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','code','name','is_active','created_by']; protected function casts():array{return ['is_active'=>'boolean'];} }
