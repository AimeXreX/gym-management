<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutProgramDay extends Model { use BelongsToGym; protected $fillable=['workout_program_id','day_number','title','notes']; public function program(){return $this->belongsTo(WorkoutProgram::class);} public function sets(){return $this->hasMany(WorkoutProgramSet::class)->orderBy('set_number');} public function sessions(){return $this->hasMany(WorkoutSession::class,'program_day_id');} }
