<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use PDO;
use ZipArchive;
class VerifyBackup extends Command {
 protected $signature='app:backup-verify {archive}'; protected $description='Verify checksum, decryptability and database integrity without touching production';
 public function handle():int{$archive=realpath($this->argument('archive'));if(!$archive||!is_file($archive)){ $this->error('Archive not found.');return self::FAILURE;} $sum=$archive.'.sha256';if(!is_file($sum)||!hash_equals(strtok(trim(File::get($sum)),' '),hash_file('sha256',$archive))){$this->error('Archive checksum failed.');return self::FAILURE;}$zip=new ZipArchive;if($zip->open($archive)!==true)return self::FAILURE;$password=(string)config('backup.password');if($password!=='')$zip->setPassword($password);$manifest=json_decode($zip->getFromName('manifest.json'),true);if(!is_array($manifest)){ $this->error('Manifest cannot be decrypted.');return self::FAILURE;}$db=$zip->getFromName($manifest['database_file']);$zip->close();if($db===false||!hash_equals($manifest['database_sha256'],hash('sha256',$db))){$this->error('Database checksum failed.');return self::FAILURE;}if($manifest['database_driver']==='sqlite'){$tmp=tempnam(sys_get_temp_dir(),'gym_restore_');File::put($tmp,$db);$result=(new PDO('sqlite:'.$tmp))->query('PRAGMA integrity_check')->fetchColumn();File::delete($tmp);if($result!=='ok'){ $this->error('SQLite integrity check failed.');return self::FAILURE;}}elseif(strlen($db)<100){$this->error('SQL dump is unexpectedly empty.');return self::FAILURE;}$this->info('Restore drill passed: checksum, decryption and database integrity are valid.');return self::SUCCESS;}
}
