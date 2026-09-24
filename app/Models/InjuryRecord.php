<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class InjuryRecord extends Model { use BelongsToGym; protected $fillable=['member_id','recorded_by','body_area','title','severity','occurred_on','expected_recovery_on','restrictions','notes','status','training_coach_visibility','consented_at']; protected function casts():array{return ['occurred_on'=>'date','expected_recovery_on'=>'date','training_coach_visibility'=>'boolean','consented_at'=>'datetime'];} }
