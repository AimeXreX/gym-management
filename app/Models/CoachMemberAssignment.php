<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class CoachMemberAssignment extends Model { use BelongsToGym; protected $fillable=['coach_id','member_id','domain','is_active','is_default']; protected function casts(): array{return ['is_active'=>'boolean','is_default'=>'boolean'];} public function coach(){return $this->belongsTo(Coach::class);} public function member(){return $this->belongsTo(Member::class);} }
