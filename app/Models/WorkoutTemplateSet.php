<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutTemplateSet extends Model { use BelongsToGym; protected $fillable=['workout_template_day_id','exercise_id','exercise_name','set_number','weight_kg','repetitions','duration_seconds','notes']; protected function casts(): array{return ['weight_kg'=>'decimal:2'];} public function day(){return $this->belongsTo(WorkoutTemplateDay::class);} public function exercise(){return $this->belongsTo(Exercise::class);} }
