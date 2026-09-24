<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class ProgressPhoto extends Model { use BelongsToGym; protected $fillable=['member_id','uploaded_by','captured_on','view_type','path','mime_type','size_bytes','visibility','consented_at']; protected function casts():array{return ['captured_on'=>'date','consented_at'=>'datetime'];} public function member(){return $this->belongsTo(Member::class);} }
