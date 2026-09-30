'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const endpoint = process.env.EVIDENCE_HUB_CDP || 'http://127.0.0.1:9223';
const base = process.env.EVIDENCE_HUB_VISUAL_BASE || 'http://127.0.0.1:8765';
const output = process.env.EVIDENCE_HUB_VISUAL_OUT || path.join(process.cwd(), '.qa-screenshots');
const fixture = (scenario, recommendations, feedback = 'none') => `${base}/tests/phase2/support/evidence-hub-final-ui-visual.php?scenario=${scenario}&recommendations=${recommendations}&feedback=${feedback}`;

class Cdp {
    constructor(socket) {
        this.socket = socket;
        this.id = 0;
        this.pending = new Map();
        this.errors = [];
        this.requests = [];
        socket.addEventListener('message', event => {
            const message = JSON.parse(event.data);
            if (message.id && this.pending.has(message.id)) {
                const { resolve, reject } = this.pending.get(message.id);
                this.pending.delete(message.id);
                message.error ? reject(new Error(message.error.message)) : resolve(message.result || {});
            }
            if (message.method === 'Runtime.exceptionThrown') this.errors.push('page exception');
            if (message.method === 'Network.requestWillBeSent') this.requests.push(message.params.request.url);
            if (message.method === 'Network.loadingFailed' && !message.params.canceled) this.errors.push(`request failed: ${message.params.errorText}`);
            if (message.method === 'Network.responseReceived' && message.params.response.status >= 500) this.errors.push(`HTTP ${message.params.response.status}`);
        });
    }
    send(method, params = {}) {
        const id = ++this.id;
        return new Promise((resolve, reject) => {
            this.pending.set(id, { resolve, reject });
            this.socket.send(JSON.stringify({ id, method, params }));
        });
    }
    async evaluate(expression) {
        const reply = await this.send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
        if (reply.exceptionDetails) throw new Error(`Browser evaluation failed: ${reply.exceptionDetails.text}`);
        return reply.result.value;
    }
}

async function open(width, height, theme = 'light', collapsed = false, javaScript = true) {
    const target = await (await fetch(`${endpoint}/json/new?about:blank`, { method: 'PUT' })).json();
    const socket = new WebSocket(target.webSocketDebuggerUrl);
    await new Promise((resolve, reject) => { socket.addEventListener('open', resolve, { once: true }); socket.addEventListener('error', reject, { once: true }); });
    const cdp = new Cdp(socket);
    cdp.targetId = target.id;
    cdp.noJs = !javaScript;
    await cdp.send('Page.enable');
    await cdp.send('Runtime.enable');
    await cdp.send('Network.enable');
    await cdp.send('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width <= 390 });
    await cdp.send('Emulation.setEmulatedMedia', { features: [{ name: 'prefers-reduced-motion', value: 'reduce' }] });
    if (javaScript) await cdp.send('Page.addScriptToEvaluateOnNewDocument', { source: `localStorage.setItem('ather.evidenceHub.theme','${theme}'); localStorage.setItem('ather.evidenceHub.sidebarCollapsed','${collapsed}');` });
    else await cdp.send('Emulation.setScriptExecutionDisabled', { value: true });
    return cdp;
}

async function navigate(cdp, url) {
    await cdp.send('Page.navigate', { url });
    if (cdp.noJs) {
        await new Promise(resolve => setTimeout(resolve, 500));
        await cdp.send('Emulation.setScriptExecutionDisabled', { value: false });
        cdp.noJs = false;
    }
    for (let attempt = 0; attempt < 80; attempt++) {
        if (await cdp.evaluate('document.readyState === "complete" && !!document.querySelector(".evidence-hub-page-body")')) {
            await new Promise(resolve => setTimeout(resolve, 180));
            const body = await cdp.evaluate('document.body?.innerText || ""');
            assert.equal(/Warning:|Fatal error|Stack trace:|HTTP 5\d\d/i.test(body), false, 'Server diagnostic leaked into the page.');
            return;
        }
        await new Promise(resolve => setTimeout(resolve, 100));
    }
    throw new Error(`Page did not load: ${url}`);
}

async function screenshot(cdp, name) {
    const result = await cdp.send('Page.captureScreenshot', { format: 'png', captureBeyondViewport: false });
    fs.writeFileSync(path.join(output, `${name}.png`), Buffer.from(result.data, 'base64'));
}

async function close(cdp) {
    assert.deepEqual(cdp.errors, [], 'Browser/page/request errors occurred.');
    cdp.socket.close();
    await fetch(`${endpoint}/json/close/${cdp.targetId}`);
}

async function state(cdp) {
    return cdp.evaluate(`(() => {
        const track = document.querySelector('[data-evidence-carousel-track]');
        const cards = [...document.querySelectorAll('.evidence-hub-project-card')];
        const visible = cards.filter(card => !card.hidden);
        const bounds = track.getBoundingClientRect();
        const fullyVisible = visible.filter(card => { const r = card.getBoundingClientRect(); return r.left >= bounds.left - 1 && r.right <= bounds.right + 1; });
        const nav = document.querySelector('[data-evidence-carousel-navigation]');
        const prev = document.querySelector('[data-evidence-carousel-previous]');
        const next = document.querySelector('[data-evidence-carousel-next]');
        const rect = element => { const r = element.getBoundingClientRect(); return { x:r.x,y:r.y,width:r.width,height:r.height,right:r.right,bottom:r.bottom }; };
        const grid = document.querySelector('.evidence-hub-recommendation-grid');
        const ids = [...document.querySelectorAll('[id]')].map(element => element.id);
        return {
            width: innerWidth, documentWidth: document.documentElement.scrollWidth,
            theme: document.body.dataset.evidenceHubTheme,
            cards: cards.length, matching: visible.length, fullyVisible: fullyVisible.length,
            fullyVisibleIds: fullyVisible.map(card => card.id),
            counter: document.querySelector('[data-evidence-carousel-counter]').textContent.trim(),
            navHidden: nav.hidden, prevDisabled: prev.disabled, nextDisabled: next.disabled,
            track: rect(track), prev: rect(prev), next: rect(next),
            gridColumns: grid ? getComputedStyle(grid).gridTemplateColumns.split(' ').filter(Boolean).length : 0,
            recommendationCount: document.querySelectorAll('.evidence-hub-recommendation-card').length,
            completion: !!document.querySelector('.evidence-hub-completion-panel'),
            duplicateIds: ids.length - new Set(ids).size,
            active: document.activeElement?.id || document.activeElement?.textContent?.trim().slice(0, 40),
            selectedFilter: document.querySelector('[data-evidence-filter][aria-pressed="true"]')?.dataset.evidenceFilter,
        };
    })()`);
}

async function key(cdp, name, code, virtualKeyCode) {
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyDown', key: name, code, windowsVirtualKeyCode: virtualKeyCode,
        ...(name === 'Enter' ? { text: '\r', unmodifiedText: '\r' } : {}) });
    await cdp.send('Input.dispatchKeyEvent', { type: 'keyUp', key: name, code, windowsVirtualKeyCode: virtualKeyCode });
}

async function menuState(cdp, projectId) {
    return cdp.evaluate(`(() => {
        const card = document.getElementById('project-${projectId}');
        const menu = card.querySelector('.evidence-hub-project-options');
        const trigger = menu.querySelector('summary');
        const panel = menu.querySelector('div');
        const track = document.querySelector('[data-evidence-carousel-track]');
        const ids = [...document.querySelectorAll('[id]')].map(element => element.id);
        return { open: menu.open, expanded: trigger.getAttribute('aria-expanded'),
            associated: trigger.getAttribute('aria-controls') === panel.id && trigger.id === 'project-${projectId}-options-trigger',
            triggerFocused: document.activeElement === trigger, focusInside: menu.contains(document.activeElement),
            visible: !!trigger.getClientRects().length && !card.hidden && !card.inert,
            openCount: document.querySelectorAll('.evidence-hub-project-options[open]').length,
            filter: document.querySelector('[data-evidence-filter][aria-pressed="true"]')?.dataset.evidenceFilter,
            scroll: track.scrollLeft, duplicateIds: ids.length - new Set(ids).size };
    })()`);
}

async function openMenuWithKey(cdp, projectId, activation) {
    await cdp.evaluate(`document.querySelector('#project-${projectId} .evidence-hub-project-options summary').focus({preventScroll:true})`);
    await key(cdp, activation === 'Enter' ? 'Enter' : ' ', activation === 'Enter' ? 'Enter' : 'Space', activation === 'Enter' ? 13 : 32);
    await new Promise(resolve => setTimeout(resolve, 60));
    const opened = await menuState(cdp, projectId);
    assert.equal(opened.open, true, `Project ${projectId} menu did not open with ${activation}`);
    assert.equal(opened.expanded, 'true');
    assert.equal(opened.associated, true);
    assert.equal(opened.openCount, 1);
    assert.equal(opened.duplicateIds, 0);
    return opened;
}

async function escapeMenu(cdp, projectId, activation, itemIndex, tabOutside = false) {
    const opened = await openMenuWithKey(cdp, projectId, activation);
    await key(cdp, 'Tab', 'Tab', 9);
    if (itemIndex === 1) await key(cdp, 'Tab', 'Tab', 9);
    assert.equal(await cdp.evaluate(`document.activeElement === document.querySelectorAll('#project-${projectId} .evidence-hub-project-options a')[${itemIndex}]`), true);
    if (tabOutside) await key(cdp, 'Tab', 'Tab', 9);
    await key(cdp, 'Escape', 'Escape', 27);
    const closed = await menuState(cdp, projectId);
    assert.equal(closed.open, false);
    assert.equal(closed.expanded, 'false');
    assert.equal(closed.triggerFocused, true, `Escape focused the wrong trigger for Project ${projectId}`);
    assert.equal(closed.visible, true, 'Escape focused a hidden or off-screen card');
    assert.equal(closed.openCount, 0);
    assert.equal(closed.filter, opened.filter);
    assert.equal(closed.scroll, opened.scroll, 'Escape moved the carousel');
    assert.equal(closed.duplicateIds, 0);
}

async function main() {
    fs.mkdirSync(output, { recursive: true });
    const checks = [];
    for (const [width, height, theme, collapsed] of [
        [1440, 900, 'light', false], [1440, 900, 'dark', false],
        [1440, 900, 'light', true], [1440, 900, 'dark', true],
        [900, 900, 'light', false], [900, 900, 'dark', false],
        [390, 844, 'light', false], [390, 844, 'dark', false],
        [320, 700, 'light', false], [320, 700, 'dark', false],
    ]) {
        const cdp = await open(width, height, theme, collapsed);
        try {
            await navigate(cdp, fixture('complete', 0));
            const item = await state(cdp);
            assert.equal(item.theme, theme);
            assert.equal(item.documentWidth <= width, true, `overflow at ${width} ${theme}`);
            assert.equal(item.cards, 4);
            assert.equal(item.completion, true);
            assert.equal(item.fullyVisible, width > 1100 ? 3 : (width > 700 ? 2 : 1));
            assert.equal(item.counter, width > 1100 ? 'Showing projects 1–3 of 4' : (width > 700 ? 'Showing projects 1–2 of 4' : 'Project 1 of 4'));
            assert.equal(item.prevDisabled, true);
            assert.equal(item.nextDisabled, false);
            assert.equal(item.duplicateIds, 0);
            if (width <= 390) assert.ok(item.prev.y >= item.track.bottom - 1, 'Mobile controls are not below the card.');
            else assert.ok(item.prev.x <= item.track.x && item.next.right >= item.track.right, 'Desktop arrows are not at card-row edges.');
            await screenshot(cdp, `${width}-${theme}-${collapsed ? 'collapsed' : 'expanded'}`);
            await cdp.evaluate('document.getElementById("project-evidence").scrollIntoView({block:"start"})');
            await screenshot(cdp, `${width}-${theme}-${collapsed ? 'collapsed' : 'expanded'}-projects`);
            checks.push(`${width}-${theme}-${collapsed ? 'collapsed' : 'expanded'}`);
        } finally { await close(cdp); }
    }
    for (const count of [1, 2, 3]) {
        const cdp = await open(1440, 900);
        try {
            await navigate(cdp, fixture('mixed', count));
            const item = await state(cdp);
            assert.equal(item.recommendationCount, count);
            assert.equal(item.gridColumns, count);
            assert.equal(item.documentWidth <= 1440, true);
            const widths = await cdp.evaluate(`(() => { const grid=document.querySelector('.evidence-hub-recommendation-grid'); const section=grid.closest('.evidence-hub-section'); const cards=[...grid.querySelectorAll('.evidence-hub-recommendation-card')]; const s=section.getBoundingClientRect(); const r=cards.map(c=>c.getBoundingClientRect()); return {sectionWidth:s.width, cardWidths:r.map(v=>v.width), left:r[0].left-s.left, right:s.right-r[r.length-1].right}; })()`);
            if (count === 1) {
                assert.ok(widths.cardWidths[0] <= 620 && widths.cardWidths[0] < widths.sectionWidth * .8, 'One recommendation stretched too wide.');
                assert.ok(Math.abs(widths.left - widths.right) <= 2, 'One recommendation is not centered.');
            }
            if (count === 2) {
                assert.ok(Math.abs(widths.cardWidths[0] - widths.cardWidths[1]) <= 2, 'Two recommendations are not equal width.');
                assert.ok(Math.abs(widths.left - widths.right) <= 2, 'Two recommendations are not centered.');
            }
            await screenshot(cdp, `recommendations-${count}`);
            checks.push(`recommendations-${count}`);
        } finally { await close(cdp); }
    }
    const cdp = await open(1440, 900);
    try {
        await navigate(cdp, fixture('mixed', 3));
        assert.equal((await state(cdp)).counter, 'Showing projects 1–3 of 4');
        await cdp.evaluate('document.querySelector("[data-evidence-carousel-next]").click()');
        await new Promise(resolve => setTimeout(resolve, 250));
        let item = await state(cdp);
        assert.equal(item.counter, 'Showing projects 2–4 of 4');
        assert.equal(item.nextDisabled, true);
        assert.equal(item.fullyVisibleIds.includes('project-4'), true);
        await cdp.evaluate('document.querySelector("[data-evidence-carousel-next]").click()');
        assert.equal((await state(cdp)).counter, 'Showing projects 2–4 of 4', 'Carousel looped');
        await cdp.evaluate('document.querySelector("[data-evidence-carousel-previous]").click()');
        assert.equal((await state(cdp)).counter, 'Showing projects 1–3 of 4');
        await cdp.evaluate('document.querySelector("[data-evidence-filter=complete]").click()');
        item = await state(cdp);
        assert.equal(item.matching, 2);
        assert.equal(item.navHidden, true);
        assert.equal(item.selectedFilter, 'complete');
        await cdp.evaluate('document.querySelector("[data-evidence-filter=attention]").click()');
        assert.equal((await state(cdp)).matching, 2);
        await cdp.evaluate('document.querySelector("[data-evidence-filter=all]").click()');
        await cdp.send('Page.navigate', { url: fixture('mixed', 3) + '#project-4' });
        await new Promise(resolve => setTimeout(resolve, 450));
        item = await state(cdp);
        assert.equal(item.fullyVisibleIds.includes('project-4'), true, 'Fragment omitted project 4');
        assert.equal(item.active, 'project-4', 'Fragment did not focus project 4');
        await cdp.evaluate('document.querySelector("#project-4 .evidence-hub-project-options summary").click()');
        assert.equal(await cdp.evaluate('document.querySelector("#project-4 .evidence-hub-project-options a")?.getAttribute("href")'), '/owner/projects/4/evidence');
        await key(cdp, 'Escape', 'Escape', 27);
        assert.equal(await cdp.evaluate('document.activeElement === document.querySelector("#project-4 .evidence-hub-project-options summary")'), true, 'Escape did not restore menu focus');
        await screenshot(cdp, 'project-4-fragment');
        checks.push('carousel-filters-fragment-menu');
        assert.deepEqual(cdp.errors, []);
    } finally { await close(cdp); }
    for (const [width, steps] of [[1440, 1], [900, 2], [390, 3], [320, 3]]) {
        const page = await open(width, width > 390 ? 900 : 700);
        try {
            await navigate(page, fixture('complete', 0));
            const firstCounter = (await state(page)).counter;
            await page.evaluate('document.querySelector("[data-evidence-carousel-next]").focus({preventScroll:true})');
            for (let step = 0; step < steps; step++) await key(page, 'ArrowRight', 'ArrowRight', 39);
            let current = await state(page);
            assert.equal(current.nextDisabled, true);
            assert.equal(await page.evaluate('document.activeElement === document.querySelector("[data-evidence-carousel-previous]")'), true,
                `Endpoint navigation lost keyboard focus at ${width}px.`);
            assert.equal(current.fullyVisibleIds.includes('project-4'), true);
            for (let step = 0; step < steps; step++) await key(page, 'ArrowLeft', 'ArrowLeft', 37);
            current = await state(page);
            assert.equal(current.counter, firstCounter);
            assert.equal(current.prevDisabled, true);
            assert.equal(await page.evaluate('document.activeElement === document.querySelector("[data-evidence-carousel-next]")'), true,
                `Start navigation lost keyboard focus at ${width}px.`);
            checks.push(`carousel-keyboard-endpoints-${width}`);
        } finally { await close(page); }
    }
    const buttonKeys = await open(390, 844);
    try {
        await navigate(buttonKeys, fixture('complete', 0));
        await buttonKeys.evaluate('document.querySelector("[data-evidence-carousel-next]").focus({preventScroll:true})');
        await key(buttonKeys, 'Enter', 'Enter', 13);
        assert.equal((await state(buttonKeys)).counter, 'Project 2 of 4');
        await key(buttonKeys, ' ', 'Space', 32);
        assert.equal((await state(buttonKeys)).counter, 'Project 3 of 4');
        checks.push('carousel-enter-space');
    } finally { await close(buttonKeys); }
    for (const [width, steps] of [[1440, 1], [900, 2], [390, 3], [320, 3]]) {
        const page = await open(width, width > 390 ? 900 : 700);
        try {
            await navigate(page, fixture('mixed', 3));
            await escapeMenu(page, 1, 'Enter', 0);
            await escapeMenu(page, 1, 'Space', 1, true);
            for (let step = 0; step < steps; step++) {
                await page.evaluate('document.querySelector("[data-evidence-carousel-next]").click()');
                await new Promise(resolve => setTimeout(resolve, 90));
            }
            assert.equal((await state(page)).fullyVisibleIds.includes('project-4'), true);
            await escapeMenu(page, 4, 'Enter', 0);
            await escapeMenu(page, 4, 'Space', 1);
            checks.push(`menu-keyboard-${width}`);
            assert.deepEqual(page.errors, []);
        } finally { await close(page); }
    }
    const menuPaths = await open(1440, 900);
    try {
        await navigate(menuPaths, fixture('mixed', 3));
        await menuPaths.evaluate('document.getElementById("project-evidence").scrollIntoView({block:"start"})');
        for (const [filter, projectId] of [['all', 1], ['complete', 1], ['attention', 2]]) {
            await menuPaths.evaluate(`document.querySelector('[data-evidence-filter=${filter}]').click()`);
            await new Promise(resolve => setTimeout(resolve, 100));
            await escapeMenu(menuPaths, projectId, 'Enter', 0);
        }
        await menuPaths.evaluate('document.querySelector("[data-evidence-filter=all]").click()');
        await new Promise(resolve => setTimeout(resolve, 100));
        await openMenuWithKey(menuPaths, 1, 'Enter');
        await screenshot(menuPaths, 'menu-open-project-1');
        await openMenuWithKey(menuPaths, 2, 'Space');
        assert.equal((await menuState(menuPaths, 1)).open, false, 'The previous menu stayed open.');
        assert.equal((await menuState(menuPaths, 2)).triggerFocused, true, 'Opening another menu moved focus.');
        await key(menuPaths, 'Escape', 'Escape', 27);
        assert.equal((await menuState(menuPaths, 2)).triggerFocused, true);
        await openMenuWithKey(menuPaths, 1, 'Enter');
        await menuPaths.evaluate('document.querySelector("[data-evidence-filter=all]").scrollIntoView({block:"center"})');
        const point = await menuPaths.evaluate(`(() => { const r=document.querySelector('[data-evidence-filter=all]').getBoundingClientRect(); return {x:r.x+r.width/2,y:r.y+r.height/2}; })()`);
        await menuPaths.send('Input.dispatchMouseEvent', { type: 'mousePressed', x: point.x, y: point.y, button: 'left', clickCount: 1 });
        await menuPaths.send('Input.dispatchMouseEvent', { type: 'mouseReleased', x: point.x, y: point.y, button: 'left', clickCount: 1 });
        assert.equal((await menuState(menuPaths, 1)).open, false);
        assert.equal(await menuPaths.evaluate('document.activeElement === document.querySelector("[data-evidence-filter=all]")'), true,
            `Outside click stole pointer focus: ${await menuPaths.evaluate('document.activeElement?.outerHTML?.slice(0,160)')}`);
        await key(menuPaths, 'Escape', 'Escape', 27);
        assert.equal(await menuPaths.evaluate('document.activeElement === document.querySelector("[data-evidence-filter=all]")'), true, 'Escape without an open menu moved focus.');
        await openMenuWithKey(menuPaths, 1, 'Enter');
        await menuPaths.evaluate(`(() => { const menu=document.querySelector('#project-1 .evidence-hub-project-options'); menu.replaceWith(menu.cloneNode(true)); })()`);
        await key(menuPaths, 'Escape', 'Escape', 27);
        assert.equal((await menuState(menuPaths, 1)).triggerFocused, true, 'Rerendered menu lost its project trigger.');
        await openMenuWithKey(menuPaths, 1, 'Enter');
        assert.equal(await menuPaths.evaluate('document.querySelector("#project-1 .evidence-hub-project-options a").getAttribute("href")'), '/owner/projects/1/evidence');
        await menuPaths.evaluate('document.querySelector("#project-1 .evidence-hub-project-options a").click()');
        await new Promise(resolve => setTimeout(resolve, 250));
        assert.equal(menuPaths.requests.some(request => request.includes('/owner/projects/1/evidence')), true, 'Menu selection did not follow the editor route.');
        checks.push('menu-filters-outside-selection-rerender');
    } finally { await close(menuPaths); }
    for (const feedback of ['saved', 'undone', 'conflict', 'unavailable']) {
        const page = await open(1440, 900);
        try {
            await navigate(page, fixture('complete', 0, feedback));
            const content = await page.evaluate('document.querySelector(".evidence-hub-page-body").textContent');
            assert.equal(content.includes('Undo last evidence update'), feedback === 'saved');
            await screenshot(page, `feedback-${feedback}`);
            checks.push(`feedback-${feedback}`);
        } finally { await close(page); }
    }
    const noJs = await open(320, 700, 'light', false, false);
    try {
        await navigate(noJs, fixture('mixed', 3));
        const item = await state(noJs);
        assert.equal(item.cards, 4);
        assert.equal(item.matching, 4);
        assert.equal(item.documentWidth <= 320, true);
        await screenshot(noJs, 'no-javascript-320');
        checks.push('no-javascript');
    } finally { await close(noJs); }
    console.log(JSON.stringify({ result: 'PASS', checks, screenshots: output }, null, 2));
}

main().catch(error => { console.error(error.stack || error); process.exitCode = 1; });
