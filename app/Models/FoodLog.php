<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class FoodLog extends Model { use BelongsToGym; protected $fillable=['member_id','consumed_at','meal_name','foods','notes','coach_visibility']; protected function casts(): array{return ['consumed_at'=>'datetime','coach_visibility'=>'boolean'];} public function member(){return $this->belongsTo(Member::class);} }
