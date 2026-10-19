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
let browser, context, stage='startup';
function check(value,message) { if (!value) { const e=new Error('Assertion'); e.safeMessage=message; throw e; } }
const form=p=>p.locator('form').filter({has:p.locator('input[name="action"][value="save_profile"]')});
const version=p=>form(p).locator('[name="expected_profile_version"]').inputValue();
async function save(p,status=200) {
  const [response]=await Promise.all([p.waitForNavigation(),form(p).getByRole('button',{name:'Save Changes',exact:true}).click()]);
  check(response.status()===status,'Unexpected profile navigation status');
}
try {
  browser=await chromium.launch({headless:true});
  context=await browser.newContext({viewport:{width:1440,height:1000}});
  await context.addCookies([{name:fixture.cookie.Name,value:fixture.cookie.Value,url:base,httpOnly:true,sameSite:'Lax'}]);
  const p=await context.newPage(); await p.goto(base+'/owner_profile.php');
  stage='retired choices';
  check(await p.locator('#country_code').inputValue()==='ZZ','Retired country was coerced');
  check(await p.locator('#country_code option').count()===251,'Country catalog count');
  for (const id of ['country_code','preferred_language','professional_status','specialization']) {
    check(await p.locator('label[for="'+id+'"]').count()===1,'Missing label');
    check(await p.locator('#'+id).getAttribute('aria-describedby')===id+'-help','Missing accessible explanation');
  }
  await p.locator('#professional_title').fill('Reviewed unchanged retirement'); await save(p);
  check(await p.locator('#country_code').inputValue()==='ZZ' && await p.locator('#professional_status').inputValue()==='retired_status','Unrelated edit lost retired values');
  console.log('PASS final-profile browser complete country catalog, AR/EN labels/help and unchanged retired choices');

  stage='exact values';
  const exact='  تخصص e\u0301 😀  ';
  await p.locator('#country_code').selectOption('JO'); await p.locator('#preferred_language').selectOption('ar');
  await p.locator('#professional_status').selectOption('student'); await p.locator('#specialization').fill(exact); await save(p);
  await p.reload();
  check(await p.locator('#specialization').inputValue()===exact,'Text was normalized or trimmed');
  check(await p.locator('#location').inputValue()==='Original location' && await p.locator('#professional_title').inputValue()==='Reviewed unchanged retirement','New fields overwrote legacy meaning');
  check(await p.locator('#preferred_language').inputValue()==='ar' && await p.locator('html').getAttribute('lang')==='en','Preference changed interface language');
  console.log('PASS final-profile browser real saves/reload preserve exact mixed Unicode and distinct legacy fields');

  stage='boundaries and errors';
  await p.locator('#specialization').fill('😀'.repeat(150)); await save(p);
  check(await p.locator('#specialization').inputValue()==='😀'.repeat(150),'HTML UTF-16 maxlength blocked valid 150 code points');
  const token=await version(p); await p.locator('#specialization').fill('   '); await save(p,422);
  check(await p.locator('#specialization').inputValue()==='   ' && await version(p)===token && await p.locator('#specialization').getAttribute('aria-invalid')==='true','Validation lost input/token/accessibility');
  await p.screenshot({path:path.join(shots,'profile-error-desktop.png'),fullPage:true});
  await p.locator('#specialization').fill(''); await save(p);
  check(await p.locator('#specialization').inputValue()==='','Explicit clear not reflected');
  await p.locator('#specialization').fill(exact); await save(p);
  console.log('PASS final-profile browser Unicode boundary, bilingual validation, original token and explicit clear');

  stage='two tabs'; const tab=await context.newPage(); await tab.goto(base+'/owner_profile.php'); const stale=await version(tab);
  await p.locator('#specialization').fill('Current private specialization'); await save(p);
  await tab.locator('#country_code').selectOption('EG'); await tab.locator('#specialization').fill('Unsaved private specialization'); await save(tab,409);
  check(await version(tab)===stale && await tab.locator('#country_code').inputValue()==='EG' && await tab.locator('#specialization').inputValue()==='Unsaved private specialization','Conflict lost choices/input/original version');
  check(await tab.getByRole('link',{name:/Reload saved profile/}).count()===1,'Explicit conflict recovery missing');
  await tab.setViewportSize({width:390,height:844});
  check(tab.viewportSize().width===390 && tab.viewportSize().height===844,'Conflict screenshot is not at the mobile viewport');
  check(await version(tab)===stale && await tab.locator('#country_code').inputValue()==='EG' && await tab.locator('#preferred_language').inputValue()==='ar' && await tab.locator('#professional_status').inputValue()==='student' && await tab.locator('#specialization').inputValue()==='Unsaved private specialization','Mobile conflict lost choices/input/original version');
  const recovery=tab.getByRole('link',{name:/Reload saved profile/});
  check(await recovery.count()===1 && await recovery.isVisible(),'Mobile conflict recovery missing or hidden');
  await tab.screenshot({path:path.join(shots,'profile-conflict-mobile.png'),fullPage:true}); await tab.close();
  await p.reload(); await p.locator('#specialization').fill(exact); await save(p);
  console.log('PASS final-profile browser two-tab conflict retains new choices and original version without replay');

  stage='responsive controls';
  for (const width of [1440,1024,768,390,320]) {
    await p.setViewportSize({width,height:1000});
    for (const id of ['country_code','preferred_language','professional_status','specialization']) {
      const box=await p.locator('#'+id).boundingBox(); check(box && box.x>=0 && box.x+box.width<=width+1,'Control exceeds viewport');
      await p.locator('#'+id).focus();
      check(await p.locator('#'+id).evaluate(e=>document.activeElement===e),'Control cannot receive keyboard focus');
    }
    check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal overflow');
    await p.locator('#country_code').scrollIntoViewIfNeeded();
    await p.screenshot({path:path.join(shots,'profile-controls-viewport-'+width+'.png')});
    await p.screenshot({path:path.join(shots,'profile-controls-'+width+'.png'),fullPage:true});
  }
  console.log('PASS final-profile browser synthetic screenshots and keyboard/bilingual controls at 1440/1024/768/390/320px');
} catch(e) {
  console.error('FAIL final-profile browser '+stage+': '+(e.safeMessage || e.name)); process.exitCode=1;
} finally {
  if (context) await context.close(); if (browser) await browser.close();
  fs.writeFileSync(path.join(evidence,'stop-profile'),'stop');
}
