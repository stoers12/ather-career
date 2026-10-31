// k6 load profile for the public visitor journey.
//
// Read-only: the portfolio page, its assets and media, then one case study.
// It never posts the contact form, never signs in and never targets a real
// portfolio; scripts/ops/run-load-test.ps1 provides a disposable synthetic one.
import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { Rate, Trend } from 'k6/metrics';

const BASE_URL = requireEnv('BASE_URL').replace(/\/+$/, '');
const SLUG = requireEnv('SLUG');
const PROJECT_ID = requireEnv('PROJECT_ID');
const PROFILE = __ENV.PROFILE || 'smoke';

if (!/^production-smoke-[a-f0-9]{24}$/.test(SLUG)) {
    throw new Error('Only the disposable synthetic portfolio slug may be load tested.');
}

const PROFILES = {
    // Correctness of the script itself.
    smoke: { executor: 'constant-vus', vus: 2, duration: '30s' },
    // Expected beta traffic and some headroom.
    load: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
            { duration: '30s', target: 30 },
            { duration: '2m', target: 30 },
            { duration: '30s', target: 60 },
            { duration: '2m', target: 60 },
            { duration: '30s', target: 0 },
        ],
    },
    // Step up until the thresholds break, to find the ceiling.
    stress: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
            { duration: '1m', target: 50 },
            { duration: '2m', target: 50 },
            { duration: '1m', target: 100 },
            { duration: '2m', target: 100 },
            { duration: '1m', target: 200 },
            { duration: '2m', target: 200 },
            { duration: '1m', target: 300 },
            { duration: '2m', target: 300 },
            { duration: '1m', target: 0 },
        ],
    },
    // A sudden burst, e.g. a post that gets shared widely.
    spike: {
        executor: 'ramping-vus',
        startVUs: 0,
        stages: [
            { duration: '10s', target: 200 },
            { duration: '1m', target: 200 },
            { duration: '10s', target: 0 },
        ],
    },
};

if (!PROFILES[PROFILE]) {
    throw new Error(`Unknown PROFILE "${PROFILE}". Use one of: ${Object.keys(PROFILES).join(', ')}.`);
}

export const options = {
    scenarios: { visitors: PROFILES[PROFILE] },
    thresholds: {
        http_req_failed: ['rate<0.01'],
        'http_req_duration{kind:page}': ['p(95)<800'],
        'http_req_duration{kind:static}': ['p(95)<300'],
        journey_ok: ['rate>0.99'],
    },
    summaryTrendStats: ['avg', 'med', 'p(90)', 'p(95)', 'p(99)', 'max'],
    noConnectionReuse: false,
    userAgent: 'ather-career-k6-load-test',
};

const journeyOk = new Rate('journey_ok');
const pageTtfb = new Trend('page_ttfb', true);

function requireEnv(name) {
    const value = __ENV[name];
    if (!value) {
        throw new Error(`${name} is required.`);
    }
    return value;
}

function think(min, max) {
    sleep(min + Math.random() * (max - min));
}

function sameOriginAssets(html) {
    const urls = new Set();
    const pattern = /(?:href|src)="(\/(?:assets\/|p\/[^"]*\/media)[^"#]*)"/g;
    let match;
    while ((match = pattern.exec(html)) !== null) {
        urls.add(match[1].replace(/&amp;/g, '&'));
    }
    return [...urls];
}

function loadAssets(html) {
    const requests = sameOriginAssets(html).map((path) => ['GET', BASE_URL + path, null, { tags: { kind: 'static', name: path.split('?')[0] } }]);
    if (requests.length === 0) {
        return true;
    }
    return http.batch(requests).every((response) => response.status === 200);
}

export default function () {
    let ok = true;

    group('portfolio', () => {
        const page = http.get(`${BASE_URL}/p/${SLUG}`, { tags: { kind: 'page', name: '/p/{slug}' } });
        pageTtfb.add(page.timings.waiting);
        ok = check(page, {
            'portfolio 200': (r) => r.status === 200,
            'portfolio is the synthetic owner': (r) => typeof r.body === 'string' && r.body.includes('Production Smoke Owner'),
        }) && ok;
        if (page.status === 200) {
            ok = check(null, { 'portfolio assets 200': () => loadAssets(page.body) }) && ok;
        }
    });

    think(2, 5);

    group('case study', () => {
        const page = http.get(`${BASE_URL}/p/${SLUG}/projects/${PROJECT_ID}`, { tags: { kind: 'page', name: '/p/{slug}/projects/{id}' } });
        pageTtfb.add(page.timings.waiting);
        ok = check(page, { 'case study 200': (r) => r.status === 200 }) && ok;
    });

    journeyOk.add(ok);
    think(3, 8);
}

export function handleSummary(data) {
    const out = __ENV.SUMMARY_PATH;
    const result = { stdout: textSummary(data) };
    if (out) {
        result[out] = JSON.stringify(data, null, 2);
    }
    return result;
}

function textSummary(data) {
    const m = data.metrics;
    const fmt = (metric, stat) => (m[metric] && m[metric].values[stat] !== undefined ? m[metric].values[stat].toFixed(1) : 'n/a');
    const thresholds = Object.entries(m)
        .flatMap(([name, metric]) => Object.entries(metric.thresholds || {}).map(([rule, t]) => `  ${t.ok ? 'PASS' : 'FAIL'} ${name} ${rule}`));
    return [
        `PROFILE ${PROFILE}`,
        `requests ${m.http_reqs.values.count} (${m.http_reqs.values.rate.toFixed(1)}/s), failed ${(m.http_req_failed.values.rate * 100).toFixed(2)}%`,
        `max VUs ${m.vus_max ? m.vus_max.values.max : 'n/a'}, journeys ${m.iterations.values.count}`,
        `page ms: med ${fmt('http_req_duration{kind:page}', 'med')} p95 ${fmt('http_req_duration{kind:page}', 'p(95)')} p99 ${fmt('http_req_duration{kind:page}', 'p(99)')} max ${fmt('http_req_duration{kind:page}', 'max')}`,
        `static ms: med ${fmt('http_req_duration{kind:static}', 'med')} p95 ${fmt('http_req_duration{kind:static}', 'p(95)')}`,
        'thresholds:',
        ...thresholds,
        '',
    ].join('\n');
}
