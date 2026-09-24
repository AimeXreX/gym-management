<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

class BackupApplication extends Command
{
    protected $signature = 'app:backup {--retention=}';
    protected $description = 'Create a checksummed encrypted database and private-upload backup';

    public function handle(): int
    {
        if (! class_exists(ZipArchive::class)) { $this->error('PHP ZIP extension is required.'); return self::FAILURE; }
        $password=(string)config('backup.password');
        if (app()->isProduction() && $password==='') { $this->error('BACKUP_ENCRYPTION_PASSWORD is required in production.'); return self::FAILURE; }
        $lock=Cache::lock('app-backup',3600);
        if(! $lock->get()){ $this->error('Another backup is running.'); return self::FAILURE; }
        $databaseFile=null;
        try {
            $dir=(string)config('backup.path'); File::ensureDirectoryExists($dir);
            $stamp=now()->format('Ymd_His'); $driver=config('database.default');
            $extension=$driver==='sqlite'?'sqlite':'sql'; $databaseFile=$dir.DIRECTORY_SEPARATOR."database_$stamp.$extension";
            $this->dumpDatabase($databaseFile,$driver);
            $archive=$dir.DIRECTORY_SEPARATOR."gym_backup_$stamp.zip"; $zip=new ZipArchive;
            if($zip->open($archive,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot create backup archive.');
            $this->addEncrypted($zip,$databaseFile,'database.'.$extension,$password);
            $fileCount=0;
            foreach([storage_path('app/private')=>'private',storage_path('app/public')=>'public'] as $root=>$prefix){if(!File::isDirectory($root))continue;foreach(File::allFiles($root) as $file){$name=$prefix.'/'.$file->getRelativePathname();$this->addEncrypted($zip,$file->getRealPath(),$name,$password);$fileCount++;}}
            $manifest=json_encode(['created_at'=>now()->toIso8601String(),'app_env'=>app()->environment(),'database_driver'=>$driver,'database_file'=>'database.'.$extension,'database_sha256'=>hash_file('sha256',$databaseFile),'upload_files'=>$fileCount],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
            $zip->addFromString('manifest.json',$manifest); if($password!=='')$zip->setEncryptionName('manifest.json',ZipArchive::EM_AES_256,$password); $zip->close();
            File::put($archive.'.sha256',hash_file('sha256',$archive)."  ".basename($archive).PHP_EOL);
            $retention=max(1,(int)($this->option('retention')?:config('backup.retention')));
            collect(File::glob($dir.DIRECTORY_SEPARATOR.'gym_backup_*.zip'))->sortDesc()->slice($retention)->each(function($old){File::delete($old,$old.'.sha256');});
            $this->info('Backup created: '.basename($archive)); return self::SUCCESS;
        } catch (\Throwable $e) { report($e); $this->error($e->getMessage()); return self::FAILURE; }
        finally { if($databaseFile&&File::exists($databaseFile))File::delete($databaseFile); $lock->release(); }
    }

    private function dumpDatabase(string $target,string $driver):void
    {
        if($driver==='sqlite'){if(!File::copy(config('database.connections.sqlite.database'),$target))throw new RuntimeException('SQLite copy failed.');return;}
        if($driver!=='mysql')throw new RuntimeException('Unsupported database driver.');
        $c=config('database.connections.mysql');$process=new Process([(string)config('backup.mysqldump'),'--single-transaction','--quick','--skip-lock-tables','--routines','--triggers','-h',$c['host'],'-P',(string)$c['port'],'-u',$c['username'],$c['database']],null,['MYSQL_PWD'=>$c['password']]);$process->mustRun();File::put($target,$process->getOutput());
    }
    private function addEncrypted(ZipArchive $zip,string $source,string $name,string $password):void { $zip->addFile($source,str_replace('\\','/',$name)); if($password!=='')$zip->setEncryptionName(str_replace('\\','/',$name),ZipArchive::EM_AES_256,$password); }
}
