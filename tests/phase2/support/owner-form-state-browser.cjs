'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const http=require('node:http');
const {execFileSync}=require('node:child_process');
const {chromium}=require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');
const root=path.resolve(__dirname,'../../..');
const output=process.env.ATHER_VISUAL_OUT;
if(!output)throw new Error('Set ATHER_VISUAL_OUT to an isolated evidence directory.');
fs.mkdirSync(output,{recursive:true});
const baseline=process.argv.includes('--baseline');
const group=process.env.ATHER_FORM_TEST_GROUP || 'all';
const script=baseline?execFileSync('git',['show','07e65bd0380da695a1cd7da1fbc2bfdb69645d41:admin.js'],{cwd:root}):fs.readFileSync(path.join(root,'admin.js'));
const html=new Map();
for(const locale of ['ar','en'])for(const page of ['dashboard','profile','projects','experiences','messages','publication','evidence_hub']){
 html.set(locale+'/'+page,execFileSync(process.env.ATHER_PHP_BINARY || 'php',[path.join(__dirname,'owner-form-state-visual.php'),locale,page]));
}
const posts=[];
const checks=[];
const history=[];
let browser,server;
const snapshot=button=>button.evaluate(e=>({html:e.innerHTML,value:e.value,disabled:e.disabled,aria:e.getAttribute('aria-disabled'),width:e.style.getPropertyValue('min-width'),priority:e.style.getPropertyPriority('min-width')}));
async function saveNodes(button){await button.evaluate(e=>{window.__originalButton=e;window.__originalChildren=Array.from(e.childNodes);window.__originalSvg=e.querySelector('svg');window.__originalSpan=e.querySelector('span');});}
async function sameNodes(button){assert.equal(await button.evaluate(e=>window.__originalButton===e&&window.__originalChildren.every((n,i)=>e.childNodes[i]===n)&&e.childNodes.length===window.__originalChildren.length&&e.querySelector('svg')===window.__originalSvg&&e.querySelector('span')===window.__originalSpan),true,'original DOM node identity');}
async function simulatedRestore(page,form,button,label){
 const original=await snapshot(button);const svgCount=await button.locator('svg').count();await saveNodes(button);
 await form.evaluate(f=>{window.__submitStates=[];f.addEventListener('submit',e=>{window.__submitStates.push(e.defaultPrevented);e.preventDefault();});});
 await button.evaluate(e=>e.form.requestSubmit(e));
 assert.equal(await button.isDisabled(),true,label+' pending disabled');
 assert.equal(await button.locator('svg').count(),svgCount,label+' SVG count while pending');
 if(svgCount)assert.equal(await button.locator('svg').evaluate(e=>e===window.__originalSvg),true,label+' SVG retained while pending');
 if(await button.locator('span').count())assert.equal(await button.locator('span').first().evaluate(e=>e===window.__originalSpan),true,label+' span retained while pending');
 const pending=await button.getAttribute('data-pending-label') || 'Saving…';
 assert.equal(await button.evaluate(e=>e.tagName==='INPUT'?e.value:e.textContent.replace('Keep SVG title','').trim()),pending,label+' pending label');
 await button.evaluate(e=>e.form.requestSubmit());
 assert.deepEqual(await page.evaluate(()=>window.__submitStates),[false,true],label+' duplicate prevented');
 await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));
 assert.deepEqual(await snapshot(button),original,label+' exact restored label/enabled/ARIA/width');await sameNodes(button);
 assert.equal(await form.getAttribute('aria-busy'),null);
 await button.evaluate(e=>e.form.requestSubmit(e));
 assert.deepEqual(await page.evaluate(()=>window.__submitStates),[false,true,false],label+' resubmit after restore');
 await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));
 checks.push({check:label,result:'PASS',restore:'synthetic pageshow; isTrusted=false'});
}
(async()=>{
 server=http.createServer((req,res)=>{
  const url=new URL(req.url,'http://127.0.0.1');
  if(req.method==='POST'){
   let body='';req.on('data',d=>body+=d);req.on('end',()=>{
    const fields=new URLSearchParams(body);posts.push({method:req.method,path:url.pathname,csrfMatches:fields.get('csrf_token')==='b'.repeat(64),fields:[...fields.keys()]});
    setTimeout(()=>{res.writeHead(303,{Location:'/result'});res.end();},350);
   });return;
  }
  if(url.pathname==='/result'){res.writeHead(200,{'Content-Type':'text/html; charset=utf-8'});res.end('<!doctype html><title>Synthetic POST result</title><p>Presentation test only; no account handler executed.</p>');return;}
  if(url.pathname==='/owner.php'){
   const locale=url.searchParams.get('lang')==='en'?'en':'ar',page=url.searchParams.get('page')||'dashboard';const content=html.get(locale+'/'+page);
   if(!content){res.writeHead(404);res.end();return;}res.writeHead(200,{'Content-Type':'text/html; charset=utf-8','Cache-Control':'private, no-store'});res.end(content);return;
  }
  if(url.pathname==='/admin.js'){res.writeHead(200,{'Content-Type':'text/javascript'});res.end(script);return;}
  const file=path.resolve(root,'.'+url.pathname);if(!file.startsWith(root+path.sep)||!fs.existsSync(file)||!['.css','.js','.png','.ttf'].includes(path.extname(file))){res.writeHead(404);res.end();return;}
  const types={'.css':'text/css','.js':'text/javascript','.png':'image/png','.ttf':'font/ttf'};res.writeHead(200,{'Content-Type':types[path.extname(file)]});fs.createReadStream(file).pipe(res);
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));const base='http://127.0.0.1:'+server.address().port;
 // Allow an actual browser history/BFCache return, and report persisted rather than assuming it.
 browser=await chromium.launch({headless:true,ignoreDefaultArgs:['--disable-back-forward-cache']});
 for(const locale of ['ar','en'])for(const width of [390,320]){
  const context=await browser.newContext({viewport:{width,height:844},deviceScaleFactor:1});
  await context.addInitScript(()=>{window.__pageShows=[];window.addEventListener('pageshow',e=>window.__pageShows.push({persisted:e.persisted,trusted:e.isTrusted}));});
  const p=await context.newPage();const errors=[];p.on('pageerror',e=>errors.push(e.message));
  const url=base+'/owner.php?lang='+locale;
  const visit=async(suffix='')=>{await p.goto(url+suffix,{waitUntil:'networkidle'});await p.evaluate(()=>document.fonts.ready);};
  if(group!=='buttons'){
   await visit();const toggle=p.locator('[data-owner-nav-toggle]'),nav=p.locator('#dashboard-navigation');
   for(const target of ['toggle','link','switch','logout']){
    await toggle.click();assert.equal(await toggle.getAttribute('aria-expanded'),'true');assert.equal(await nav.isVisible(),true);
    const focused=target==='toggle'?toggle:target==='link'?nav.locator('nav a').first():nav.locator('form[action="/owner_'+(target==='switch'?'switch_account':'logout')+'.php"] button');
    await focused.focus();await p.keyboard.press('Escape');assert.equal(await toggle.getAttribute('aria-expanded'),'false',locale+width+target+' ARIA');assert.equal(await nav.isVisible(),false,locale+width+target+' visibility');assert.equal(await toggle.evaluate(e=>e===document.activeElement),true,locale+width+target+' focus');
   }
   await toggle.click();await toggle.focus();await p.keyboard.press('Tab');assert.equal(await nav.locator('nav a').first().evaluate(e=>e===document.activeElement),true);await p.keyboard.press('Shift+Tab');assert.equal(await toggle.evaluate(e=>e===document.activeElement),true);await p.keyboard.press('Escape');
   checks.push({check:locale+' '+width+' Escape toggle/nav/both account buttons; Tab/Shift+Tab',result:'PASS'});
  }
  if(group!=='menu'){
   for(const action of ['switch_account','logout']){
    await visit();await p.locator('[data-owner-nav-toggle]').click();const form=p.locator('form[action="/owner_'+action+'.php"]'),button=form.locator('button');
    assert.equal(await form.getAttribute('method'),'POST');const before=posts.length;await simulatedRestore(p,form,button,locale+' '+width+' '+action);assert.equal(posts.length,before,'simulation sent no request');
    // A fresh real submission to the local synthetic sink, followed by browser.goBack().
    await visit();await p.locator('[data-owner-nav-toggle]').click();const original=await snapshot(button);await saveNodes(button);const sent=posts.length;
    await form.evaluate(f=>f.addEventListener('submit',e=>{if(!e.defaultPrevented&&!window.__duplicateRequested){window.__duplicateRequested=true;f.requestSubmit();}}));
    await button.click();await p.waitForURL(base+'/result');assert.equal(posts.length,sent+1,'one actual POST');assert.deepEqual(posts.at(-1),{method:'POST',path:'/owner_'+action+'.php',csrfMatches:true,fields:['csrf_token']});
    await p.goBack({waitUntil:'networkidle'});await p.waitForURL(url);assert.deepEqual(await snapshot(button),original,'real browser back restore');
    const event=await p.evaluate(()=>window.__pageShows.at(-1));assert.equal(event.trusted,true);if(event.persisted)await sameNodes(button);
    history.push({locale,width,action,actualBrowserBack:true,pageshow:event,onePost:true,csrfUnchanged:true,restored:true});
   }
  }
  assert.deepEqual(errors,[]);await context.close();
  if(group!=='buttons'){
   const nojs=await browser.newContext({javaScriptEnabled:false,viewport:{width,height:844}});const p=await nojs.newPage();await p.goto(url);assert.equal(await p.locator('#dashboard-navigation').isVisible(),true);assert.equal(await p.locator('#dashboard-navigation nav a').count(),7);assert.equal(await p.locator('[data-owner-nav-toggle]').isVisible(),false);await nojs.close();checks.push({check:locale+' '+width+' no-JS navigation',result:'PASS'});
  }
 }
 if(group!=='menu'){
  const c=await browser.newContext({viewport:{width:1440,height:1000}});const p=await c.newPage();
  for(const page of ['profile','projects','experiences','messages','evidence_hub']){
   await p.goto(base+'/owner.php?lang=en&page='+page,{waitUntil:'networkidle'});
   for(const action of ['switch_account','logout']){
    const form=p.locator('form[action="/owner_'+action+'.php"]');await simulatedRestore(p,form,form.locator('button'),page+' shared '+action);
   }
  }
  for(const selector of ['#plain-submit','#nested-submit','#input-submit','#icon-submit']){
   await p.goto(base+'/owner.php?lang=en&page=profile',{waitUntil:'networkidle'});const form=p.locator('#shared-form'),button=p.locator(selector);await simulatedRestore(p,form,button,selector);assert.equal(await p.locator('#disabled-control').isDisabled(),true);
  }
  await p.goto(base+'/owner.php?lang=en&page=profile',{waitUntil:'networkidle'});await p.locator('#required-field').fill('');await p.locator('#plain-submit').click();assert.equal(await p.locator('#plain-submit').isEnabled(),true);assert.equal(await p.locator('#shared-form').getAttribute('aria-busy'),null);checks.push({check:'invalid shared form stays enabled',result:'PASS'});
  await p.goto(base+'/owner.php?lang=en&page=publication',{waitUntil:'networkidle'});const form=p.locator('.publication-destructive-action');await form.locator('button').click();assert.equal(await p.locator('.confirm-modal').isVisible(),true);assert.equal(await form.getAttribute('aria-busy'),null);await p.locator('.confirm-cancel').click();assert.equal(await form.locator('button').isEnabled(),true);
  await form.evaluate(f=>f.addEventListener('submit',e=>e.preventDefault()));await form.locator('button').click();await p.locator('.confirm-ok').click();assert.equal(await form.locator('button').isDisabled(),true);await p.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));assert.equal(await form.locator('button').isEnabled(),true);checks.push({check:'actual Publication confirmation cancel/confirm and simulated restore',result:'PASS'});
  await c.close();
 }
 fs.writeFileSync(path.join(output,'RESULT.json'),JSON.stringify({result:'PASS',baseline,engine:await browser.version(),scope:'CLI-rendered real shared chrome + representative control structures; isolated Node POST sink, no authentication/account/database handlers',checks,history},null,2));
 console.log('PASS 11-A menu and form-state regressions; actual browser history results recorded.');
})().catch(e=>{fs.writeFileSync(path.join(output,'FAILED.txt'),e.stack);console.error(e.stack);process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();if(server)await new Promise(r=>server.close(r));});
