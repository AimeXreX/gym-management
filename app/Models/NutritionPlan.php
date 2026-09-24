<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class NutritionPlan extends Model { use BelongsToGym; protected $fillable=['member_id','created_by','title','starts_on','ends_on','goal','notes','status']; protected function casts(): array{return ['starts_on'=>'date','ends_on'=>'date'];} public function items(){return $this->hasMany(NutritionPlanItem::class)->orderBy('sort_order');} public function member(){return $this->belongsTo(Member::class);} }
