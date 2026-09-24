<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
class ProductionCheck extends Command {
 protected $signature='app:production-check'; protected $description='Fail-fast production configuration and infrastructure preflight';
 public function handle():int{$checks=['APP_ENV is production'=>app()->environment('production'),'APP_DEBUG is false'=>config('app.debug')===false,'APP_URL uses HTTPS'=>str_starts_with((string)config('app.url'),'https://'),'APP_KEY is set'=>(string)config('app.key')!=='','secure session cookie'=>config('session.secure')===true,'encrypted sessions'=>config('session.encrypt')===true,'backup password is set'=>(string)config('backup.password')!=='','private storage is writable'=>File::isWritable(storage_path('app/private')),'cache directory is writable'=>File::isWritable(base_path('bootstrap/cache'))];try{DB::select('select 1');$checks['database connection']=true;}catch(\Throwable){$checks['database connection']=false;}foreach($checks as $label=>$ok)$this->line(($ok?'[PASS] ':'[FAIL] ').$label);return in_array(false,$checks,true)?self::FAILURE:self::SUCCESS;}
}
