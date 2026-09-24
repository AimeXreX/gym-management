<?php
namespace App\Http\Controllers;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
class HealthController extends Controller {
 public function __invoke():JsonResponse{$checks=[];try{DB::select('select 1');$checks['database']=true;}catch(\Throwable){$checks['database']=false;}try{$probe=storage_path('framework/health-probe');File::put($probe,'ok');File::delete($probe);$checks['storage']=true;}catch(\Throwable){$checks['storage']=false;}$heartbeat=storage_path('framework/scheduler-heartbeat');$checks['scheduler']=File::exists($heartbeat)&&File::lastModified($heartbeat)>=now()->subMinutes(5)->timestamp;$backups=File::glob(rtrim((string)config('backup.path'),'\\/').DIRECTORY_SEPARATOR.'gym_backup_*.zip');$latest=$backups?max(array_map('filemtime',$backups)):0;$checks['backup']=$latest>=now()->subHours(config('backup.max_age_hours'))->timestamp;$ok=!in_array(false,$checks,true);return response()->json(['status'=>$ok?'ok':'degraded','checks'=>$checks],$ok?200:503)->header('Cache-Control','no-store');}
}
