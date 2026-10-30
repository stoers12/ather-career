'use strict';
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {chromium} = require(process.env.ATHER_PLAYWRIGHT_MODULE || 'playwright');
const base=process.env.ATHER_VISUAL_BASE || 'http://127.0.0.1:8765';
const output=process.env.ATHER_VISUAL_OUT || path.join(process.env.TEMP,'ather-evidence-interactions');
const palettes={light:{soft:'rgb(232, 240, 230)',green:'rgb(35, 93, 69)',ink:'rgb(28, 48, 40)',danger:'rgb(154, 40, 40)',surface:'rgb(255, 255, 255)'},dark:{soft:'rgb(46, 66, 49)',green:'rgb(184, 212, 170)',ink:'rgb(237, 243, 234)',danger:'rgb(255, 180, 174)',surface:'rgb(28, 39, 34)'}};
async function inspect(locator){return locator.evaluate(n=>{let s=getComputedStyle(n),r=n.getBoundingClientRect();return {color:s.color,bg:s.backgroundColor,outline:s.outlineColor,width:s.outlineWidth,shadow:s.boxShadow,x:r.x,y:r.y,w:r.width,h:r.height};});}
async function audit(page,palette,label){
 const controls=page.locator('.admin-content :is(a[href],button,summary,input:not([type=hidden]),textarea,select)'); let checked=0;
 for(let i=0;i<await controls.count();i++){
  const target=controls.nth(i);
  if(!await target.isVisible() || await target.isDisabled() || await target.evaluate(n=>!!n.closest('[inert]')))continue;
  await target.scrollIntoViewIfNeeded(); await page.keyboard.press('Tab'); await target.focus();
  const focus=await inspect(target); assert.equal(focus.outline,palette.green,`${label} focus ${i}`); assert.equal(focus.width,'2px'); assert.ok(!focus.shadow.includes('29, 78, 216'));
  const before=await inspect(target); await target.hover(); const after=await inspect(target);
  for(const key of ['x','y','w','h'])assert.ok(Math.abs(before[key]-after[key])<1,`${label} stable hover ${i}/${key}`);
  assert.notEqual(after.bg,'rgb(37, 99, 235)',`${label} no blue leakage`);
  if(await target.evaluate(n=>n.matches('a,button') && !n.matches('[aria-pressed=true],[aria-selected=true]') && !n.closest('.evidence-hub-options > div') && n.type!=='submit' && !n.classList.contains('evidence-hub-primary-cta'))){assert.equal(after.bg,palette.soft,`${label} secondary hover`);assert.equal(after.color,palette.green);}
  assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true,`${label} overflow`); checked++;
 }
 assert.ok(checked>=4,`${label} controls were audited`);return checked;
}
(async()=>{fs.mkdirSync(output,{recursive:true});const browser=await chromium.launch({headless:true,executablePath:process.env.ATHER_CHROMIUM_PATH});try{
 for(const theme of ['light','dark']){
  const context=await browser.newContext({viewport:{width:1440,height:900},reducedMotion:'reduce'});await context.addInitScript(t=>localStorage.setItem('ather.evidenceHub.theme',t),theme);const page=await context.newPage(),palette=palettes[theme];
  await page.goto(`${base}/tests/Browser/evidence-hub-page-visual.php?variant=partial`,{waitUntil:'networkidle'});
  assert.equal(await page.locator('body').getAttribute('data-evidence-hub-theme'),theme);
  await audit(page,palette,`${theme} Records`);
  const menu=page.locator('.evidence-hub-recommendation-card details').first();await menu.locator('summary').click();await menu.locator('button').last().hover();assert.equal((await inspect(menu.locator('button').last())).color,palette.danger);
  await page.keyboard.press('Escape');assert.equal(await menu.evaluate(n=>n.open),false);
  await page.locator('[data-evidence-layer=insights]').click();await audit(page,palette,`${theme} Insights`);
  assert.equal(await page.locator('[data-evidence-layer=insights]').getAttribute('aria-selected'),'true');
  await page.goto(`${base}/tests/Browser/owner-project-evidence-visual.php?mode=write`,{waitUntil:'networkidle'});
  for(const step of ['problem','personal_role','measurable_outcome','sources-after-save']){await page.locator(`[data-evidence-step=${step}]`).click();await audit(page,palette,`${theme} ${step}`);}
  await page.screenshot({path:path.join(output,`editor-${theme}.png`)});
  await page.goto(`${base}/tests/Browser/owner-project-evidence-visual.php`);await audit(page,palette,`${theme} Read/Sources`);await context.close();
 }
 for(const width of [320,390,768]){const context=await browser.newContext({viewport:{width,height:900},hasTouch:true});const page=await context.newPage();await page.goto(`${base}/tests/Browser/evidence-hub-page-visual.php?variant=partial`);await page.locator('.evidence-hub-options summary').first().tap();assert.equal(await page.locator('.evidence-hub-options').first().evaluate(n=>n.open),true);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await page.goto(`${base}/tests/Browser/owner-project-evidence-visual.php?mode=write`);assert.equal(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth),true);await context.close();}
 console.log('PASS Evidence Hub reference hover/focus, both layers, every editor part, read/source controls, touch and stable geometry');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1});
