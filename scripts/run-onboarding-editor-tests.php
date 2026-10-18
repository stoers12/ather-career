<?php
declare(strict_types=1);
require_once __DIR__.'/../tests/phase2/bootstrap.php';
require_once __DIR__.'/../includes/onboarding_presentation.php';
TestEnvironment::assertSafeEnvironment(getenv());
$checks=0;
foreach (['ar','en'] as $locale) {
    foreach ([1,2,3,4,5] as $step) {
        ob_start(); renderOnboardingPresentation($locale,$step,true,str_repeat('a',64)); $html=ob_get_clean();
        $dom=new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath=new DOMXPath($dom);
        phase2Assert($xpath->query('//input[@type="checkbox" and @disabled]')->length===5,'Unsupported editor controls must be disabled');
        phase2Assert($xpath->query('//*[@data-draft-field]')->length===8,'Editor must expose exactly the approved seven fields/eight controls');
        phase2Assert($xpath->query('//button[@data-create and @disabled]')->length===1,'Create must stay disabled');
        phase2Assert(!str_contains($html,'m••••d@example.com') && str_contains($html,'onboarding_editor.js?v='),'Editor email/script boundary');
        phase2Assert(str_contains($html,'aria-live="polite"') && str_contains($html,'data-editor-context='),'Accessible editor feedback/context');
        $checks+=5;
    }
}
$source=file_get_contents(__DIR__.'/../owner_onboarding_editor.php');
phase2Assert(!str_contains($source,'tests/') && !str_contains($source,'createOwnedPortfolio') && str_contains($source,'onboardingDraftHttpEnabled()'),'Editor production boundary');
$js=file_get_contents(__DIR__.'/../onboarding_editor.js');
foreach (['localStorage','sessionStorage','console.','sendBeacon','window.name','document.cookie'] as $term) { phase2Assert(!str_contains($js,$term),'Private editor persistence/telemetry boundary'); }
$docker=file_get_contents(__DIR__.'/../Dockerfile.production');
foreach (['onboarding.css','onboarding_editor.js'] as $asset) { phase2Assert(str_contains($docker,'/var/www/app/'.$asset),'Production packaging asset missing'); }
echo 'PASS editor shared rendering, disabled boundaries and accessible AR/EN: '.$checks." checks\n";
echo "PASS editor private script and production packaging contracts\n";
