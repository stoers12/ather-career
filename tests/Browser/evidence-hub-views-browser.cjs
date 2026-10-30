'use strict';
const assert=require('node:assert/strict');
// Called by the registered real-actions driver, against its disposable synthetic owners.
module.exports=async function evidenceViewsJourney(page,base){
 await page.goto(`${base}/owner/evidence-hub`);
 const read=await page.locator('.evidence-hub-open-record').first().getAttribute('href');
 assert.match(read,/^\/owner\/projects\/[1-9][0-9]*\/evidence$/);
 await page.goto(base+read+'?mode=write');
 const text={problem:'Synthetic browser context: the team needed a weekly reporting process.',personal_role:'I prepared the synthetic pipeline and wrote the reporting checks.',measurable_outcome:'The synthetic team could review the weekly report in 2 minutes.'};
 await page.locator('textarea[name=problem]').fill(text.problem);
 await page.locator('[data-evidence-step=personal_role]').click();await page.locator('textarea[name=personal_role]').fill(text.personal_role);
 await page.locator('[data-evidence-step=measurable_outcome]').click();await page.locator('textarea[name=measurable_outcome]').fill(text.measurable_outcome);
 // Save from the second step: the other two values must remain in the submitted form.
 await page.locator('[data-evidence-step=personal_role]').click();
 await Promise.all([page.waitForURL('**/owner/evidence-hub#project-*'),page.locator('.evidence-hub-editor-actions button[type=submit]').click()]);
 assert.equal(await page.getByRole('button',{name:'Undo last evidence update',exact:true}).isVisible(),true);
 const before=page.url();let navigations=0;const watch=req=>{if(req.isNavigationRequest())navigations++};page.on('request',watch);
 await page.locator('[data-evidence-layer=insights]').click();await page.locator('[data-evidence-layer=records]').click();page.off('request',watch);assert.equal(navigations,0);assert.ok(before.includes('#project-'));
 assert.equal(await page.getByRole('button',{name:'Undo last evidence update',exact:true}).isVisible(),true);
 await page.goto(base+read+'#sources');assert.equal(await page.locator('[data-evidence-text-form]').count(),0);
 for(const value of Object.values(text))assert.ok((await page.locator('.evidence-hub-record-text').allTextContents()).includes(value));
 const add=page.locator('form').filter({has:page.locator('input[value=add_source]')});
 await add.locator('input[name=title]').fill('Synthetic browser source');await add.locator('input[name=url]').fill('https://example.test/browser-source');await add.locator('input[name=note]').fill('A synthetic supporting report.');
 await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),add.getByRole('button',{name:'Add source',exact:true}).click()]);
 assert.equal(await page.getByRole('link',{name:'Synthetic browser source',exact:true}).isVisible(),true);
 // A later GET re-reads persisted text after source addition.
 await page.goto(base+read);for(const value of Object.values(text))assert.ok((await page.locator('.evidence-hub-record-text').allTextContents()).includes(value));
 const remove=page.locator('.evidence-hub-source-list li').filter({hasText:'Synthetic browser source'}).locator('button');await remove.click();await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('.confirm-ok').click()]);
 assert.equal(await page.getByRole('link',{name:'Synthetic browser source',exact:true}).count(),0);
 await page.goto(base+read+'?mode=write&part=personal_role');await page.locator('textarea[name=personal_role]').fill('x'.repeat(5001));
 const rejected=page.waitForResponse(r=>r.request().method()==='POST' && r.url().endsWith(read));await Promise.all([page.waitForNavigation({waitUntil:'domcontentloaded'}),page.locator('.evidence-hub-editor-actions button[type=submit]').click()]);assert.equal((await rejected).status(),422);
 console.log('CHECKPOINT Evidence B-07 retained 422');
 assert.equal(await page.locator('textarea[name=personal_role]').inputValue(),'x'.repeat(5001));
 await page.waitForFunction(()=>document.activeElement===document.querySelector('[aria-invalid=true]'));
 assert.equal(await page.locator('[data-evidence-step-panel=personal_role]').isVisible(),true);assert.equal(await page.locator('input[value=add_source]').count(),0);
 console.log('PASS Evidence Hub B-07 browser save from step 2, preserved values after source add/remove, immediate Undo across tabs, and retained 422 focus');
};
