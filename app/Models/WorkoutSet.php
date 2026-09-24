<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutSet extends Model { use BelongsToGym; protected $fillable=['workout_session_id','exercise_name','set_number','weight_kg','repetitions','duration_seconds','notes']; protected function casts(): array{return ['weight_kg'=>'decimal:2'];} }
