<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutProgramSet extends Model { use BelongsToGym; protected $fillable=['workout_program_day_id','exercise_name','set_number','weight_kg','repetitions','notes']; protected function casts(): array{return ['weight_kg'=>'decimal:2'];} public function day(){return $this->belongsTo(WorkoutProgramDay::class);} }
