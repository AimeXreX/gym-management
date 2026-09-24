<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class CoachRequest extends Model { use BelongsToGym; protected $fillable=['member_id','coach_id','domain','type','message','status','reviewed_by','reviewed_at','review_note']; protected function casts(): array{return ['reviewed_at'=>'datetime'];} public function member(){return $this->belongsTo(Member::class);} public function coach(){return $this->belongsTo(Coach::class);} public function reviewer(){return $this->belongsTo(User::class,'reviewed_by');} }
