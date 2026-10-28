<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/phase2/bootstrap.php';
TestEnvironment::assertSafeEnvironment(getenv());
if (PHP_SAPI!=='cli' || !is_file('/.dockerenv')) { exit(2); }
$file=$argv[1]??''; $action=$argv[2]??'';
$real=realpath($file);
if (!$real || is_link($file) || basename($real)!=='control.json'
    || !str_starts_with($real,sys_get_temp_dir().'/ather-')) { exit(2); }
$control=json_decode(file_get_contents($real),true,8,JSON_THROW_ON_ERROR);
if (($control['browser']??null)!==true) { exit(2); }
switch ($action) {
    case 'v1': $control=['browser'=>true,'version'=>'v1','language'=>'en']; break;
    case 'v2': case 'v4': case 'v5': case 'v6': case 'v7': case 'v8':
        $control=['browser'=>true,'version'=>$action,'language'=>'en']; break;
    case 'ar': $control=['browser'=>true,'version'=>'v3','language'=>'ar']; break;
    case 'uncertain': $control=['browser'=>true,'version'=>'v4','language'=>'en','fault'=>'after_commit']; break;
    case 'recover': unset($control['fault']); break;
    case 'missing': $control['null_documents']=true; break;
    default: exit(2);
}
file_put_contents($real,json_encode($control,JSON_THROW_ON_ERROR),LOCK_EX);
chmod($real,0600);
echo "PASS guarded attestation browser fixture control\n";
