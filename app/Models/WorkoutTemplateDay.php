<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutTemplateDay extends Model { use BelongsToGym; protected $fillable=['workout_template_id','phase','day_number','title','notes']; public function template(){return $this->belongsTo(WorkoutTemplate::class);} public function sets(){return $this->hasMany(WorkoutTemplateSet::class)->orderBy('set_number');} }
