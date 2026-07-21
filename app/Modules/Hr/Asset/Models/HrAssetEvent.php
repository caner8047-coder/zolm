<?php
namespace App\Modules\Hr\Asset\Models;
use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model;
class HrAssetEvent extends Model { use BelongsToLegalEntity; public $timestamps=false; protected $fillable=['legal_entity_id','asset_id','asset_assignment_id','event_type','from_status','to_status','note','metadata','acted_by','created_at']; protected function casts():array{return ['metadata'=>'array','created_at'=>'datetime'];} }
