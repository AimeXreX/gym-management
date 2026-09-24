<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutProgram extends Model { use BelongsToGym; protected $fillable=['member_id','coach_id','created_by','title','starts_on','ends_on','goal','status','notes']; protected function casts(): array{return ['starts_on'=>'date','ends_on'=>'date'];} public function days(){return $this->hasMany(WorkoutProgramDay::class)->orderBy('day_number');} public function member(){return $this->belongsTo(Member::class);} public function coach(){return $this->belongsTo(Coach::class);} public function feedback(){return $this->hasMany(WorkoutProgramFeedback::class)->latest();} }
