<?php
namespace App\Models;
use App\Core\Tenancy\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
class Exercise extends Model { use BelongsToGym; protected $fillable=['name','category','muscle_group','equipment','description','created_by']; public function creator(){return $this->belongsTo(User::class,'created_by');} }
