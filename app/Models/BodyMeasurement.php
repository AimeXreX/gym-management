<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class BodyMeasurement extends Model { use BelongsToGym; protected $fillable=['member_id','recorded_by','measured_on','weight_kg','body_fat_percent','waist_cm','chest_cm','arm_cm','thigh_cm','notes']; protected function casts():array{return ['measured_on'=>'date'];} }
