<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class NutritionPlanItem extends Model { use BelongsToGym; protected $fillable=['nutrition_plan_id','meal_name','suggested_at','foods','sort_order']; }
