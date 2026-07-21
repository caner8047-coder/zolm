<?php
namespace App\Modules\Hr\Performance\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model;
class HrCompetency extends Model { use BelongsToLegalEntity; protected $fillable=['legal_entity_id','code','name','category','description','is_active']; protected function casts():array{return ['is_active'=>'boolean'];} }
