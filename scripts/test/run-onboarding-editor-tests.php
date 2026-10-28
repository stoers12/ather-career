<?php
declare(strict_types=1);
require_once __DIR__.'/../../tests/phase2/bootstrap.php';
require_once __DIR__.'/../../includes/onboarding_presentation.php';
TestEnvironment::assertSafeEnvironment(getenv());
$checks=0;
foreach (['ar','en'] as $locale) {
    foreach ([1,2,3,4,5] as $step) {
        ob_start(); renderOnboardingPresentation($locale,$step,true,str_repeat('a',64)); $html=ob_get_clean();
        $dom=new DOMDocument(); @$dom->loadHTML('<?xml encoding="UTF-8">'.$html); $xpath=new DOMXPath($dom);
        phase2Assert($xpath->query('//input[@type="checkbox" and @disabled]')->length===7,'Unsupported editor controls must be disabled');
        phase2Assert($xpath->query('//input[@data-attestation-action and @disabled and not(@checked)]')->length===3,'Three distinct initially unchecked attestation actions');
        phase2Assert($xpath->query('//*[@data-attestation-documents]')->length===1 && $xpath->query('//*[@data-attestation-status and @role="status" and @aria-live="polite"]')->length===1,'Exact document container and independent accessible status');
        phase2Assert($xpath->query('//button[@data-attestation-record and @disabled]')->length===1 && $xpath->query('//button[@data-attestation-load and @disabled]')->length===1,'Explicit initial disabled recording and read controls');
        phase2Assert($xpath->query('//input[@data-completion-confirm and @disabled and not(@checked)]')->length===2,'Two separate initially unchecked completion confirmations');
        phase2Assert($xpath->query('//*[@data-completion-status and @role="status" and @aria-live="polite"]')->length===1 && $xpath->query('//*[@data-completion-review-body and @hidden]')->length===1,'Private completion live status and no fabricated review');
        phase2Assert($xpath->query('//*[@data-draft-field]')->length===8,'Editor must expose exactly the approved seven fields/eight controls');
        phase2Assert($xpath->query('//button[@data-create and @disabled]')->length===1,'Create must start disabled');
        phase2Assert($xpath->query('//a[@data-completion-dashboard and @hidden and @href="/owner.php"]')->length===1,'Dashboard action starts hidden and targets reauthorizing fixed route');
        phase2Assert(!str_contains($html,'m••••d@example.com') && str_contains($html,'onboarding_editor.js?v='),'Editor email/script boundary');
        phase2Assert(str_contains($html,'aria-live="polite"') && str_contains($html,'data-editor-context='),'Accessible editor feedback/context');

        $catalog=finalProfileCatalog();
        foreach (['countries'=>'country_code','statuses'=>'professional_status'] as $group=>$field) {
            $options=$xpath->query('//select[@data-draft-field="'.$field.'"]/option');
            phase2Assert($options->length===count($catalog[$group])+1,'Editor catalog count mismatch');
            $actual=[];
            foreach ($options as $option) { if ($option->getAttribute('value')!=='') { $actual[$option->getAttribute('value')]=$option->textContent; } }
            phase2Assert($actual===array_map(static fn(array $labels): string=>$labels[$locale],$catalog[$group]),'Editor catalog membership/labels differ from Profile');
        }
        foreach ($catalog['languages'] as $token=>$labels) {
            phase2Assert($xpath->query('//input[@data-draft-field="preferred_language" and @value="'.$token.'"]/parent::label')->item(0)->textContent===$labels[$locale], 'Profile language label differs');
        }
        phase2Assert(!str_contains($html,'maxlength=') && str_contains($html,'150'),'Specialization Unicode limit is server-owned and explained');

        $countryOptions=$xpath->query('//select[@data-draft-field="country_code"]/option');
        phase2Assert($countryOptions->length===14 && $countryOptions->item(0)->getAttribute('value')==='' && $xpath->query('//select[@data-country]/option[@selected]')->length===0,'No inferred/default country');
        phase2Assert(array_keys($catalog['countries'])===['JO','PS','SA','AE','QA','KW','BH','OM','IQ','LB','SY','YE','EG'],'Exact launch order');
        $checks+=7;
    }
}
$source=file_get_contents(__DIR__.'/../../owner_onboarding_editor.php');
phase2Assert(!str_contains($source,'tests/') && !str_contains($source,'createOwnedPortfolio') && str_contains($source,'onboardingDraftHttpEnabled()'),'Editor production boundary');
$js=file_get_contents(__DIR__.'/../../onboarding_editor.js');
foreach (['localStorage','sessionStorage','console.','sendBeacon','window.name','document.cookie'] as $term) { phase2Assert(!str_contains($js,$term),'Private editor persistence/telemetry boundary'); }
$docker=file_get_contents(__DIR__.'/../../Dockerfile.production');
foreach (['onboarding.css','onboarding_editor.js'] as $asset) { phase2Assert(str_contains($docker,'/var/www/app/'.$asset),'Production packaging asset missing'); }
echo 'PASS editor shared rendering, disabled boundaries and accessible AR/EN: '.$checks." checks\n";
echo "PASS editor private script and production packaging contracts\n";

echo "PASS editor exact shared country/status/language catalogs and localized labels with native controls\n";
