import {createRequire} from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');
if (process.env.APP_ENV!=='test' || process.env.ATHERCAR_TEST_MODE!=='1') process.exit(2);
const base=process.env.ATHERCAR_EDITOR_BASE_URL;
const off=process.env.ATHERCAR_EDITOR_OFF_URL;
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base||'') || !/^http:\/\/127\.0\.0\.1:\d+$/.test(off||'')) process.exit(2);
const evidence=process.env.ATHERCAR_EDITOR_EVIDENCE_DIR;
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'private-context.json'),'utf8'));
const shots=path.join(evidence,'screenshots'); fs.mkdirSync(shots,{recursive:true});
let browser, stage='startup', parked=false;
const contexts=[];
function check(value,message) { if (!value) { const error=new Error('Assertion'); error.safeMessage=message; throw error; } }
async function context(session='a') {
    const c=await browser.newContext({viewport:{width:1440,height:1100}}); contexts.push(c);
    await c.addCookies([{name:'portfolio_owner_session',value:fixture.sessions[session],url:base,httpOnly:true,sameSite:'Lax'}]); return c;
}
async function open(c,lang='en',step=2) {
    const p=await c.newPage(); await p.goto(base+'/owner_onboarding_editor.php?lang='+lang+'&step='+step);
    await state(p,'saved'); return p;
}
async function state(p,value) { await p.waitForSelector('[data-draft-editor][data-save-state="'+value+'"]',{timeout:20000}); }
async function jump(p,value) { await p.locator('[data-step-jump="'+value+'"]').click({force:true}); }
async function draft(c) { const r=await c.request.get(base+'/owner_onboarding_draft.php'); return (await r.json()).draft; }
const field=(p,key)=>p.locator('[data-draft-field="'+key+'"]').first();
function fault(action) { execFileSync('docker',['exec',process.env.ATHERCAR_EDITOR_CONTAINER,'php',process.env.ATHERCAR_EDITOR_APP_ROOT+'/scripts/run-onboarding-editor-fault.php',action],{stdio:'pipe'}); }
try {
    browser=await chromium.launch({headless:true});
    stage='default-off and authenticated packaging';
    const c=await context();
    check((await c.request.get(off+'/owner_onboarding_editor.php')).status()===404,'Gate off page');
    check((await c.request.get(off+'/owner_onboarding_draft.php')).status()===404,'Gate off API');
    const anonymous=await browser.newContext(); contexts.push(anonymous);
    check((await anonymous.request.get(base+'/owner_onboarding_editor.php')).status()===401,'Anonymous page');
    check((await c.request.post(base+'/owner_onboarding_editor.php')).status()===405,'Page method');
    check((await c.request.get(base+'/owner_onboarding_editor.php?user_id=1')).status()===400,'Page selector');
    for (const asset of ['onboarding.css','onboarding_editor.js','landing.js']) check((await c.request.get(base+'/'+asset)).status()===200,'Packaged asset');
    const emptyContext=await context('b');const emptyPage=await open(emptyContext);check((await emptyPage.locator('[data-save-status]').textContent()).includes('No saved draft yet')&&(await draft(emptyContext)).version===0,'Honest absent draft without row creation');await emptyPage.close();
    const p=await open(c);
    let errors=0; p.on('pageerror',()=>errors++);
    check(await field(p,'country_code').inputValue()==='ZZ','Unknown country');
    check(await p.locator('[data-draft-field="preferred_language"]:checked').inputValue()==='fr-CA','Unknown language');
    check(await field(p,'professional_status').inputValue()==='Research <b>candidate</b>','Unknown status');
    check(await p.locator('input[type=checkbox]:disabled').count()===5,'Disabled consent and visibility');
    check(await p.locator('[data-create]').isDisabled(),'Disabled create');
    check(!(await p.locator('body').textContent()).includes('m••••d@example.com'),'Example email omitted');
    console.log('PASS editor browser default-off packaging, authenticated context and unsupported controls');

    stage='exact partial save clear resume language and safe text';
    const posts=[]; let flights=0,maxFlights=0;
    p.on('request',r=>{ if(r.url().endsWith('/owner_onboarding_draft.php')&&r.method()==='POST'){ posts.push(r.postData()); flights++; maxFlights=Math.max(maxFlights,flights); }});
    const done=r=>{if(r.url().endsWith('/owner_onboarding_draft.php')&&r.method()==='POST')flights--;}; p.on('requestfinished',done);p.on('requestfailed',done);
    const exact='  Synthetic <img src=x onerror="window.editorXss=1">  ';
    await field(p,'display_name').fill(exact); await p.locator('[data-next]').click(); await state(p,'saved');
    await p.waitForSelector('[data-step-panel="3"]:not([hidden])');
    check(await p.evaluate(()=>document.activeElement.id)==='step-3-heading','Continue heading focus');
    check((await draft(c)).fields.display_name===exact,'Exact save');
    check(Object.keys(JSON.parse(posts[0]).fields).join(',')==='display_name','Omit untouched fields');
    check(await p.locator('h2[data-preview-name]').textContent()===exact && await p.locator('h2[data-preview-name] img').count()===0 && await p.evaluate(()=>window.editorXss)===undefined,'Safe text rendering');
    stage='deliberate clear'; await field(p,'headline').fill(''); await field(p,'specialization').fill('Exact تخصص ');
    await p.locator('[data-next]').click(); await state(p,'saved');
    const cleared=await draft(c); check(!Object.hasOwn(cleared.fields,'headline')&&cleared.fields.specialization==='Exact تخصص ','Deliberate clear and exact string');
    stage='language switching'; await field(p,'slug_candidate').fill('draft-candidate');
    await p.locator('[data-language-switch]').click(); await p.waitForFunction(()=>document.documentElement.lang==='ar');
    check(await field(p,'slug_candidate').inputValue()==='draft-candidate' && await p.evaluate(()=>document.documentElement.dir)==='rtl','AR unsaved preservation');
    await p.locator('[data-language-switch]').click(); await p.waitForFunction(()=>document.documentElement.lang==='en');
    check(await field(p,'slug_candidate').inputValue()==='draft-candidate','EN unsaved preservation');
    await p.locator('[data-next]').click(); await state(p,'saved'); await p.reload(); await state(p,'saved');
    check(await field(p,'slug_candidate').inputValue()==='draft-candidate','Reload resume');
    stage='resumed unrepresented fields'; const values=await draft(c); for(const [key,value]of Object.entries({country_code:'ZZ',preferred_language:'fr-CA',professional_status:'Research <b>candidate</b>',name_ar:'Untouched Arabic',city:'Untouched city',university:'Untouched university',phone:'Untouched private phone'}))check(values.fields[key]===value,'Preserve unrepresented/unknown values');
    console.log('PASS editor browser exact partial saves, deliberate clear, reload, localized unsaved values and safe text');

    stage='save and exit acknowledgement';
    await jump(p,2); await field(p,'display_name').fill('Saved Exit');
    await jump(p,3); await field(p,'headline').fill('Exit headline');
    await jump(p,4); await field(p,'slug_candidate').fill('exit-candidate');
    let exitRelease,exitArrived; const exitHold=new Promise(r=>exitRelease=r); const exitArrival=new Promise(r=>exitArrived=r); const exitSections=[];
    await p.route('**/owner_onboarding_draft.php',async route=>{
        if(route.request().method()!=='POST')return route.continue();
        const section=JSON.parse(route.request().postData()).section; exitSections.push(section);
        const result=await route.fetch(); if(section==='profile_identity'){exitArrived();await exitHold;} await route.fulfill({response:result});
    });
    const exitRequest=p.waitForRequest(r=>new URL(r.url()).pathname==='/owner.php');
    await p.locator('[data-save-exit]').last().click(); await exitArrival;
    check(new URL(p.url()).pathname==='/owner_onboarding_editor.php','Wait for all section acknowledgements before exit');
    exitRelease(); await exitRequest; await p.unroute('**/owner_onboarding_draft.php');
    const exitDraft=await draft(c);
    check(exitSections.join(',')==='public_identity,professional_snapshot,profile_identity'&&exitDraft.fields.display_name==='Saved Exit'&&exitDraft.fields.headline==='Exit headline'&&exitDraft.fields.slug_candidate==='exit-candidate','All intended sections acknowledged');
    await p.goto(base+'/owner_onboarding_editor.php?lang=en&step=2'); await state(p,'saved');
    console.log('PASS editor browser save-and-exit uses fixed local destination only after acknowledgement');

    stage='two tab conflict and explicit recovery';
    const tab=await open(c); await field(p,'display_name').fill('Tab one'); await p.locator('[data-next]').click(); await state(p,'saved');
    await field(tab,'display_name').fill('Tab two'); await tab.locator('[data-next]').click(); await state(tab,'conflict');
    check(await field(tab,'display_name').inputValue()==='Tab two','Conflict retains edits');
    check(await tab.locator('[data-next]').isDisabled(),'Conflict prevents blind retry');
    tab.once('dialog',d=>d.dismiss()); await tab.locator('[data-recover]').click(); check(await field(tab,'display_name').inputValue()==='Tab two','Recovery cancellation');
    tab.once('dialog',d=>d.accept()); await tab.locator('[data-recover]').click(); await state(tab,'saved');
    check(await field(tab,'display_name').inputValue()==='Tab one','Explicit server replacement'); await tab.close();
    console.log('PASS editor browser two-tab conflict retains edits and requires explicit replacement');

    stage='uncertain exact retry and later edits';
    await jump(p,2); let first=true; const retryPosts=[];
    await p.route('**/owner_onboarding_draft.php',async route=>{
        if(route.request().method()!=='POST')return route.continue();
        retryPosts.push(route.request().postData()); const result=await route.fetch();
        if(first){first=false;await route.abort('failed');}else await route.fulfill({response:result});
    });
    await field(p,'display_name').fill('Uncertain first'); await p.locator('[data-next]').click(); await state(p,'uncertain');
    await field(p,'display_name').fill('Subsequent edit'); await p.locator('[data-language-switch]').click(); await p.waitForFunction(()=>document.documentElement.lang==='ar'); await p.locator('[data-retry]').click(); await state(p,'saved');
    check(retryPosts.length===3&&retryPosts[0]===retryPosts[1]&&JSON.parse(retryPosts[2]).operationId!==JSON.parse(retryPosts[0]).operationId,'Exact immutable retry receipt');
    check((await draft(c)).fields.display_name==='Subsequent edit' && await field(p,'display_name').inputValue()==='Subsequent edit','Later edits preserved');
    await p.unroute('**/owner_onboarding_draft.php');
    console.log('PASS editor browser uncertain committed response retries exact payload/ID before newer edits');

    stage='late acknowledgement and serial queue';
    let release,arrived; const arrival=new Promise(r=>arrived=r); const hold=new Promise(r=>release=r); let delay=true;
    await p.route('**/owner_onboarding_draft.php',async route=>{if(route.request().method()!=='POST')return route.continue();const result=await route.fetch();if(delay){delay=false;arrived();await hold;}await route.fulfill({response:result});});
    await field(p,'display_name').fill('Late A'); await p.locator('[data-next]').click(); await arrival;
    await field(p,'display_name').fill('Late B'); release(); await state(p,'saved');
    check((await draft(c)).fields.display_name==='Late B'&&await field(p,'display_name').inputValue()==='Late B','Late response preserves newer edits');
    await p.unroute('**/owner_onboarding_draft.php'); check(maxFlights===1,'One POST in flight');
    console.log('PASS editor browser late acknowledgements preserve edits and serialize necessary saves');

    stage='real temporary database failure exact retry';
    await jump(p,2); await field(p,'display_name').fill('Temporary failure');
    fault('park');parked=true;
    try {await p.locator('[data-next]').click();await state(p,'uncertain');}finally{fault('restore');parked=false;}
    const beforeRetry=posts.at(-1);await p.locator('[data-retry]').click();await state(p,'saved');check(posts.at(-1)===beforeRetry,'Temporary failure exact retry');
    console.log('PASS editor browser real HTTP database failure retains pending operation for safe retry');

    stage='expired session and account switch locks';
    const e=await context();const expiredPage=await open(e);await field(expiredPage,'display_name').fill('Must not save');
    await e.addCookies([{name:'portfolio_owner_session',value:fixture.sessions.expired,url:base,httpOnly:true,sameSite:'Lax'}]);
    await expiredPage.locator('[data-next]').click();await state(expiredPage,'auth');check(await field(expiredPage,'display_name').isDisabled(),'Expired session locks editor');
    const switched=await context();const switchedPage=await open(switched);await field(switchedPage,'display_name').fill('Old account edits');
    await switched.addCookies([{name:'portfolio_owner_session',value:fixture.sessions.b,url:base,httpOnly:true,sameSite:'Lax'}]);
    await switchedPage.locator('[data-next]').click();await state(switchedPage,'auth');check((await draft(switched)).version===0,'No values sent to switched account');
    const translated=await context();const translatedPage=await open(translated);await field(translatedPage,'display_name').fill('Keep local account values');
    await translated.addCookies([{name:'portfolio_owner_session',value:fixture.sessions.b,url:base,httpOnly:true,sameSite:'Lax'}]);
    await translatedPage.locator('[data-language-switch]').click();await state(translatedPage,'auth');check(await field(translatedPage,'display_name').inputValue()==='Keep local account values','Language context mismatch cannot replace values');
    console.log('PASS editor browser expired and switched accounts stop saving without token reacquisition');

    stage='AR EN responsive themes focus and screenshots';
    const visual=await context();const v=await open(visual,'en',2);
    for(const lang of ['en','ar'])for(const width of [1440,390,320])for(const theme of ['light','dark']){
        await v.setViewportSize({width,height:width===1440?1100:1000});
        await v.goto(base+'/owner_onboarding_editor.php?lang='+lang+'&step=2');await state(v,'saved');
        await v.evaluate(theme=>document.documentElement.dataset.landingTheme=theme,theme);
        await v.evaluate(()=>document.fonts.ready);
        check(await v.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow');
        check(await v.evaluate(()=>document.documentElement.dir)===(lang==='ar'?'rtl':'ltr'),'Language direction');
        check(await v.locator('[data-save-status]').getAttribute('aria-live')==='polite','Accessible status');
        await v.screenshot({path:path.join(shots,`${lang}-${width}-${theme}.png`),fullPage:true});
        for(const section of [1,3,4,5]) {
            await v.goto(base+'/owner_onboarding_editor.php?lang='+lang+'&step='+section);await state(v,'saved');
            await v.evaluate(theme=>document.documentElement.dataset.landingTheme=theme,theme);
            check(await v.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'All sections responsive without overflow');
            if(lang==='ar'&&width===320&&theme==='dark'&&(section===3||section===4))await v.screenshot({path:path.join(shots,`ar-320-dark-step-${section}.png`),fullPage:true});
        }

    }
    await v.setViewportSize({width:1440,height:1100});await v.goto(base+'/owner_onboarding_editor.php?lang=ar&step=1');await state(v,'saved');
    await v.screenshot({path:path.join(shots,'ar-readiness-disabled.png'),fullPage:true});
    await v.goto(base+'/owner_onboarding_editor.php?lang=en&step=5');await state(v,'saved');await v.screenshot({path:path.join(shots,'en-review-create-disabled.png'),fullPage:true});
    await v.goto(base+'/owner_onboarding_editor.php?lang=en&step=2');await state(v,'saved');await field(v,'display_name').focus();await v.keyboard.press('Tab');check(await v.evaluate(()=>document.activeElement.dataset.draftField)==='country_code','Keyboard field order');
    check(await v.evaluate(()=>Object.values({...localStorage}).every(value=>value==='light'||value==='dark')&&sessionStorage.length===0),'No private browser persistence');
    check(errors===0,'No script execution errors');
    fs.writeFileSync(path.join(shots,'README.txt'),'Synthetic sessions/data only. Tokens, cookies and operation receipts are not rendered or exported in screenshots. No HAR, HTML dumps or storage state is retained.\n');
    console.log('PASS editor browser AR/EN RTL/LTR desktop/mobile/320px themes keyboard focus live status and screenshots');
    fs.writeFileSync(path.join(evidence,'browser-results.json'),JSON.stringify({passed:true,groups:9,screenshots:16,private_tokens:'Not rendered/exported',post_requests_serial:true},null,2));
}catch(_error){console.error('FAIL editor browser at '+stage+(_error.safeMessage ? ': '+_error.safeMessage : ' ('+_error.name+')'));process.exitCode=1;}
finally{if(parked){try{fault('restore');}catch{process.exitCode=1;}}for(const c of contexts)await c.close().catch(()=>{});if(browser)await browser.close();fs.writeFileSync(path.join(evidence,'stop'),'stop');}
