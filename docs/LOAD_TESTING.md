# Load testing

How to measure how many concurrent visitors the public site serves, and the
first measured baseline. The tool is [k6](https://k6.io), run in a container.

## Safety model

`scripts/ops/run-load-test.ps1` never targets the running stack.

1. It starts a disposable, production-shaped stack (`docker-compose.production.yml`) with a run-owned project and database `ather_production_smoke_<24 hex>`, on its own network and a random loopback port.
2. It seeds one synthetic portfolio through `scripts/ops/production-smoke-fixture.php`, whose guards accept only that namespace.
3. It runs k6 inside that network against `http://web`.
4. It always removes the stack and its volumes (`down --volumes`) and checks nothing is left.

The image is only read. By default it is the image the resident web container runs (`-Image` overrides it). The k6 script refuses any slug other than the synthetic `production-smoke-<24 hex>` shape. It only sends `GET` requests: it never posts the contact form, never signs in and never writes.

## The visitor journey

`tests/Load/public-visitor.js` models one new visitor per iteration:

1. `GET /p/{slug}`;
2. every same-origin asset and media URL on that page, in parallel like a browser;
3. 2–5 s reading;
4. `GET /p/{slug}/projects/{id}`;
5. 3–8 s reading.

The model is deliberately pessimistic: every visitor is new (no browser cache), and every connection goes straight to Apache. In production, a proxy and CDN in front of Apache (HTTP/2, cached static files) remove most of those connections. Measure there too before quoting capacity.

## Profiles

| Profile | Shape | Purpose |
| --- | --- | --- |
| `smoke` | 2 VUs, 30 s | The script and stack work |
| `load` | 30 VUs for 2 min, then 60 VUs for 2 min | Expected traffic with headroom; must pass |
| `stress` | 50 → 100 → 200 → 300 VUs, 2 min each | Find the ceiling; expected to cross thresholds at the top step |
| `spike` | 0 → 200 VUs in 10 s, held 1 min | Sudden burst |

Thresholds:

- under 1% failed requests;
- page p95 under 800 ms;
- static p95 under 300 ms;
- over 99% complete journeys.

A VU is one simulated visitor in the same moment, including reading time.

## Running it

```powershell
./scripts/ops/run-load-test.ps1 -Profile smoke
./scripts/ops/run-load-test.ps1 -Profile stress -RawMetrics -OutputDirectory C:\temp\load
```

Output:

- the summary is printed and saved as `summary-<profile>.json`;
- `-RawMetrics` also writes every sample (`raw-<profile>.json.gz`) for per-URL or per-minute analysis.

The script ends with `LOAD_TEST_PASSED` or `LOAD_TEST_THRESHOLDS_FAILED` (non-zero exit).

## Baseline, 2026-10-31

Environment: Docker Desktop on the owner's Windows PC (16 vCPU). Web is limited to 512 MiB as in production, and k6 shares the same CPU. Image `79c20f8f2e26` (main after PR #96). The numbers are relative, not a hosting capacity.

### Finding

| Resource | Utilisation | Saturation |
| --- | --- | --- |
| CPU (web) | near idle | none |
| Memory (web) | 226 / 512 MiB | none |
| Database | light | none |
| **Apache prefork workers** | **150 / 150** | **requests queued up to 20 s** |

There were two causes, both in the stock Apache defaults:

1. **`KeepAliveTimeout 5`.** Prefork dedicates one worker to each open connection. After each response, an idle keep-alive connection held its worker for 5 s, so the 150 workers were mostly idle-but-reserved while new requests queued.
2. **Only 5 idle workers (`StartServers`/`MinSpareServers 5`).** One page view (the page plus parallel assets) needs about 6 workers at once. Prefork adds workers at one spawn cycle per second, so even 2 visitors waited about 1 s for images.

### Fix

`docker/apache/performance.conf`, measured one change at a time:

- `KeepAliveTimeout 1`;
- `StartServers 15`, `MinSpareServers 15`, `MaxSpareServers 30`.

`MaxRequestWorkers` stays at 150. It is the memory-safe ceiling for 512 MiB: 150 busy workers used under 230 MiB.

### Result (all requests, per 2-minute step)

| Concurrent visitors | Before p95 | After p95 | After requests/s |
| --- | --- | --- | --- |
| 2 (`smoke`) | static 1,379 ms | static 7 ms | — |
| 50 | 608 ms | 4 ms | 37 |
| 100 | 6,550 ms | 36 ms | 60 |
| 200 | ≈ 18,700 ms | 126 ms | 151 |
| 300 | 22,343 ms | 1,179 ms | 172 |

With the fix, `load` (up to 60 VUs) passes every threshold: 10,696 requests with 0 failed, page p95 8.6 ms (p99 88 ms) and static p95 3.5 ms (`LOAD_TEST_PASSED`).

There were no failed requests, restarts or OOM kills in either run. At 300 visitors, all 150 workers are busy, which is the current ceiling for this machine.

### Next measurements

- **After moving to the hosting server:** repeat `load` and `stress` through the real path (CDN → proxy → Apache).
- **Only if those numbers require it:** consider `mpm_event` with PHP-FPM, which does not tie a PHP process to an idle connection.
