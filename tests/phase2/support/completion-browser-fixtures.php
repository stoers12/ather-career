<?php
declare(strict_types=1);

// Guarded CLI builder only. Existing document-copy guards reject production use.
require_once __DIR__.'/attestation-http-fixtures.php';
function completionBrowserFixtureCopies(string $root): void
{
    attestationHttpFixtureCopies($root);
    $app=realpath(__DIR__.'/../../..'); $private=$root.'/synthetic';
    $core=file_get_contents($app.'/includes/onboarding_completion.php');
    $needle="require_once __DIR__ . '/onboarding_attestations.php';";
    $replacement='require_once '.var_export($private.'/onboarding_attestations.php',true).';';
    if(substr_count($core,$needle)!==1){throw new RuntimeException('Completion include seam changed.');}
    $copy=str_replace('__DIR__',var_export($app.'/includes',true),str_replace($needle,$replacement,$core));
    if(str_replace($replacement,$needle,str_replace(var_export($app.'/includes',true),'__DIR__',$copy))!==$core){throw new RuntimeException('Completion test copy changed behavior.');}
    file_put_contents($private.'/completion.php',$copy); chmod($private.'/completion.php',0600);
    $handler=file_get_contents($app.'/owner_onboarding_completion.php');
    $replacements=["require_once __DIR__ . '/includes/onboarding_completion.php';"=>'require_once '.var_export($private.'/completion.php',true).';',
        "require_once __DIR__ . '/config/database.php';"=>'require_once '.var_export($private.'/database.php',true).';'];
    foreach($replacements as $from=>$to){if(substr_count($handler,$from)!==1){throw new RuntimeException('HTTP include seam changed.');}}
    $copy=str_replace('__DIR__',var_export($app,true),str_replace(array_keys($replacements),array_values($replacements),$handler));
    if(str_replace(array_values($replacements),array_keys($replacements),str_replace(var_export($app,true),'__DIR__',$copy))!==$handler){throw new RuntimeException('HTTP test copy changed behavior.');}
    $copy=str_replace('declare(strict_types=1);','declare(strict_types=1);'."\nrequire_once ".var_export($private.'/bootstrap.php',true).';',$copy);
    file_put_contents($private.'/completion-handler.php',$copy); chmod($private.'/completion-handler.php',0600);
    file_put_contents($root.'/synthetic-public/owner_onboarding_completion.php','<?php require '.var_export($private.'/completion-handler.php',true).';');
    chmod($root.'/synthetic-public/owner_onboarding_completion.php',0600);
}
