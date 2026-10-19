<?php
declare(strict_types=1);

// This builder is never included by a production route. Synthetic resolvers and
// PDO faults exist only in private source copies created by guarded CLI tests.
if (PHP_SAPI !== 'cli' || !is_file('/.dockerenv') || getenv('APP_ENV') !== 'test'
    || getenv('ATHERCAR_TEST_MODE') !== '1'
    || preg_match('/\Aather_career_test_[a-f0-9]{24}\z/D', (string)getenv('DB_NAME')) !== 1
    || getenv('DB_NAME') !== getenv('ATHERCAR_TEST_DB_NAME')) { exit(2); }

function attestationHttpFixtureCopies(string $root): void
{
    $app = realpath(__DIR__.'/../../..');
    $includes = $app.'/includes';
    mkdir($root.'/synthetic',0700);
    $private = $root.'/synthetic';
    $bootstrap = str_replace('__CONTROL__',var_export($root.'/control.json',true),file_get_contents(__DIR__.'/attestation-http-runtime.php'));
    $bootstrap = str_replace('__BARRIER__',var_export($root.'/arrivals',true),$bootstrap);
    file_put_contents($private.'/bootstrap.php',$bootstrap); chmod($private.'/bootstrap.php',0600);
    foreach (['onboarding_attestations.php','onboarding_attestation_presentation.php'] as $name) {
        $source = str_replace("\r\n","\n",file_get_contents($includes.'/'.$name));
        $function = $name === 'onboarding_attestations.php' ? 'currentOnboardingAttestationBundle' : 'currentOnboardingAttestationDocuments';
        $type = $name === 'onboarding_attestations.php' ? '?OnboardingAttestationBundle' : '?array';
        $replacement = $name === 'onboarding_attestations.php' ? 'attestationHttpFixtureBundle()' : 'attestationHttpFixtureDocuments()';
        $needle = "function $function(): $type\n{\n    return null;\n}";
        if (substr_count($source,$needle)!==1) { throw new RuntimeException('Fixed null resolver seam changed.'); }
        $copy = str_replace($needle,"function $function(): $type\n{\n    return $replacement;\n}",$source);
        $copy = str_replace("require_once __DIR__ . '/onboarding_attestations.php';",'require_once '.var_export($private.'/onboarding_attestations.php',true).';',$copy);
        $copy = str_replace('__DIR__',var_export($includes,true),$copy);
        // Only these exact resolver/include-path transformations are permitted.
        $restored = str_replace(var_export($includes,true),'__DIR__',$copy);
        $restored = str_replace('require_once '.var_export($private.'/onboarding_attestations.php',true).';',"require_once __DIR__ . '/onboarding_attestations.php';",$restored);
        $restored = str_replace("function $function(): $type\n{\n    return $replacement;\n}",$needle,$restored);
        if ($restored !== $source) { throw new RuntimeException('Test copy changed functional attestation behavior.'); }
        file_put_contents($private.'/'.$name,$copy); chmod($private.'/'.$name,0600);
    }
    $source = file_get_contents($app.'/config/database.php');
    if (substr_count($source,'return new PDO(')!==1) { throw new RuntimeException('Test connection seam changed.'); }
    file_put_contents($private.'/database.php',str_replace('return new PDO(','return new AttestationHttpFixturePDO(',$source)); chmod($private.'/database.php',0600);
    $handler = file_get_contents($app.'/owner_onboarding_attestation.php');
    $copy = str_replace("require_once __DIR__ . '/includes/onboarding_attestation_presentation.php';",'require_once '.var_export($private.'/onboarding_attestation_presentation.php',true).';',$handler);
    $copy = str_replace("require_once __DIR__ . '/config/database.php';",'require_once '.var_export($private.'/database.php',true).';',$copy);
    $copy = str_replace('__DIR__',var_export($app,true),$copy);
    $restore = str_replace(var_export($app,true),'__DIR__',$copy);
    $restore = str_replace('require_once '.var_export($private.'/onboarding_attestation_presentation.php',true).';',"require_once __DIR__ . '/includes/onboarding_attestation_presentation.php';",$restore);
    $restore = str_replace('require_once '.var_export($private.'/database.php',true).';',"require_once __DIR__ . '/config/database.php';",$restore);
    if ($restore !== $handler) { throw new RuntimeException('Test copy changed HTTP handler behavior.'); }
    $copy = str_replace('declare(strict_types=1);','declare(strict_types=1);'."\nrequire_once ".var_export($private.'/bootstrap.php',true).';',$copy);
    if (!str_contains($copy,"require_once ".var_export($private.'/bootstrap.php',true))) {
        throw new RuntimeException('Fixture bootstrap insertion failed.');
    }
    file_put_contents($private.'/handler.php',$copy); chmod($private.'/handler.php',0600);
    mkdir($root.'/synthetic-public',0700);
    file_put_contents($root.'/synthetic-public/owner_onboarding_attestation.php','<?php require '.var_export($private.'/handler.php',true).';');
    chmod($root.'/synthetic-public/owner_onboarding_attestation.php',0600);
}
