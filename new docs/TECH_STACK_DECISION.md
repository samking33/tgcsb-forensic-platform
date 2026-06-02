# TGCSB Forensic Platform — Tech Stack Analysis & Decision Guide
**Date:** 2026-05-31  
**Context:** Current tool is v2.1.0 — raw PHP + Python flat-file system. Moving to production.

---

## TABLE OF CONTENTS
1. [Current Stack Honest Assessment](#1-current-stack-honest-assessment)
2. [Root Problems (Not Symptoms)](#2-root-problems-not-symptoms)
3. [Database Decision](#3-database-decision)
4. [Three Paths Forward](#4-three-paths-forward)
5. [Path A — FastAPI + React](#5-path-a--fastapi--react-typescript)
6. [Path B — Laravel + Vue](#6-path-b--laravel--vuejs)
7. [Path C — Next.js Full-Stack](#7-path-c--nextjs-full-stack--python-microservice)
8. [Head-to-Head Comparison](#8-head-to-head-comparison)
9. [Recommendation](#9-recommendation)
10. [What the Stack Should NOT Be](#10-what-the-stack-should-not-be)
11. [Team Skill Decision Matrix](#11-team-skill-decision-matrix)
12. [Transition Roadmap](#12-transition-roadmap)

---

## 1. Current Stack — Honest Assessment

### What exists today

| Layer | Technology | State |
|---|---|---|
| Web backend | Raw PHP 8.3 (no framework) | Functional but unmaintainable |
| API layer | PHP files acting as endpoints | No routing, no auth, no validation |
| Extraction backend | Python 3.x scripts | Mostly working, Windows-biased |
| Analysis backend | Python 3.x modules | Partially implemented, many stubs |
| Frontend | Vanilla JS + Chart.js + Leaflet | ~900 lines of procedural functions |
| Database | None — flat `.txt` files in `logs/` | Not production viable |
| Queue / jobs | `exec("start /B ...")` shell hack | Broken on macOS/Linux |
| Auth | None | Every endpoint is open |
| Real-time | PHP SSE (Server-Sent Events) | Partially working |
| Deployment | Manual PHP dev server | Not reproducible |

### What "raw PHP with no framework" means in practice

Right now every API endpoint is a standalone file. Each one:
- Opens files directly with `file_get_contents()` and `fopen()`
- Has its own ad-hoc error handling (or none)
- Duplicates config loading, path resolution, header setting
- Has no consistent request validation
- Has no ORM — any DB query will be raw SQL string concatenation
- Has no concept of middleware — auth would have to be copy-pasted into every single file

This is not a criticism of PHP. It is a criticism of using PHP without a framework for a production application. Laravel sitting on top of the same PHP runtime gives you routing, ORM, queues, auth, migrations, events, and testing infrastructure — all of which this app needs and currently lacks entirely.

---

## 2. Root Problems (Not Symptoms)

The 40+ bugs in the audit are symptoms. These are the underlying root causes:

---

### Root Problem 1: No framework = no structure
Without a framework there is no enforced way to:
- Handle requests consistently (every file does it differently)
- Validate inputs (done ad-hoc or not at all)
- Manage database connections (will be duplicated per-file once DB is added)
- Handle errors (some files return JSON, some return HTML, some return nothing)
- Write tests (no test runner, no DI container, nothing to mock)

**Direct consequence in codebase:** `web/api/` has 25 files each doing their own version of the same things. Adding authentication means touching all 25 files.

---

### Root Problem 2: The PHP/Python bridge is the most fragile part of the system
The extraction pipeline is Python. The analysis is Python. The threat detection is Python. Yet the web layer is PHP. These two talk to each other via `exec()` shell commands. This is the source of:

- BUG-001: Extraction broken on macOS (Windows shell command)
- BUG-002: Live streaming broken on macOS (Windows ADB paths)
- BUG-003: Threat scanner broken (Python syntax error invisible to PHP)
- BUG-004: Report export broken (Windows `cd /d` command)

Every time Python throws an exception, PHP gets back an empty string and reports success. The bridge has no error channel, no type safety, no versioning, and no ability to stream progress natively.

**The bridge is not fixable. It can only be replaced** — either by moving the web layer to Python (eliminating the bridge entirely) or by using a proper job queue (making the bridge asynchronous and observable).

---

### Root Problem 3: No background job system
Long-running operations (ADB extraction, threat scan, report generation) need to run in the background while the browser polls for progress. The current "solution":

```php
// Windows only, no error handling, no progress, returns false success
$cmd = "start /B \"\" \"$phpBin\" ... > NUL 2>&1";
pclose(popen($cmd, "r"));
echo json_encode(['success' => true]);  // always says success
```

A proper job queue (Celery, Laravel Queues, BullMQ) gives you: job status, progress tracking, retry on failure, priority lanes, job history, and dead-letter queues. This is not optional for a forensic tool — investigators need to know definitively whether extraction succeeded or failed.

---

### Root Problem 4: No type safety anywhere
- PHP without strict_types is dynamically typed
- Python without type hints is dynamically typed
- JavaScript is dynamically typed
- The data passed between all three layers is untyped strings

In a forensic tool where the wrong data type can silently corrupt evidence records (the year-inference bug is a direct example of this), no type safety is a serious integrity risk.

---

### Root Problem 5: Frontend is procedural, not component-based
`app.js` is ~900 lines of global functions with names like `updateBadge()`, `appendLog()`, `displayFilterResults()`. They are called via `onclick` attributes in PHP-rendered HTML. There is no:
- Component lifecycle management
- Reactive state (changing a case switches pages but doesn't reset state)
- Type safety (function parameters are untyped)
- Reusable UI components (the same card layout is copy-pasted across 20 pages)

Adding the case management UI, the SQLite browser, the evidence comparison view — all of these are major UI features. Building them in vanilla procedural JS is how you end up with 500-line functions and bugs that are impossible to trace.

---

## 3. Database Decision

### PostgreSQL vs MongoDB

This decision comes up because the app currently has no database at all. The recommendation is **PostgreSQL 16 with PostGIS**.

#### Why PostgreSQL wins for a forensic tool

**Forensic data is relational by nature:**
```
Cases (1) → (many) Devices (1) → (many) Extractions (1) → (many) Evidence Records
```
This is a textbook relational model. Forcing it into document collections means manually managing references, joins become application-level loops, and referential integrity is your problem to enforce.

**ACID compliance is non-negotiable for evidence:**
In MongoDB's default configuration, a write can succeed on the primary but not yet replicate. In a forensic context, a "partially written" evidence record that later disappears is a chain-of-custody failure. PostgreSQL's full ACID compliance means a write either fully happened or fully didn't — no in-between state.

**Immutable audit log is enforceable at DB level:**
```sql
-- This trigger makes audit_log physically impossible to modify
CREATE TRIGGER audit_log_immutable
    BEFORE UPDATE OR DELETE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();
```
MongoDB has no equivalent. You can build application-level guards, but a compromised application bypasses them. A DB-level trigger cannot be bypassed by application code.

**PostGIS for location data:**
Cell tower geolocation, GPS track analysis, geofence queries, speed analysis between points — all of these require geospatial operations. PostGIS is the industry standard:
```sql
-- Find all location points within 500m of a crime scene
SELECT * FROM location_points
WHERE ST_DWithin(location, ST_MakePoint(101.6869, 3.1478)::geography, 500);

-- Detect impossible travel (person appeared in two cities within 1 hour)
SELECT a.extraction_id, a.recorded_at, b.recorded_at,
       ST_Distance(a.location, b.location) / 1000 AS distance_km
FROM location_points a JOIN location_points b ON a.extraction_id = b.extraction_id
WHERE b.recorded_at - a.recorded_at < INTERVAL '1 hour'
AND ST_Distance(a.location, b.location) > 100000;
```
This is one SQL query. Without PostGIS it is hundreds of lines of PHP math with floating-point errors.

**Full-text search built in:**
```sql
-- Global search across all SMS, logcat, contacts — no extra service
CREATE INDEX idx_sms_fts ON sms_records USING GIN(to_tsvector('english', body));
SELECT * FROM sms_records WHERE to_tsvector('english', body) @@ plainto_tsquery('drug payment');
```

**JSONB for semi-structured data:**
MongoDB's main advantage is flexible schemas. PostgreSQL JSONB gives you 90% of that:
```sql
-- Store raw device metadata as JSONB, query specific fields
SELECT metadata->>'build_fingerprint' FROM devices WHERE metadata @> '{"is_rooted": true}';
```

#### When MongoDB would make sense
If you had millions of heterogeneous, schema-less documents per day with unpredictable structure and write throughput was the primary concern. That is not this use case. Case counts are in the hundreds. Record counts are in the thousands to low millions. Structure is well-defined. Integrity matters more than write speed.

#### Redis as a companion (not alternative)
PostgreSQL handles persistent, structured data. Redis handles ephemeral real-time state:

| Use case | PostgreSQL | Redis |
|---|---|---|
| Evidence records | ✅ | ❌ |
| Audit log | ✅ | ❌ |
| Case data | ✅ | ❌ |
| Extraction job progress | ❌ | ✅ |
| Live ADB log stream buffer | ❌ | ✅ |
| Session tokens | ❌ | ✅ |
| Rate limiting | ❌ | ✅ |
| WebSocket pub/sub | ❌ | ✅ |

---

## 4. Three Paths Forward

Three viable options exist. All three require adding PostgreSQL + Redis. The difference is what happens to the PHP/Python bridge.

| | Path A | Path B | Path C |
|---|---|---|---|
| **Name** | FastAPI + React | Laravel + Vue | Next.js + Python service |
| **Bridge fate** | Eliminated | Queue-based | Isolated microservice |
| **Rewrite scope** | Web layer only | Refactor in place | Web layer + service split |
| **Time to production** | 2–3 months | 3–6 weeks | 3–4 months |
| **Long-term fit** | Excellent | Good | Good |

---

## 5. Path A — FastAPI + React (TypeScript)

### Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Browser                            │
│   React 18 + TypeScript + TanStack Query            │
│   Chart.js + Leaflet + shadcn/ui components         │
└──────────────────────┬──────────────────────────────┘
                       │ HTTP REST + WebSocket
┌──────────────────────▼──────────────────────────────┐
│              FastAPI (Python 3.11+)                  │
│   • Pydantic models for request/response validation  │
│   • SQLAlchemy 2.0 ORM (async)                       │
│   • Alembic for DB migrations                        │
│   • FastAPI-Users for auth                           │
│   • WebSockets for live ADB streaming                │
└──────────────────────┬──────────────────────────────┘
                       │
          ┌────────────┴─────────────┐
          │                          │
┌─────────▼──────┐        ┌──────────▼──────┐
│  PostgreSQL 16  │        │   Celery + Redis │
│  + PostGIS      │        │   Background jobs│
└─────────────────┘        └──────────────────┘
                                    │
                           ┌────────▼────────┐
                           │  Python workers  │
                           │  scripts/*.py    │
                           │  analysis/*.py   │
                           └─────────────────┘
```

### Why this makes the most sense for this project

The extraction pipeline is Python. The analysis modules are Python. The threat detection is Python. With FastAPI, the web API is also Python. The PHP/Python bridge disappears entirely — `android_logs.py` is just another Python module that the API imports directly.

```python
# Before (broken exec() bridge):
$command = "python threat_detector.py 2>&1";
exec($command, $output);  // no error handling, no types, Windows-only

# After (direct import):
from analysis.threat_detector import analyze_threats
findings = await analyze_threats(extraction_id=42)  # typed, async, testable
```

### FastAPI specifics

**Automatic request validation:**
```python
from pydantic import BaseModel
from typing import Literal

class ExtractionRequest(BaseModel):
    device_id: int
    extract_logcat: bool = True
    extract_sms: bool = True
    extract_calls: bool = True
    extract_location: bool = True
    extraction_type: Literal['logical', 'backup', 'manual'] = 'logical'

@router.post("/extractions", response_model=ExtractionResponse)
async def start_extraction(req: ExtractionRequest, user=Depends(current_user)):
    # req is fully validated and typed before this line runs
    job = await queue.enqueue(run_extraction, req.model_dump())
    return ExtractionResponse(extraction_id=job.id, status="pending")
```

**Native WebSocket for live ADB streaming:**
```python
@router.websocket("/extractions/{id}/stream")
async def stream_logcat(websocket: WebSocket, id: int, user=Depends(ws_auth)):
    await websocket.accept()
    async for line in adb_logcat_stream(extraction_id=id):
        await websocket.send_json({"line": line, "ts": time.time()})
```

**Auto-generated API documentation:**
FastAPI generates OpenAPI (Swagger) docs automatically at `/docs`. Every endpoint, every request/response model, every error code — documented with zero extra work. Useful for TGCSB integrations with other systems.

### React + TypeScript frontend

```typescript
// Type-safe API calls with TanStack Query
const { data: extraction, isLoading } = useQuery({
    queryKey: ['extraction', extractionId],
    queryFn: () => api.get<Extraction>(`/extractions/${extractionId}`),
    refetchInterval: (data) => data?.status === 'running' ? 2000 : false,
});

// shadcn/ui components — professional UI without writing CSS
<DataTable
    columns={smsColumns}
    data={extraction?.smsRecords ?? []}
    searchColumn="body"
    exportable
/>
```

### Celery for background jobs

```python
@celery.task(bind=True, max_retries=3)
def run_extraction(self, extraction_id: int):
    try:
        self.update_state(state='PROGRESS', meta={'percent': 0})
        extractor = AndroidExtractor(extraction_id)
        for progress in extractor.run():
            self.update_state(state='PROGRESS', meta={'percent': progress})
        return {'status': 'complete', 'extraction_id': extraction_id}
    except ADBNotFoundError as exc:
        raise self.retry(exc=exc, countdown=10)
```

### Pros
- One language (Python) for entire backend — no bridge at all
- Full Python forensic ecosystem available natively (pytsk3, libimobiledevice, yara-python)
- Async-native — live ADB streaming is a first-class feature
- Pydantic validation catches bad data at the boundary, not after it corrupts evidence
- Type safety across the entire backend
- FastAPI + SQLAlchemy is the modern Python web stack — strong community, long-term supported
- OpenAPI docs auto-generated

### Cons
- Requires rewriting the PHP web layer (1–2 months of work)
- Team needs Python web development skills, not just scripting
- React adds a build step (Vite — simple, fast, but still a step)
- More moving parts than Laravel (FastAPI + Celery + Redis + React build)

---

## 6. Path B — Laravel + Vue.js

### Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Browser                            │
│   Vue 3 + TypeScript + Inertia.js                   │
│   (or Alpine.js for lighter weight)                  │
└──────────────────────┬──────────────────────────────┘
                       │ Inertia protocol / REST
┌──────────────────────▼──────────────────────────────┐
│              Laravel 11 (PHP 8.3)                    │
│   • Eloquent ORM                                     │
│   • Laravel Sanctum (auth)                           │
│   • Laravel Queues + Horizon (job monitoring)        │
│   • Laravel Echo + WebSockets (Reverb)               │
│   • Artisan migrations                               │
└──────────────────────┬──────────────────────────────┘
                       │
          ┌────────────┴─────────────┐
          │                          │
┌─────────▼──────┐        ┌──────────▼──────┐
│  PostgreSQL 16  │        │   Redis          │
│  + PostGIS      │        │   Queue backend  │
└─────────────────┘        └──────────────────┘
                                    │
                           ┌────────▼────────┐
                           │  Python scripts  │
                           │  called via Jobs │
                           └─────────────────┘
```

### Why this is the fastest path to production

The existing PHP pages, includes, parsers, and collectors are mostly salvageable. You're adding a framework on top of existing code, not rewriting from scratch. A Laravel migration looks like:

```
web/api/extract.php          → app/Http/Controllers/ExtractionController.php
web/includes/services/       → app/Services/
web/includes/collectors/     → app/Services/Collectors/
web/includes/models/         → app/Models/ (Eloquent models)
web/includes/parsers/        → app/Parsers/
```

### Laravel specifics

**Eloquent ORM — replaces all custom repository code:**
```php
// Before: custom PDO queries in every API file
$stmt = DB::query('SELECT * FROM extractions WHERE device_id = ? AND status = ?', [$deviceId, 'complete']);

// After: Eloquent with type-safe relationships
$extractions = Extraction::where('device_id', $deviceId)
    ->where('status', 'complete')
    ->with(['device.case', 'threatFindings', 'fileHashes'])
    ->latest()
    ->paginate(20);
```

**Laravel Queues — replaces the `exec()` hack:**
```php
// Before: broken Windows-only shell exec
$cmd = "start /B \"\" \"$phpBin\" ... > NUL 2>&1";
pclose(popen($cmd, "r"));

// After: proper job queue (works on all platforms)
ExtractionJob::dispatch($extraction->id)
    ->onQueue('extractions')
    ->delay(now());
```

**The extraction job calls Python properly:**
```php
class ExtractionJob implements ShouldQueue {
    use InteractsWithQueue, Queueable;
    public int $tries = 3;

    public function handle(): void {
        $process = new Process([
            'python3', base_path('scripts/process_all.py'),
            '--extraction-id', $this->extractionId,
            '--db-url', config('database.connections.pgsql.url'),
        ]);
        $process->setTimeout(3600);
        $process->run(function ($type, $output) {
            // real-time progress updates via Redis
            Redis::publish("extraction:{$this->extractionId}", $output);
        });

        if (!$process->isSuccessful()) {
            throw new ExtractionFailedException($process->getErrorOutput());
        }
    }
}
```

**Laravel Sanctum — auth in ~10 lines:**
```php
// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('cases', CaseController::class);
    Route::apiResource('cases.devices', DeviceController::class);
    Route::apiResource('extractions', ExtractionController::class);
    Route::get('extractions/{id}/progress', [ExtractionController::class, 'progress']);
});
```

**Laravel Horizon — real-time job monitoring UI:**
Horizon is a dashboard for monitoring all queue jobs. Investigators can see running extractions, failed jobs, retry counts — with zero extra code. Installed with `composer require laravel/horizon`.

**Inertia.js — Vue without an API layer:**
Inertia.js lets you write Vue components that are served by Laravel controllers, without building a separate API. The controller passes typed props directly to the Vue component:

```php
// Laravel controller
public function show(Case $case): Response {
    return Inertia::render('Cases/Show', [
        'case'       => CaseResource::make($case->load('investigators', 'devices')),
        'stats'      => $this->statsService->forCase($case),
        'canEdit'    => $request->user()->can('update', $case),
    ]);
}
```

```vue
<!-- Vue component receives typed props -->
<script setup lang="ts">
const props = defineProps<{
  case: Case
  stats: CaseStats
  canEdit: boolean
}>()
</script>
```

### Pros
- Fastest path to production — existing PHP code is mostly reusable
- Laravel is the most complete PHP framework — auth, queues, ORM, events, websockets all built in
- Eloquent ORM is mature and well-documented
- Horizon gives job monitoring with no extra code
- Vue + Inertia means one repo, no separate frontend build pipeline to manage
- PHP 8.3 is strongly typed — type declarations, match expressions, enums

### Cons
- PHP/Python bridge still exists (now as a job, not a raw exec — much better, but still two languages)
- PHP is not natural for forensic tool long-term — the DFIR Python ecosystem (YARA, pytsk3, volatility3) won't integrate as cleanly
- Type safety is PHP-only — Python scripts still untyped unless you add mypy
- Laravel is opinionated — migrating from raw PHP to Laravel has a learning curve

---

## 7. Path C — Next.js Full-Stack + Python Microservice

### Architecture

```
┌─────────────────────────────────────────────────────┐
│   Next.js 15 (TypeScript)                           │
│   • React Server Components for pages               │
│   • API Routes for REST endpoints                   │
│   • Server Actions for form handling                │
│   • Prisma ORM for PostgreSQL                       │
│   • NextAuth.js for authentication                  │
└──────────────────────┬──────────────────────────────┘
                       │ internal HTTP
┌──────────────────────▼──────────────────────────────┐
│   FastAPI microservice (Python)                      │
│   • ADB extraction only                             │
│   • Analysis modules only                           │
│   • Called by Next.js backend, not browser          │
└──────────────────────┬──────────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────────┐
│   PostgreSQL 16 + PostGIS + Redis                    │
└─────────────────────────────────────────────────────┘
```

### When this makes sense
This path makes the most sense if you are planning a multi-tenant SaaS product where multiple organisations (different police departments, agencies) use the same hosted platform. Next.js + TypeScript end-to-end is very clean for that model.

For a single-organisation tool deployed on-premises at TGCSB, this is over-engineered. You now have three runtimes (Node.js, Python, PostgreSQL), two separate services to deploy, version, and monitor, and two languages to maintain.

### Pros
- TypeScript everywhere — full type safety frontend to backend
- Next.js is the most modern and actively developed web framework
- Python service is cleanly isolated — best of both worlds
- Best option for future SaaS multi-tenancy

### Cons
- Largest rewrite — PHP + Python scripts both replaced entirely
- Two separate services to deploy and monitor (Node.js + Python)
- Complexity without commensurate benefit for a single-org tool
- Team needs to know TypeScript, React, Next.js, AND Python
- Longest time to production

---

## 8. Head-to-Head Comparison

### By technical criteria

| Criterion | Path A (FastAPI+React) | Path B (Laravel+Vue) | Path C (Next.js) |
|---|---|---|---|
| PHP/Python bridge | ❌ Eliminated | ⚠️ Queue-based | ⚠️ HTTP microservice |
| Type safety — backend | ✅ Pydantic + mypy | ✅ PHP 8 types | ✅ TypeScript |
| Type safety — frontend | ✅ TypeScript | ✅ TypeScript | ✅ TypeScript |
| Auth system | ✅ FastAPI-Users | ✅ Sanctum | ✅ NextAuth |
| Background jobs | ✅ Celery | ✅ Laravel Queues | ✅ BullMQ |
| Job monitoring dashboard | ⚠️ Flower (separate) | ✅ Horizon (built-in) | ⚠️ Manual |
| Real-time / WebSockets | ✅ Native async | ✅ Laravel Reverb | ✅ Native |
| DB migrations | ✅ Alembic | ✅ Artisan | ✅ Prisma |
| ORM quality | ✅ SQLAlchemy 2.0 | ✅ Eloquent | ✅ Prisma |
| PostGIS integration | ✅ GeoAlchemy2 | ✅ Native SQL | ✅ Raw queries |
| Forensic ecosystem fit | ✅ Native Python | ⚠️ Via subprocess | ⚠️ Via subprocess |
| YARA integration | ✅ yara-python native | ⚠️ exec() | ⚠️ exec() |
| Auto API docs | ✅ OpenAPI built-in | ⚠️ Scribe (addon) | ⚠️ Manual |
| Testability | ✅ pytest | ✅ PHPUnit + Pest | ✅ Vitest + pytest |

### By project criteria

| Criterion | Path A | Path B | Path C |
|---|---|---|---|
| Time to first working deployment | 2–3 months | 3–6 weeks | 3–4 months |
| PHP code salvageability | ❌ Full rewrite | ✅ ~60% reusable | ❌ Full rewrite |
| Python code salvageability | ✅ 100% kept | ✅ 100% kept | ✅ 100% kept |
| Team learning curve | Medium (Python web) | Low (PHP framework) | High (TS + 2 services) |
| Long-term maintainability | Excellent | Good | Good |
| Forensic tool ecosystem alignment | Excellent | Fair | Fair |
| Future iOS/mobile expansion | ✅ Python libs available | ⚠️ Via subprocess | ⚠️ Via subprocess |
| Multi-tenancy / SaaS potential | Good | Good | Excellent |
| Operational complexity | Medium | Low | High |
| Docker deployment complexity | Medium | Low | High |

### By team profile

| If your team is strong in... | Best path |
|---|---|
| Python (scripting + some web) | Path A — FastAPI + React |
| PHP / web backend | Path B — Laravel + Vue |
| TypeScript / React / Node.js | Path C — Next.js |
| Mixed / small team | Path B — lowest learning curve, fastest to ship |

---

## 9. Recommendation

### Short answer: Path B now, migrate to Path A for v4

### Reasoning

**The goal right now is a working, production tool** — not the perfect architecture. The current tool has 7 critical bugs that mean it doesn't function at all. TGCSB investigators cannot use it today.

**Path B (Laravel) gets you to production in 3–6 weeks** because:
1. 60% of the PHP code survives — pages, parsers, collectors, services are all salvageable
2. Python scripts are completely unchanged — they just get called by Laravel jobs instead of raw `exec()`
3. Laravel solves every structural problem in one move: ORM, auth, queues, migrations, events
4. The team is already writing PHP — no new language to learn

**Once the tool is stable and being used,** evaluate Path A. The right time to rewrite the web layer in FastAPI is when:
- The tool has active users (investigators depend on it)
- The Python analysis modules are mature and tested
- There is capacity for a 2-month migration without disrupting operations

**Do not do a full FastAPI rewrite while the tool is broken.** You will spend 3 months rewriting infrastructure while investigators still can't do their job.

### The non-negotiable decisions regardless of path

These apply to all three paths:

1. **PostgreSQL 16 + PostGIS** — not negotiable, not SQLite, not MongoDB
2. **Redis** — for job queues, progress streaming, sessions
3. **Authentication** — before any production deployment
4. **Docker Compose** — one command to run the entire stack, eliminates OS compatibility issues
5. **Fix the 7 critical bugs** — these must be fixed regardless of which path is chosen. They are not framework problems.

---

## 10. What the Stack Should NOT Be

### Raw PHP with no framework
What the tool is today. Cannot scale. Cannot be secured. Cannot be tested. Every feature addition requires touching 25 files. Do not continue building on this foundation.

### SQLite as the database
The existing architecture doc mentions SQLite as an option. It is wrong for this use case:
- No concurrent write support — two investigators accessing the same case simultaneously causes lock contention
- No PostGIS support — geospatial queries become hundreds of lines of application math
- No row-level security — you cannot restrict investigator A from seeing investigator B's cases at the DB level
- Not suitable for multi-case production workloads

### MongoDB
Covered in detail in Section 3. The forensic data model is relational. ACID compliance is required. The immutable audit log requires DB-level enforcement. PostGIS is needed for location analysis. MongoDB provides none of these.

### Node.js without TypeScript
Trading PHP's type problems for JavaScript's. If you go Node.js, TypeScript is mandatory, not optional.

### Deploying without Docker
The Windows/macOS/Linux compatibility bugs (BUG-001 through BUG-004) exist because the tool assumes a specific OS environment. Docker eliminates this class of bugs entirely. Any production deployment must be containerised.

---

## 11. Team Skill Decision Matrix

Answer these questions to confirm the path:

**Q1: Does your team write Python web apps (FastAPI/Flask/Django), or primarily Python scripts?**
- Web apps → Path A is comfortable
- Scripts only → Path B or C first, Path A as a v4 migration

**Q2: Does your team have PHP web development experience?**
- Yes, familiar with MVC frameworks → Path B, fastest delivery
- PHP only procedural → any path requires some learning

**Q3: Does your team know TypeScript/React?**
- Yes → Path C is viable
- No → Path A or B, add TypeScript incrementally

**Q4: How many developers are working on this?**
- 1–2 people → Path B. Minimal moving parts, fastest to production
- 3–5 people → Path A or B. Can split frontend and backend
- 5+ people → Any path. Path C's microservice split becomes manageable

**Q5: Is this tool ever going to be multi-tenant (multiple agencies on one instance)?**
- Yes → Path C (Next.js SaaS architecture)
- No, always single-org → Path A or B

---

## 12. Transition Roadmap

### Regardless of path chosen — do this first (Week 1–2)

These fix the critical bugs and add the database without any framework decision:

1. Fix BUG-001: Cross-platform extraction command
2. Fix BUG-002: Cross-platform ADB discovery
3. Fix BUG-003: Python indentation error in `threat_scanner.py`
4. Fix BUG-004: Cross-platform report export command
5. Fix BUG-027: Python path auto-detection
6. Install PostgreSQL, run initial schema migration
7. Add `web/includes/db.php` PDO connection
8. Modify Python scripts to write to DB in addition to flat files

---

### Path B transition (Laravel) — Weeks 3–12

**Weeks 3–4: Laravel scaffold**
- `composer create-project laravel/laravel` alongside existing `web/`
- Configure PostgreSQL, Redis, Sanctum, Horizon
- Port `web/includes/config.php` → Laravel config files
- Port DB schema → Eloquent models + Artisan migrations

**Weeks 5–6: Auth + case management**
- Login/logout with Sanctum tokens
- Case CRUD: create case, assign investigators, set classification
- Device registration per case
- All API routes protected by `auth:sanctum`

**Weeks 7–8: Extraction pipeline via jobs**
- `ExtractionJob` dispatches Python via `Process` (no `exec()`)
- Progress via Redis pub/sub → Vue frontend reactive updates
- Extraction history page (replaces direct file reads)

**Weeks 9–10: Evidence display from DB**
- Port all `web/pages/` to Inertia + Vue components reading from DB
- SMS, calls, contacts, logcat all served from PostgreSQL
- Retire flat file reading from all pages

**Weeks 11–12: Chain of custody + reports**
- Evidence hasher writes all hashes to `extraction_file_hashes` table
- Court-ready HTML/PDF report generation
- Complete audit trail

---

### Path A transition (FastAPI) — for v4, after Laravel is stable

Once Laravel is in production:

1. Build FastAPI service alongside Laravel (both run simultaneously)
2. Port analysis modules to FastAPI endpoints one at a time
3. Update frontend to call FastAPI endpoints where ported
4. Once all endpoints migrated, retire Laravel
5. Build React frontend to replace Vue + Inertia

This is a 3-month project done while the Laravel version is in active production use.

---

*This document should be reviewed with the development team before committing to a path. The recommendation (Path B now, Path A for v4) is based on the assumption of a small team with mixed PHP/Python experience and a need to ship a working tool quickly.*
