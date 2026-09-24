<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutProgramFeedback extends Model { use BelongsToGym; protected $fillable=['workout_program_id','user_id','body']; public function program(){return $this->belongsTo(WorkoutProgram::class);} public function user(){return $this->belongsTo(User::class);} }
