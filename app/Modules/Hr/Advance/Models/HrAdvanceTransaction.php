<?php
namespace App\Modules\Hr\Advance\Models;
use App\Modules\Hr\Advance\Enums\AdvanceTransactionType; use App\Modules\Hr\Core\Traits\BelongsToLegalEntity; use Illuminate\Database\Eloquent\Model; use Illuminate\Database\Eloquent\Relations\BelongsTo;
class HrAdvanceTransaction extends Model { use BelongsToLegalEntity; public $timestamps=false; protected $fillable=['legal_entity_id','advance_id','type','amount','transaction_date','reference','note','source_key','payload_hash','created_by','created_at']; protected function casts():array{return ['type'=>AdvanceTransactionType::class,'amount'=>'decimal:2','transaction_date'=>'date','created_at'=>'datetime'];} public function advance():BelongsTo{return $this->belongsTo(HrAdvance::class,'advance_id');} }
