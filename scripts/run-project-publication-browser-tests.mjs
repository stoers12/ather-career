import {createRequire} from 'node:module';
import fs from 'node:fs';
import path from 'node:path';
const require=createRequire(import.meta.url);
const {chromium}=require(process.env.ATHERCAR_PLAYWRIGHT_MODULE || 'playwright');
if (process.env.APP_ENV!=='test' || process.env.ATHERCAR_TEST_MODE!=='1') process.exit(2);
const base=process.env.ATHERCAR_PROJECT_PUBLICATION_BASE_URL;
const evidence=process.env.ATHERCAR_PROJECT_PUBLICATION_BROWSER_EVIDENCE;
if (!/^http:\/\/127\.0\.0\.1:\d+$/.test(base||'') || !fs.statSync(evidence).isDirectory()) process.exit(2);
const fixture=JSON.parse(fs.readFileSync(path.join(evidence,'private-project-context.json'),'utf8'));
let browser,context,stage='startup';
function check(condition,message) { if (!condition) { const e=new Error('Assertion'); e.safeMessage=message; throw e; } }
try {
  browser=await chromium.launch({headless:true});
  context=await browser.newContext({viewport:{width:1440,height:1000}});
  await context.addCookies([{name:fixture.cookie.Name,value:fixture.cookie.Value,url:base,httpOnly:true,sameSite:'Lax'}]);
  const p=await context.newPage(); await p.goto(base+'/owner_projects.php');
  const card=p.locator('.admin-project-grid article').filter({has:p.getByRole('heading',{name:'Project Alpha',exact:true})});
  const form=card.locator('.project-publication-form');
  stage='explicit keyboard controls';
  check(await card.count()===1 && await form.count()===1,'Independent control missing');
  check((await card.locator('.project-publication-status').textContent()).includes('Published'),'Stored public status missing');
  await form.getByRole('button',{name:'Hide project',exact:true}).focus();
  await Promise.all([p.waitForNavigation(),p.keyboard.press('Enter')]);
  check((await card.locator('.project-publication-status').textContent()).includes('Private project'),'Hide was not saved');
  const media=base+'/p/'+fixture.slug+'/media/project/'+fixture.alpha;
  check((await p.request.get(media)).status()===404,'Hidden image remained public');
  check((await (await p.request.get(base+'/p/'+fixture.slug+'/projects.json')).json()).projects.length===0,'Hidden JSON remained public');
  await p.reload();
  await Promise.all([p.waitForNavigation(),form.getByRole('button',{name:'Show in public Portfolio',exact:true}).click()]);
  check((await p.request.get(media)).status()===200,'Published existing image unavailable');
  check((await (await p.request.get(base+'/p/'+fixture.slug+'/projects.json')).json()).projects.length===1,'Mixed JSON projection wrong');
  check((await p.request.get(base+'/p/'+fixture.slug+'/media/project/'+fixture.hidden)).status()===404,'Other private project was published');
  console.log('PASS project-publication browser actual explicit keyboard hide/show save/reload public image200 private404 and mixed JSON');
  stage='content preservation';
  await card.getByRole('link',{name:'Edit',exact:true}).click();
  await p.locator('#description').fill('Browser content edit preserves choice');
  await Promise.all([p.waitForNavigation(),p.getByRole('button',{name:'Update Project',exact:true}).click()]);
  check(await form.getByRole('button',{name:'Hide project',exact:true}).count()===1,'Content edit reset choice');
  console.log('PASS project-publication browser ordinary content edit preserves separate publication choice');
  stage='responsive controls';
  for (const width of [1440,1024,768,390,320]) {
    await p.setViewportSize({width,height:1000});
    const button=form.getByRole('button'); await button.scrollIntoViewIfNeeded(); await button.focus();
    const box=await button.boundingBox(); check(box && box.x>=0 && box.x+box.width<=width+1,'Publication control clipped');
    check(await button.evaluate(e=>document.activeElement===e),'Publication control cannot focus');
    check(await p.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'Horizontal overflow');
    await p.screenshot({path:path.join(evidence,'project-publication-'+width+'.png'),fullPage:true});
  }
  console.log('PASS project-publication browser responsive stored-state/help keyboard controls at 1440 1024 768 390 320px');
} catch(e) {
  console.error('FAIL project-publication browser '+stage+': '+(e.safeMessage || e.name)); process.exitCode=1;
} finally {
  if (context) await context.close(); if (browser) await browser.close();
  fs.writeFileSync(path.join(evidence,'stop-project'),'stop');
}
