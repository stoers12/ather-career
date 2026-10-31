'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
// Additional FE-07 matrix, invoked by the standalone page visual driver.
module.exports=async function evidenceViewsVisual(browser,base,output){
 await require('./evidence-hub-navigation-checks.cjs')(browser,base,output);
 const pageUrl=(locale,kind)=>`${base}/tests/Browser/${kind==='hub'?'evidence-hub-page-visual.php?variant=partial':`owner-project-evidence-visual.php?mode=${kind==='write'?'write':'read'}`}&lang=${locale}`;
 for(const locale of ['ar','en'])for(const theme of ['light','dark'])for(const large of [false,true]){
  const context=await browser.newContext({viewport:{width:1440,height:1052},reducedMotion:'reduce',hasTouch:true});
  await context.addInitScript(({theme,large})=>{localStorage.setItem('ather.evidenceHub.theme',theme);if(large)localStorage.setItem('ather.owner.textSize','large')},{theme,large});
  const page=await context.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
  for(const width of [390,768,1280,1920,2560]){
   await page.setViewportSize({width,height:1052});
   for(const kind of ['hub','read','write']){
    const response=await page.goto(pageUrl(locale,kind),{waitUntil:'networkidle'});assert.equal(response.status(),200);
    assert.equal(await page.locator('html').getAttribute('dir'),locale==='ar'?'rtl':'ltr');
    if(large)assert.equal(await page.locator('body').getAttribute('data-owner-text-size'),'large');
    if(kind==='write' && width>=1280)assert.ok((await page.locator('.evidence-hub-writing-paper').boundingBox()).width > (await page.locator('.admin-content > section').boundingBox()).width*.65, 'Desktop writing paper uses the available column');
    const panels=kind==='hub'?['records','insights']:kind==='write'?['problem','personal_role','measurable_outcome','sources-after-save']:['read'];
    for(const panel of panels){
     if(kind==='hub')await page.locator(`[data-evidence-layer=${panel}]`).click();
     if(kind==='write')await page.locator(`[data-evidence-step=${panel}]`).click();
     assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth && document.body.scrollWidth<=innerWidth),true,`${locale}/${theme}/${large}/${width}/${kind}/${panel}`);
     const smallTargets=await page.locator('.admin-content :is(a,button,summary)').evaluateAll(nodes=>nodes.filter(node=>node.getClientRects().length && !node.closest('[inert]')).map(node=>{const r=node.getBoundingClientRect();return {tag:node.tagName,width:r.width,height:r.height};}).filter(r=>r.width<44 || r.height<44));
     assert.deepEqual(smallTargets,[],`${kind}/${panel}: coarse targets`);
    }
   }
  }
  assert.deepEqual(errors,[]);await context.close();
 }
 // Delivery captures: all reference screens, then every requested extra state.
 const capture=async(name,url,width,theme='light',js=true,action=null)=>{
  const context=await browser.newContext({viewport:{width,height:1052},javaScriptEnabled:js,reducedMotion:'reduce',colorScheme:theme});
  if(js)await context.addInitScript(t=>localStorage.setItem('ather.evidenceHub.theme',t),theme);
  const page=await context.newPage();assert.equal((await page.goto(url,{waitUntil:'networkidle'})).status(),200);
  await page.evaluate(()=>document.fonts.ready);if(action)await action(page);await page.evaluate(()=>window.scrollTo(0,0));await page.waitForTimeout(200);
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,name);
  await page.screenshot({path:path.join(output,name+'.png'),fullPage:true});await context.close();
 };
 for(const theme of ['light','dark']){
  for(const [label,kind]of [['10-evidence','hub'],['11-evidence-read','read'],['12-evidence-write','write']])await capture(`reference-desktop-ar-${theme}-${label}`,pageUrl('ar',kind),1440,theme);
  await capture(`reference-desktop-en-${theme}-02-evidence`,pageUrl('en','hub'),1440,theme);
  await capture(`reference-mobile-ar-${theme}-04-evidence`,pageUrl('ar','hub'),390,theme);
  await capture(`reference-mobile-ar-${theme}-05-evidence-write`,pageUrl('ar','write'),390,theme);
 }
 const states=[['insights','evidence-hub-page-visual.php?variant=partial','#insights'],['empty','evidence-hub-page-visual.php?variant=zero',''],['attention','evidence-hub-text-rules-visual.php?fixture=default','#project-evidence'],['technology-review','evidence-hub-final-ui-visual.php?scenario=mixed&recommendations=3','#technologies'],['skills-without-evidence','evidence-hub-skill-evidence-visual.php?fixture=default','#skills-without-evidence'],['no-js','evidence-hub-page-visual.php?variant=partial','']];
 for(const[variant,locale,width,theme]of [['ar-light','ar',1440,'light'],['ar-dark','ar',1440,'dark'],['en-light','en',1440,'light'],['mobile-ar','ar',390,'light']]){
  for(const[name,route,fragment]of states)await capture(`${name}-${variant}`,`${base}/tests/Browser/${route}&lang=${locale}${fragment}`,width,theme,name!=='no-js');
  await capture(`sources-${variant}`,pageUrl(locale,'read')+'#sources',width,theme);
  await capture(`validation-${variant}`,`${base}/tests/Browser/owner-project-evidence-visual.php?scenario=error&lang=${locale}`,width,theme);
 }
 console.log('PASS FE-07 all five widths, ar/en, light/dark, large text, every layer/view/step, 44px coarse targets and full reference/state screenshots');
};
