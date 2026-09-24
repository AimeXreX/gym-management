<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class WorkoutTemplate extends Model { use BelongsToGym; protected $fillable=['coach_id','name','goal','notes','created_by']; public function coach(){return $this->belongsTo(Coach::class);} public function days(){return $this->hasMany(WorkoutTemplateDay::class)->orderBy('day_number');} }
