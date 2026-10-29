<?php
declare(strict_types=1);
if (PHP_SAPI!=='cli' || !is_file('/.dockerenv') || getenv('APP_ENV')!=='test' || getenv('ATHERCAR_TEST_MODE')!=='1' || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D',(string)getenv('DB_NAME'))!==1 || getenv('DB_NAME')!==getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }

function journeyFixtureCopy(string $root): void
{
    $app=realpath(__DIR__.'/../..');
    foreach (['handlers','includes','config','data'] as $directory) {
        $source=$app.'/'.$directory;
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::SELF_FIRST);
        mkdir($root.'/'.$directory,0700,true);
        foreach ($iterator as $file) {
            $target=$root.'/'.$directory.'/'.substr($file->getPathname(),strlen($source)+1);
            if ($file->isLink()) { throw new RuntimeException('Unexpected source link.'); }
            if ($file->isDir()) { mkdir($target,0700); } else { copy($file->getPathname(),$target); chmod($target,0600); }
        }
    }
    symlink($app.'/vendor',$root.'/vendor');
    symlink($app.'/public',$root.'/public');
    $control=var_export(dirname($root).'/documents.json',true);
    foreach (['onboarding_attestations.php'=>'?OnboardingAttestationBundle','onboarding_attestation_presentation.php'=>'?array'] as $name=>$type) {
        $function=$type==='?array' ? 'currentOnboardingAttestationDocuments' : 'currentOnboardingAttestationBundle';
        $file=$root.'/includes/'.$name; $source=str_replace("\r\n","\n",file_get_contents($file));
        $needle="function $function(): $type\n{\n    return null;\n}";
        if (substr_count($source,$needle)!==1) { throw new RuntimeException('Literal null policy seam changed.'); }
        $body='    $path='.$control.'; if (!is_file($path)) { return null; } $d=json_decode(file_get_contents($path),true,8,JSON_THROW_ON_ERROR);';
        if ($type==='?array') { $body.=' return $d;'; }
        else { $body.=' $one=static fn(string $k): OnboardingDocumentDescriptor=>new OnboardingDocumentDescriptor("test-".$k,"synthetic-v1",hash("sha256",$d[$k]["content"]),$d[$k]["language"]); return new OnboardingAttestationBundle($one("age"),$one("terms"),$one("privacy"));'; }
        $replacement="function $function(): $type\n{\n$body\n}";
        $copy=str_replace($needle,$replacement,$source);
        if (str_replace($replacement,$needle,$copy)!==$source) { throw new RuntimeException('Private fixture changed other behavior.'); }
        file_put_contents($file,$copy);
    }
}

function journeyFixtureRemove(string $path): void
{
    if (is_link($path) || is_file($path)) { if (!unlink($path)) { throw new RuntimeException('Fixture file cleanup failed.'); } return; }
    foreach (new DirectoryIterator($path) as $entry) { if (!$entry->isDot()) { journeyFixtureRemove($entry->getPathname()); } }
    if (!rmdir($path)) { throw new RuntimeException('Fixture directory cleanup failed.'); }
}
