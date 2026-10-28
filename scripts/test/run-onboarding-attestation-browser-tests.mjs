import {createRequire} from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
import {createHash} from 'node:crypto';
import {execFileSync} from 'node:child_process';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.ATHERCAR_PLAYWRIGHT_MODULE||'playwright');
if(process.env.APP_ENV!=='test'||process.env.ATHERCAR_TEST_MODE!=='1')process.exit(2);
const base=process.env.ATHERCAR_ATTESTATION_BROWSER_BASE_URL;
const original=process.env.ATHERCAR_ATTESTATION_BROWSER_NULL_URL;
const off=process.env.ATHERCAR_ATTESTATION_BROWSER_OFF_URL;
if(![base,original,off].every(url=>/^http:\/\/127\.0\.0\.1:\d+$/.test(url||'')))process.exit(2);
const evidence=process.env.ATHERCAR_EDITOR_EVIDENCE_DIR;
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'private-context.json'),'utf8'));
const shots=path.join(evidence,'screenshots');fs.mkdirSync(shots,{recursive:true});
const endpoint='/owner_onboarding_attestation.php';
const contexts=[];let browser,stage='startup';
function check(ok,message){if(!ok){const error=new Error('Assertion');error.safeMessage=message;throw error;}}
function control(action){execFileSync('docker',['exec',process.env.ATHERCAR_EDITOR_CONTAINER,'php',process.env.ATHERCAR_EDITOR_APP_ROOT+'/scripts/test/run-onboarding-attestation-browser-control.php',fixture.control,action],{stdio:'pipe'});}
async function context(session='a'){
    const c=await browser.newContext({viewport:{width:1440,height:1100}});contexts.push(c);
    await cookie(c,session);return c;
}
async function cookie(c,session){await c.addCookies([{name:'portfolio_owner_session',value:fixture.sessions[session],url:base,httpOnly:true,sameSite:'Lax'}]);}
async function state(p,value){await p.waitForSelector('[data-draft-editor][data-attestation-state="'+value+'"]',{timeout:20000});}
async function saved(p,value='saved'){await p.waitForSelector('[data-draft-editor][data-save-state="'+value+'"]',{timeout:20000});}
async function open(c,url=base,lang='en'){
    const p=await c.newPage();p.stats={posts:[],csrf:[],presentations:[],errors:0};
    p.on('pageerror',()=>p.stats.errors++);
    p.on('request',r=>{if(new URL(r.url()).pathname===endpoint&&r.method()==='POST'){p.stats.posts.push(r.postData());p.stats.csrf.push(r.headers()['x-csrf-token']);}});
    p.on('response',async r=>{if(new URL(r.url()).pathname===endpoint&&r.request().method()==='GET'&&r.status()===200){try{p.stats.presentations.push(await r.json());}catch{}}});
    await p.goto(url+'/owner_onboarding_editor.php?lang='+lang+'&step=1');await saved(p);return p;
}
async function load(p,value='ready'){await p.locator('[data-attestation-load]').click();await state(p,value);}
async function affirm(p){for(const name of ['age_18_or_older','terms_accepted','privacy_notice_acknowledged'])await p.locator('[data-attestation-action="'+name+'"]').check();}
async function unchecked(p){check(await p.locator('[data-attestation-action]:checked').count()===0,'Fresh actions must be unchecked');}
async function language(p,lang){await p.locator('[data-language-switch]').click();await p.waitForFunction(lang=>document.documentElement.lang===lang,lang);}
async function shot(p,name){await p.evaluate(()=>window.scrollTo({top:0,left:0,behavior:'instant'}));await p.screenshot({path:path.join(shots,name),fullPage:true,mask:['[data-profile-name]','h2[data-preview-name]','[data-review-identity]','[data-slug]','[data-review-slug]'].map(s=>p.locator(s))});}
async function draft(c){return(await(await c.request.get(base+'/owner_onboarding_draft.php')).json()).draft;}
try{
    browser=await chromium.launch({headless:true});
    stage='production null policies and off endpoint';
    const c=await context();
    for(const url of [original,off]){
        const p=await open(c,url);await state(p,'unavailable');await unchecked(p);
        check(await p.locator('[data-attestation-record]').isDisabled(),'Unavailable recording');
        check(await p.locator('.ao-document-text').count()===0,'Unavailable must expose no documents');
        await p.setViewportSize({width:390,height:1000});await shot(p,url===original?'null-policies-mobile.png':'endpoint-off-mobile.png');
        await p.setViewportSize({width:1440,height:1100});
        await p.locator('[data-step-jump="2"]').click();
        await p.locator('[data-draft-field="display_name"]').fill('Independent null/off draft');
        await p.locator('[data-next]').click();await saved(p);
        check(p.stats.posts.length===0&&(await draft(c)).fields.display_name==='Independent null/off draft','Unavailable attestations must not break saving');
        await p.close();
    }
    console.log('PASS attestation browser original null policies and endpoint off preserve independent draft saves');

    stage='exact safe documents and independent interface language';
    const p=await open(c);await state(p,'ready');await unchecked(p);
    check(await p.locator('[data-attestation-record]').isDisabled(),'No implicit actions');
    let data=p.stats.presentations.at(-1);check(data,'Observed real server presentation');
    for(const[kind,index]of [['age',0],['terms',1],['privacy',2]]){
        const doc=data.documents[kind];const text=p.locator('.ao-document-text').nth(index);
        check(await text.textContent()===doc.content&&createHash('sha256').update(doc.content).digest('hex')===doc.digest,'Exact bytes/digest preserved in text DOM');
        check(doc.content.includes('\r\n\n  ')&&doc.content.includes('<script>')&&await text.getAttribute('lang')===doc.language,'Whitespace and authoritative language');
    }
    check(await p.locator('.ao-attestation-documents img, .ao-attestation-documents script, .ao-attestation-documents a').count()===0&&await p.evaluate(()=>window.policyXss)===undefined,'Hostile documents cannot create HTML');
    const before=await draft(c);await p.locator('[data-attestation-action]').first().check();
    await language(p,'ar');await state(p,'ready');await unchecked(p);
    check(await p.locator('.ao-document-text').first().getAttribute('lang')==='en'&&await p.evaluate(()=>document.documentElement.dir)==='rtl','UI language must not select document language');
    control('ar');await load(p);check(await p.locator('.ao-document-text').first().getAttribute('lang')==='ar','Server Arabic document language');
    await language(p,'en');await state(p,'ready');
    check(await p.locator('.ao-document-text').first().getAttribute('lang')==='ar'&&(await draft(c)).version===before.version&&(await draft(c)).fields.preferred_language==='fr-CA','UI switching must not save preferred language');
    check(p.stats.posts.length===0,'No automatic POST on page load or language switches');
    console.log('PASS attestation browser exact hostile plain text whitespace and server AR-EN languages survive UI switching safely');

    stage='explicit positive actions navigation and busy controls';
    await unchecked(p);await p.locator('[data-attestation-action]').first().focus();await p.keyboard.press('Space');
    check(await p.locator('[data-attestation-record]').isDisabled(),'One positive action insufficient');
    await p.locator('[data-step-jump="2"]').click();await p.locator('[data-step-jump="1"]').click();
    check(p.stats.posts.length===0,'Navigation and checkbox changes never write');
    await affirm(p);check(await p.locator('[data-attestation-record]').isEnabled(),'All three positive actions enable explicit recording');
    let release,arrive;const hold=new Promise(r=>release=r),arrival=new Promise(r=>arrive=r);
    await p.route('**'+endpoint,async route=>{if(route.request().method()!=='POST')return route.continue();const result=await route.fetch();arrive();await hold;await route.fulfill({response:result});});
    await p.locator('[data-attestation-record]').click();await arrival;await state(p,'recording');
    check(await p.locator('[data-attestation-record]').isDisabled()&&await p.locator('[data-attestation-load]').isDisabled()&&await p.locator('[data-attestation-action]:disabled').count()===3,'Busy controls disable repeated recording');
    await p.locator('[data-attestation-record]').dispatchEvent('click');check(p.stats.posts.length===1,'Repeated busy activation must not create another POST');
    release();await state(p,'recorded');await p.unroute('**'+endpoint);await unchecked(p);
    check(await p.locator('.ao-document-text').count()===3,'Complete reviewed documents must remain visible with recorded evidence');
    const body=JSON.parse(p.stats.posts[0]);check(Object.keys(body).sort().join(',')==='age_18_or_older,presentation_token,privacy_notice_acknowledged,terms_accepted'&&body.age_18_or_older===true&&body.terms_accepted===true&&body.privacy_notice_acknowledged===true,'Exact strict recording envelope');
    check(p.stats.csrf[0]===await p.locator('[data-draft-editor]').getAttribute('data-editor-context'),'POST uses original loaded editor CSRF header');
    const originalStatus=await p.locator('[data-attestation-status]').textContent();
    await shot(p,'recorded-original-time-desktop.png');
    await load(p,'recorded');check(await p.locator('[data-attestation-status]').textContent()===originalStatus,'Original evidence timestamp remains on re-read');
    await p.reload();await saved(p);await state(p,'recorded');await unchecked(p);
    check(await p.locator('[data-attestation-status]').textContent()===originalStatus&&p.stats.posts.length===1,'Reload reads exact evidence without fabricated fresh actions');
    console.log('PASS attestation browser explicit unchecked actions busy controls successful recording and original timestamp without automatic writes');

    stage='changed bundle stale presentation and fresh review';
    control('v2');await load(p);await affirm(p);control('v1');
    await p.locator('[data-attestation-record]').click();await state(p,'stale');await unchecked(p);
    await p.setViewportSize({width:390,height:1000});await shot(p,'stale-fresh-review-mobile.png');
    await p.setViewportSize({width:1440,height:1100});
    check(await p.locator('.ao-document-text').count()===0&&await p.locator('[data-attestation-record]').isDisabled(),'Stale documents retired');
    const stalePosts=p.stats.posts.length;await load(p);await unchecked(p);
    check(p.stats.posts.length===stalePosts&&await p.locator('.ao-document-text').first().textContent()===p.stats.presentations.at(-1).documents.age.content,'Explicit reload obtains fresh docs without carried actions');
    console.log('PASS attestation browser changed documents require explicit fresh unchecked review');

    stage='possible commit and explicit evidence recovery';
    control('v4');await load(p);await affirm(p);control('uncertain');await p.locator('[data-attestation-record]').click();await state(p,'uncertain');
    const uncertainPosts=p.stats.posts.length;control('recover');
    await p.setViewportSize({width:390,height:1000});await shot(p,'uncertain-explicit-reread-mobile.png');await p.setViewportSize({width:1440,height:1100});
    check(await p.locator('[data-attestation-load]').textContent()==='Re-read current evidence','Actionable explicit uncertainty recovery');
    await load(p,'recorded');await unchecked(p);
    check(p.stats.posts.length===uncertainPosts,'No POST replay after possible COMMIT');
    control('v5');await load(p);await affirm(p);
    await p.route('**'+endpoint,async route=>{if(route.request().method()!=='POST')return route.continue();await route.fetch();await route.abort('failed');});
    await p.locator('[data-attestation-record]').click();await state(p,'uncertain');await p.unroute('**'+endpoint);
    const lostPosts=p.stats.posts.length;await load(p,'recorded');
    check(p.stats.posts.length===lostPosts,'Lost response recovered by GET only');
    console.log('PASS attestation browser possible-commit and lost-response outcomes recover only through explicit evidence re-read');

    stage='obsolete GET after language and DOM replacement';
    control('v6');let oldRelease,oldArrive,oldDone;const oldHold=new Promise(r=>oldRelease=r),oldArrival=new Promise(r=>oldArrive=r),oldFinished=new Promise(r=>oldDone=r);let firstGet=true;
    await p.route('**'+endpoint,async route=>{if(route.request().method()!=='GET'||!firstGet)return route.continue();firstGet=false;const result=await route.fetch();oldArrive();await oldHold;try{await route.fulfill({response:result});}catch{}finally{oldDone();}});
    await p.locator('[data-attestation-load]').click();await oldArrival;await state(p,'loading');
    control('ar');await language(p,'ar');await state(p,'recorded');
    oldRelease();await oldFinished;await p.unroute('**'+endpoint);
    check(await p.locator('.ao-document-text').first().getAttribute('lang')==='ar'&&await p.locator('[data-draft-editor]').getAttribute('data-attestation-state')==='recorded','Old GET must not replace newer localized context');
    control('v6');await load(p);const retiredButton=await p.locator('[data-attestation-record]').elementHandle();
    let markupRelease,markupArrive;const markupHold=new Promise(r=>markupRelease=r),markupArrival=new Promise(r=>markupArrive=r);
    await p.route('**/owner_onboarding_editor.php?**',async route=>{const result=await route.fetch();markupArrive();await markupHold;await route.fulfill({response:result});});
    await p.locator('[data-language-switch]').click();await markupArrival;
    check(await p.locator('[data-attestation-load]').isDisabled()&&await p.locator('[data-attestation-record]').isDisabled(),'Language handoff retires protected controls before markup arrives');
    markupRelease();await p.waitForFunction(()=>document.documentElement.lang==='en');await state(p,'ready');await p.unroute('**/owner_onboarding_editor.php?**');
    await affirm(p);const retiredPosts=p.stats.posts.length;
    await retiredButton.evaluate(button=>button.dispatchEvent(new MouseEvent('click',{bubbles:true})));
    check(p.stats.posts.length===retiredPosts&&await p.locator('[data-draft-editor]').getAttribute('data-attestation-state')==='ready','Detached listeners must not record fresh-root actions');
    control('ar');await load(p,'recorded');await language(p,'ar');await state(p,'recorded');
    console.log('PASS attestation browser obsolete presentation responses are retired across language and DOM replacement');

    stage='draft pending operation remains exact and independent';
    await p.locator('[data-step-jump="2"]').click();const draftPosts=[];let lose=true;
    await p.route('**/owner_onboarding_draft.php',async route=>{if(route.request().method()!=='POST')return route.continue();draftPosts.push(route.request().postData());const result=await route.fetch();if(lose){lose=false;await route.abort('failed');}else await route.fulfill({response:result});});
    await p.locator('[data-draft-field="display_name"]').fill('Pending attestation independent');await p.locator('[data-next]').click();await saved(p,'uncertain');
    await p.locator('[data-step-jump="2"]').click();await p.locator('[data-draft-field="display_name"]').fill('Later unsaved exact input');
    await language(p,'en');await state(p,'recorded');
    check(await p.locator('[data-draft-field="display_name"]').inputValue()==='Later unsaved exact input','Unsaved text survives attestation rebind');
    await p.locator('[data-step-jump="1"]').click();await load(p,'recorded');await p.locator('[data-retry]').click();await saved(p);
    check(draftPosts.length===3&&draftPosts[0]===draftPosts[1]&&JSON.parse(draftPosts[2]).operationId!==JSON.parse(draftPosts[0]).operationId&&(await draft(c)).fields.display_name==='Later unsaved exact input','Attestation must not mutate exact pending draft retry');
    check(draftPosts.every(raw=>!raw.includes('presentation_token')&&!raw.includes('terms_accepted')&&!raw.includes('SYNTHETIC TEST ONLY')),'No policy state in draft payload');await p.unroute('**/owner_onboarding_draft.php');
    console.log('PASS attestation browser unsaved draft values and immutable pending operation survive independent attestation and language requests');

    stage='temporary failure missing documents and explicit recovery';
    await p.route('**'+endpoint,route=>route.abort('failed'));await load(p,'temporary');await p.unroute('**'+endpoint);
    control('missing');await load(p,'unavailable');await unchecked(p);control('v7');await load(p);
    check(await p.locator('[data-attestation-status]').getAttribute('role')==='status'&&await p.locator('[data-attestation-status]').getAttribute('aria-live')==='polite','Independent live status');
    console.log('PASS attestation browser temporary and unavailable states retain draft access and require explicit document loading');

    stage='responsive bilingual themes keyboard and redacted screenshots';
    for(const lang of ['ar','en']){
        if(await p.evaluate(()=>document.documentElement.lang)!==lang){await language(p,lang);await state(p,'ready');}
        for(const width of [1440,1024,768,390,320])for(const theme of ['light','dark']){
            await p.setViewportSize({width,height:1100});await p.evaluate(theme=>document.documentElement.dataset.landingTheme=theme,theme);
            check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),'No horizontal overflow');
            if([1440,390].includes(width))await shot(p,lang+'-'+width+'-'+theme+'.png');
        }
    }
    await p.locator('[data-attestation-action]').first().focus();await p.keyboard.press('Space');await p.keyboard.press('Tab');
    check(await p.evaluate(()=>document.activeElement.dataset.attestationAction)==='terms_accepted','Keyboard labels/focus order');
    check(await p.evaluate(()=>Object.values({...localStorage}).every(v=>v==='light'||v==='dark')&&sessionStorage.length===0),'No private browser persistence');
    check(p.stats.errors===0,'No browser script errors');
    console.log('PASS attestation browser keyboard live feedback AR-EN RTL-LTR themes and 1440-1024-768-390-320 layouts');

    stage='account mismatch invalidates old protected responses';
    const auth=await context(),a=await open(auth);await state(a,'ready');
    await a.locator('[data-step-jump="2"]').click();await a.locator('[data-draft-field="display_name"]').fill('Retain after authentication change');
    let draftRelease,draftArrive;const draftHold=new Promise(r=>draftRelease=r),draftArrival=new Promise(r=>draftArrive=r);
    await a.route('**/owner_onboarding_draft.php',async route=>{if(route.request().method()!=='POST')return route.continue();const result=await route.fetch();draftArrive();await draftHold;await route.fulfill({response:result});});
    await a.locator('[data-step-jump="1"]').click();await a.locator('[data-save-exit]').first().click();await draftArrival;
    await cookie(auth,'b');
    // The attestation read is independent of the still-pending draft acknowledgement.
    await a.locator('[data-attestation-load]').click();await state(a,'auth');await saved(a,'auth');
    const lateAck=a.waitForResponse(r=>new URL(r.url()).pathname==='/owner_onboarding_draft.php'&&r.request().method()==='POST');
    draftRelease();await lateAck;await a.unroute('**/owner_onboarding_draft.php');
    check(await a.locator('[data-draft-editor]').getAttribute('data-save-state')==='auth'&&await a.locator('[data-draft-field="display_name"]').inputValue()==='Retain after authentication change','Late draft success cannot unlock an invalidated context');
    check(await a.locator('[data-attestation-record]').isDisabled()&&await a.locator('.ao-document-text').count()===0,'Cross-account results never hydrate old UI');
    console.log('PASS attestation browser cross-account CSRF mismatch locks both boundaries and ignores late draft acknowledgement');

    stage='late attestation POST after authentication invalidation';
    control('v8');const lateContext=await context(),late=await open(lateContext);await state(late,'ready');await affirm(late);
    let postRelease,postArrive,postDone;const postHold=new Promise(r=>postRelease=r),postArrival=new Promise(r=>postArrive=r),postFinished=new Promise(r=>postDone=r);
    await late.route('**'+endpoint,async route=>{if(route.request().method()!=='POST')return route.continue();const result=await route.fetch();postArrive();await postHold;try{await route.fulfill({response:result});}catch{}finally{postDone();}});
    await late.locator('[data-attestation-record]').click();await postArrival;
    await late.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await state(late,'auth');
    postRelease();await postFinished;await late.unroute('**'+endpoint);
    check(await late.locator('[data-draft-editor]').getAttribute('data-attestation-state')==='auth'&&await late.locator('.ao-document-text').count()===0&&late.stats.posts.length===1,'Late committed result must not revive restored/invalid context');
    console.log('PASS attestation browser obsolete POST outcomes cannot revive an invalidated session interface');

    stage='expired access and back-forward restoration';
    control('v7');
    const expired=await context(),e=await open(expired);await state(e,'ready');await cookie(expired,'expired');await load(e,'auth');await saved(e,'auth');
    const cached=await context(),b=await open(cached);await state(b,'ready');
    await b.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await state(b,'auth');await saved(b,'auth');
    check(await b.locator('[data-attestation-action]:checked').count()===0&&await b.locator('[data-attestation-action]:disabled').count()===3,'Restored document must require fresh authentication context');
    console.log('PASS attestation browser expired sessions and persisted-page restoration stop protected interaction without replay');
    fs.writeFileSync(path.join(shots,'README.txt'),'Redacted synthetic document/session/data screenshots only. No token, cookie, raw HTML, HAR or storage state export. Automated Chromium keyboard/live-region/responsive checks are not manual screen-reader, Safari or physical-device certification.\n');
}catch(error){console.error('FAIL attestation browser '+stage+(error.safeMessage?': '+error.safeMessage:': '+(error.name==='TimeoutError'?'timeout waiting for browser condition':'safe infrastructure failure')));process.exitCode=1;}
finally{
    for(const c of contexts)try{await c.close();}catch{}
    if(browser)await browser.close();
    fs.writeFileSync(path.join(evidence,'stop'),'stop');
}
