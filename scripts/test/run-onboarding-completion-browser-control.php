<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/phase2/bootstrap.php';
TestEnvironment::assertSafeEnvironment(getenv());
if(PHP_SAPI!=='cli'||!is_file('/.dockerenv')){exit(2);}
$file=$argv[1]??'';$action=$argv[2]??'';$real=realpath($file);
if(!$real||is_link($file)||basename($real)!=='control.json'||!str_starts_with($real,sys_get_temp_dir().'/ather-')){exit(2);}
$c=json_decode(file_get_contents($real),true,8,JSON_THROW_ON_ERROR);
if(($c['browser']??null)!==true||!isset($c['users'],$c['sessions'],$c['session_path'])){exit(2);}
switch($action){
    case 'v1':case 'v2':$c['version']=$action;unset($c['fault']);break;
    case 'uncertain':$c['fault']='after_commit';break;
    case 'recover':unset($c['fault']);break;
    case 'revoke-k':
        require_once __DIR__.'/../../config/database.php';
        getDatabaseConnection()->prepare('UPDATE users SET authz_version=authz_version+1 WHERE id=?')->execute([$c['users']['k']]);
        break;
    case 'profile-edit':
    case 'assert-private':
    case 'assert-uncompleted':
        require_once __DIR__.'/../../config/database.php';
        require_once __DIR__.'/../../includes/owner_session.php';
        require_once __DIR__.'/../../includes/profile_writes.php';
        $db=getDatabaseConnection();
        if($action==='assert-uncompleted'){if((int)$db->query('SELECT COUNT(*) FROM portfolios WHERE owner_user_id='.(int)$c['users']['a'])->fetchColumn()!==0){exit(1);}}
        elseif($action==='profile-edit'){
            session_save_path($c['session_path']);session_id($c['sessions']['a']);startOwnerSession();
            $owned=requireOwnedPortfolioContext($db);$row=loadAuthorizedPersonalInfo($db,$owned);
            saveOwnedProfilePatch($db,$owned,(int)$row['id'],(int)$row['profile_version'],['full_name'=>'Later authoritative Profile']);session_write_close();
        }else{
            if((int)$db->query('SELECT COUNT(*) FROM portfolios WHERE owner_user_id IN ('.implode(',',array_map('intval',$c['users'])).') AND (is_published<>0 OR published_at IS NOT NULL)')->fetchColumn()!==0){exit(1);}
            if((int)$db->query('SELECT COUNT(*) FROM personal_info WHERE portfolio_id IN (SELECT id FROM portfolios WHERE owner_user_id IN ('.implode(',',array_map('intval',$c['users'])).')) AND (public_email_visible<>0 OR public_phone_visible<>0)')->fetchColumn()!==0){exit(1);}
        }
        break;
    default:exit(2);
}
file_put_contents($real,json_encode($c,JSON_THROW_ON_ERROR),LOCK_EX);chmod($real,0600);
echo "PASS guarded completion browser fixture control\n";
