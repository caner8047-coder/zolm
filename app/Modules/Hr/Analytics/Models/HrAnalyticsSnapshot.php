<?php
namespace App\Modules\Hr\Analytics\Models;use App\Modules\Hr\Core\Traits\BelongsToLegalEntity;use Illuminate\Database\Eloquent\Model;
class HrAnalyticsSnapshot extends Model{use BelongsToLegalEntity;protected $fillable=['legal_entity_id','period_start','period_end','metrics','sources','source_hash','generated_by','generated_at'];protected function casts():array{return ['period_start'=>'date','period_end'=>'date','metrics'=>'array','sources'=>'array','generated_at'=>'datetime'];}}
