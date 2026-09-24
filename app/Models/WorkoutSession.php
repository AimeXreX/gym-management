<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutSession extends Model { use BelongsToGym; protected $fillable=['member_id','recorded_by','performed_on','title','notes','program_id','program_day_id']; protected function casts(): array{return ['performed_on'=>'date'];} public function sets(){return $this->hasMany(WorkoutSet::class)->orderBy('set_number');} public function member(){return $this->belongsTo(Member::class);} public function program(){return $this->belongsTo(WorkoutProgram::class);} public function programDay(){return $this->belongsTo(WorkoutProgramDay::class);} }
