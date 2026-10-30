import {createRequire} from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');
if (process.env.APP_ENV!=='test' || process.env.ATHERCAR_TEST_MODE!=='1') process.exit(2);
const base=process.env.ATHERCAR_FINAL_PROFILE_BASE_URL;
const evidence=process.env.ATHERCAR_FINAL_PROFILE_BROWSER_EVIDENCE;
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base||'') || !fs.statSync(evidence).isDirectory()) process.exit(2);
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'private-profile-context.json'),'utf8'));
const shots=path.join(evidence,'profile-screenshots'); fs.mkdirSync(shots,{recursive:true});
let browser, context, diagnosticPage, stage='startup', operation='launch-browser';
function check(value,message) { if (!value) { const e=new Error('Assertion'); e.safeMessage=message; throw e; } }
const form=p=>p.locator('form').filter({has:p.locator('input[name="action"][value="save_profile"]')});
const version=p=>form(p).locator('[name="expected_profile_version"]').inputValue();
// The Profile groups the final-profile and phone controls in a collapsed native disclosure.
// Open it as a user would (its summary) before interacting; reads need no reveal.
async function revealed(p,id) {
  const control=p.locator('#'+id);
  const closed=control.locator('xpath=ancestor::details[not(@open)]');
  while (await closed.count()) await closed.first().locator(':scope > summary').click();
  return control;
}
const labelFailure=label=>error=>{ error.safeOperation=label; throw error; };
async function failureDiagnostics(p) {
  if (!p || p.isClosed()) return {available:false};
  const pathname=new URL(p.url()).pathname;
  const language=await p.locator('html').evaluateAll(elements=>elements[0]?.getAttribute('lang'));
  return {
    pathname:['/owner_profile.php','/signin.php','/owner_login.php'].includes(pathname)?pathname:'[other]',
    documentLanguage:['en','ar'].includes(language)?language:'[other]',
    matchingButtonCount:await form(p).getByRole('button',{name:'Save Changes',exact:true}).count(),
    submitButtons:await form(p).locator('button[type="submit"]').evaluateAll(buttons=>buttons.map(button=>{
      const style=getComputedStyle(button), box=button.getBoundingClientRect();
      return {visible:style.visibility!=='hidden' && style.visibility!=='collapse' && box.width>0 && box.height>0,
        enabled:!button.matches(':disabled') && !button.closest('[aria-disabled="true"]')};
    }))
  };
}
async function save(p,status=200) {
  diagnosticPage=p;
  const [response]=await Promise.all([
    p.waitForNavigation().catch(labelFailure('save-navigation')),
    form(p).getByRole('button',{name:'Save Changes',exact:true}).click().catch(labelFailure('save-click'))
  ]);
  operation='verify-save-response';
  check(response.status()===status,'Unexpected profile navigation status');
}
try {
  browser=await chromium.launch({headless:true});
  operation='create-browser-context';
  context=await browser.newContext({viewport:{width:1440,height:1000}});
  operation='install-fixture-session';
  await context.addCookies([{name:fixture.cookie.Name,value:fixture.cookie.Value,url:base,httpOnly:true,sameSite:'Lax'}]);
  operation='open-english-profile';
  const p=await context.newPage(); diagnosticPage=p; await p.goto(base+'/owner_profile.php?lang=en');
  operation='assert-english-document';
  check(await p.locator('html').getAttribute('lang')==='en','Final-profile fixture must use the English workspace.');
  stage='retired choices';
  operation='read-retired-country';
  check(await p.locator('#country_code').inputValue()==='US','Retired country was coerced');
  operation='inspect-country-catalog';
  check(await p.locator('#country_code option').count()===15,'Country catalog count');
  check(JSON.stringify(await p.locator('#country_code option').evaluateAll(os=>os.filter(o=>o.value && o.value!=='US').map(o=>o.value)))===JSON.stringify(['JO','PS','SA','AE','QA','KW','BH','OM','IQ','LB','SY','YE','EG']),'Approved country order');
  operation='read-retired-country-label';
  check((await p.locator('#country_code option:checked').textContent()).includes('Older saved choice'),'Historical country is not labeled');
  operation='inspect-retired-field-accessibility';
  for (const id of ['country_code','preferred_language','professional_status','specialization']) {
    check(await p.locator('label[for="'+id+'"]').count()===1,'Missing label');
    check(await p.locator('#'+id).getAttribute('aria-describedby')===id+'-help','Missing accessible explanation');
  }
  operation='fill-professional-title';
  await p.locator('#professional_title').fill('Reviewed unchanged retirement'); await save(p);
  operation='verify-retired-values-after-save';
  check(await p.locator('#country_code').inputValue()==='US' && await p.locator('#professional_status').inputValue()==='retired_status','Unrelated edit lost retired values');
  console.log('PASS final-profile browser ordered 13-country launch catalog, AR/EN labels/help and unchanged retired choices');

  stage='exact values';
  const exact='  تخصص e\u0301 😀  ';
  operation='select-country'; await (await revealed(p,'country_code')).selectOption('JO');
  operation='select-profile-language'; await (await revealed(p,'preferred_language')).selectOption('ar');
  operation='select-professional-status'; await (await revealed(p,'professional_status')).selectOption('student');
  operation='fill-exact-specialization'; await (await revealed(p,'specialization')).fill(exact); await save(p);
  operation='reload-exact-values';
  await p.reload();
  operation='verify-exact-values';
  check(await p.locator('#specialization').inputValue()===exact,'Text was normalized or trimmed');
  check(await p.locator('#location').inputValue()==='Original location' && await p.locator('#professional_title').inputValue()==='Reviewed unchanged retirement','New fields overwrote legacy meaning');
  check(await p.locator('#preferred_language').inputValue()==='ar' && await p.locator('html').getAttribute('lang')==='en','Preference changed interface language');
  console.log('PASS final-profile browser real saves/reload preserve exact mixed Unicode and distinct legacy fields');

  stage='independent contact visibility';
  operation='fill-profile-email';
  await p.locator('#email').fill('browser-contact@example.test');
  operation='fill-primary-phone';
  await (await revealed(p,'phone_primary')).fill('+962 79 456 7890');
  operation='fill-secondary-phone';
  await (await revealed(p,'phone_secondary')).fill('+962 78 765 4321');
  for (const id of ['public_email_visible','public_phone_visible']) {
    operation='inspect-contact-checkbox';
    check(await p.locator('label[for="'+id+'"]').count()===1 && await p.locator('#'+id).getAttribute('aria-describedby')===id+'-help','Independent checkbox label/help missing');
    check(!await p.locator('#'+id).isChecked(),'New Profile contact did not default private');
    operation='focus-contact-checkbox'; await (await revealed(p,id)).focus();
    operation='toggle-contact-checkbox'; await p.keyboard.press('Space');
    check(await p.locator('#'+id).isChecked(),'Independent checkbox is not keyboard operable');
    operation='restore-contact-checkbox'; await p.keyboard.press('Space');
  }
  operation='verify-retired-contact-controls-absent';
  check(await p.locator('[name="public_contact_visible"],[name="public_contact_present"],[name="public_phone_secondary_visible"]').count()===0,'Retired/secondary visibility control appeared');
  for (const [email,phone] of [[false,false],[true,false],[false,true],[true,true]]) {
    operation='set-email-visibility'; await p.locator('#public_email_visible').setChecked(email);
    operation='set-phone-visibility'; await (await revealed(p,'public_phone_visible')).setChecked(phone); await save(p);
    operation='reload-contact-visibility'; await p.reload();
    operation='verify-contact-visibility';
    check(await p.locator('#public_email_visible').isChecked()===email && await p.locator('#public_phone_visible').isChecked()===phone,'Save/reload coupled the contact choices');
    check(await p.locator('#email').inputValue()==='browser-contact@example.test' && await p.locator('#phone_primary').inputValue()==='+962 79 456 7890','Toggle changed stored contact text');
  }
  console.log('PASS contact-types browser private defaults independent labels keyboard four combinations and exact save reload without secondary control');

  stage='boundaries and errors';
  operation='fill-unicode-boundary';
  await (await revealed(p,'specialization')).fill('😀'.repeat(150)); await save(p);
  operation='verify-unicode-boundary';
  check(await p.locator('#specialization').inputValue()==='😀'.repeat(150),'HTML UTF-16 maxlength blocked valid 150 code points');
  operation='read-profile-version'; const token=await version(p);
  operation='fill-invalid-specialization'; await (await revealed(p,'specialization')).fill('   '); await save(p,422);
  operation='verify-validation-retention';
  check(await p.locator('#specialization').inputValue()==='   ' && await version(p)===token && await p.locator('#specialization').getAttribute('aria-invalid')==='true','Validation lost input/token/accessibility');
  operation='capture-validation-screenshot'; await p.screenshot({path:path.join(shots,'profile-error-desktop.png'),fullPage:true});
  operation='clear-specialization';
  await (await revealed(p,'specialization')).fill(''); await save(p);
  operation='verify-cleared-specialization';
  check(await p.locator('#specialization').inputValue()==='','Explicit clear not reflected');
  operation='restore-exact-specialization'; await (await revealed(p,'specialization')).fill(exact); await save(p);
  console.log('PASS final-profile browser Unicode boundary, bilingual validation, original token and explicit clear');

  stage='two tabs'; operation='open-second-profile-tab';
  const tab=await context.newPage(); diagnosticPage=tab; await tab.goto(base+'/owner_profile.php');
  operation='read-stale-profile-version'; const stale=await version(tab);
  diagnosticPage=p; operation='fill-current-specialization';
  await (await revealed(p,'specialization')).fill('Current private specialization'); await save(p);
  diagnosticPage=tab;
  operation='check-conflicting-email-visibility'; await tab.locator('#public_email_visible').check();
  operation='uncheck-conflicting-phone-visibility'; await (await revealed(tab,'public_phone_visible')).uncheck();
  operation='select-conflicting-country'; await (await revealed(tab,'country_code')).selectOption('EG');
  operation='fill-conflicting-specialization'; await (await revealed(tab,'specialization')).fill('Unsaved private specialization'); await save(tab,409);
  operation='verify-conflict-retention';
  check(await version(tab)===stale && await tab.locator('#country_code').inputValue()==='EG' && await tab.locator('#specialization').inputValue()==='Unsaved private specialization','Conflict lost choices/input/original version');
  check(await tab.locator('#public_email_visible').isChecked() && !await tab.locator('#public_phone_visible').isChecked(),'Conflict discarded independent visibility choices');
  check(await tab.getByRole('link',{name:/Reload saved profile/}).count()===1,'Explicit conflict recovery missing');
  check(await tab.locator('#country_code').isVisible() && await tab.locator('#specialization').isVisible(),'Conflict hid the retained additional details');
  operation='set-mobile-conflict-viewport'; await tab.setViewportSize({width:390,height:844});
  operation='verify-mobile-conflict';
  check(tab.viewportSize().width===390 && tab.viewportSize().height===844,'Conflict screenshot is not at the mobile viewport');
  check(await version(tab)===stale && await tab.locator('#country_code').inputValue()==='EG' && await tab.locator('#preferred_language').inputValue()==='ar' && await tab.locator('#professional_status').inputValue()==='student' && await tab.locator('#specialization').inputValue()==='Unsaved private specialization','Mobile conflict lost choices/input/original version');
  const recovery=tab.getByRole('link',{name:/Reload saved profile/});
  check(await recovery.count()===1 && await recovery.isVisible(),'Mobile conflict recovery missing or hidden');
  operation='capture-mobile-conflict'; await tab.screenshot({path:path.join(shots,'profile-conflict-mobile.png'),fullPage:true});
  operation='close-conflict-tab'; await tab.close();
  diagnosticPage=p; operation='reload-after-conflict'; await p.reload();
  operation='restore-specialization-after-conflict'; await (await revealed(p,'specialization')).fill(exact); await save(p);
  console.log('PASS final-profile browser two-tab conflict retains new choices and original version without replay');

  stage='responsive controls';
  for (const width of [1440,1024,768,390,320]) {
    operation='set-responsive-viewport';
    await p.setViewportSize({width,height:1000});
    for (const id of ['country_code','preferred_language','professional_status','specialization','public_email_visible','public_phone_visible']) {
      operation='inspect-responsive-control-bounds';
      const box=await (await revealed(p,id)).boundingBox(); check(box && box.x>=0 && box.x+box.width<=width+1,'Control exceeds viewport');
      operation='focus-responsive-control'; await p.locator('#'+id).focus();
      operation='verify-responsive-focus';
      check(await p.locator('#'+id).evaluate(e=>document.activeElement===e),'Control cannot receive keyboard focus');
    }
    operation='verify-responsive-overflow'; check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal overflow');
    operation='scroll-country-control'; await p.locator('#country_code').scrollIntoViewIfNeeded();
    operation='capture-responsive-screenshots';
    await p.screenshot({path:path.join(shots,'profile-controls-viewport-'+width+'.png')});
    await p.screenshot({path:path.join(shots,'profile-controls-'+width+'.png'),fullPage:true});
  }
  console.log('PASS final-profile browser synthetic screenshots and keyboard/bilingual controls at 1440/1024/768/390/320px');
} catch(e) {
  console.error('FAIL final-profile browser '+stage+' ['+(e.safeOperation || operation)+']: '+(e.safeMessage || e.name)); process.exitCode=1;
  try { console.error('DIAGNOSTIC final-profile '+JSON.stringify(await failureDiagnostics(diagnosticPage))); }
  catch { console.error('DIAGNOSTIC final-profile unavailable'); }
} finally {
  if (context) await context.close(); if (browser) await browser.close();
  fs.writeFileSync(path.join(evidence,'stop-profile'),'stop');
}
