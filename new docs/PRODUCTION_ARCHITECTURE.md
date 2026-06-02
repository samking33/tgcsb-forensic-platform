# TGCSB Forensic Platform — Production Architecture
**Version:** 3.0 Target  
**Date:** 2026-05-31

---

## DATABASE DECISION: PostgreSQL

**Why PostgreSQL over MongoDB:**

| Concern | PostgreSQL | MongoDB |
|---|---|---|
| Forensic data integrity | ACID transactions — evidence can never be partially written | Eventual consistency risks partial writes |
| Relational case data | Cases → Devices → Extractions → Records fits naturally | Requires manual reference management |
| Geospatial location | PostGIS — industry standard for GPS track analysis | Geospatial is limited without Atlas |
| Full-text log search | Native `tsvector` + GIN index, no extra service | Requires Atlas Search or manual indexing |
| Audit trail integrity | Row-level triggers, immutable history tables | Can't enforce immutability at DB level |
| Semi-structured data | `JSONB` columns handle threat findings, device metadata | MongoDB's core strength, but PostgreSQL JSONB matches it 90% |
| Court admissibility | Mature, understood by expert witnesses | Newer, less established in legal contexts |
| Regulatory compliance | SOC2, ISO 27001 certified deployments common | Less common in law enforcement tech stacks |

**MongoDB would make sense if:** You had millions of heterogeneous documents per day with no schema. That's not the case here — forensic case data is structured, record counts are in the thousands not millions, and data integrity beats write throughput.

**Use Redis alongside PostgreSQL for:** live log streaming buffers, extraction job progress, session state, and real-time WebSocket pub/sub.

---

## SYSTEM ARCHITECTURE OVERVIEW

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                            TGCSB FORENSIC PLATFORM v3.0                      │
└─────────────────────────────────────────────────────────────────────────────┘

┌──────────────┐    USB/ADB     ┌──────────────────────────────────────────┐
│   Android    │◄──────────────►│            EXTRACTION LAYER              │
│   Device     │                │  Python ADB Workers (scripts/)           │
└──────────────┘                │  • android_logs.py                       │
                                │  • enhanced_extraction.py                │
                                │  • process_all.py (orchestrator)         │
                                └─────────────────┬────────────────────────┘
                                                  │ writes structured records
                                                  ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                            DATA LAYER                                        │
│                                                                              │
│  ┌──────────────────────────┐     ┌──────────────────────────┐              │
│  │   PostgreSQL 16          │     │   Redis 7                │              │
│  │   Primary database       │     │   Real-time / ephemeral  │              │
│  │   • Cases                │     │   • Job progress         │              │
│  │   • Investigators        │     │   • Live log stream      │              │
│  │   • Devices              │     │   • Session tokens       │              │
│  │   • Extractions          │     │   • WebSocket pub/sub    │              │
│  │   • Evidence records     │     │   • Rate limiting        │              │
│  │   • Audit trail          │     └──────────────────────────┘              │
│  │   • Threat findings      │                                               │
│  │   • Location points      │     ┌──────────────────────────┐              │
│  │     (PostGIS)            │     │   File Storage           │              │
│  └──────────────────────────┘     │   /storage/              │              │
│                                   │   • Raw ADB dumps        │              │
│                                   │   • Evidence containers  │              │
│                                   │   • Generated reports    │              │
│                                   │   • Device images        │              │
│                                   └──────────────────────────┘              │
└─────────────────────────────────────────────────────────────────────────────┘
                                          │
                                reads/writes
                                          │
                                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         APPLICATION LAYER                                    │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                   PHP 8.3 Web Application                           │    │
│  │   web/api/      ← REST API endpoints                                │    │
│  │   web/pages/    ← Server-rendered UI pages                         │    │
│  │   web/includes/ ← Services, Models, Repositories                   │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
│  ┌─────────────────────────────────────────────────────────────────────┐    │
│  │                   Python Analysis Workers                           │    │
│  │   analysis/     ← Forensic analysis modules                        │    │
│  │   Called by PHP via job queue, write results to PostgreSQL          │    │
│  └─────────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────────┘
                                          │
                                          ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                                    │
│                                                                              │
│   Browser ← PHP-rendered pages + Alpine.js/Chart.js/Leaflet + WebSockets   │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

---

## COMPLETE DATABASE SCHEMA

### Conventions
- All primary keys: `BIGSERIAL` (auto-incrementing 64-bit int)
- All timestamps: `TIMESTAMPTZ` (UTC stored, timezone-aware)
- Soft deletes: `deleted_at TIMESTAMPTZ NULL`
- Immutable evidence tables: no UPDATE/DELETE permitted (enforced by trigger)
- Audit log: append-only, never modified

---

### SCHEMA: accounts & access control

```sql
-- Investigators / users
CREATE TABLE investigators (
    id              BIGSERIAL PRIMARY KEY,
    badge_number    TEXT NOT NULL UNIQUE,
    full_name       TEXT NOT NULL,
    email           TEXT NOT NULL UNIQUE,
    password_hash   TEXT NOT NULL,                  -- bcrypt
    role            TEXT NOT NULL DEFAULT 'analyst' -- 'admin','analyst','viewer','auditor'
    department      TEXT,
    is_active       BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at   TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Active sessions
CREATE TABLE sessions (
    id              TEXT PRIMARY KEY,               -- random 64-char hex
    investigator_id BIGINT NOT NULL REFERENCES investigators(id),
    ip_address      INET NOT NULL,
    user_agent      TEXT,
    expires_at      TIMESTAMPTZ NOT NULL,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sessions_investigator ON sessions(investigator_id);
CREATE INDEX idx_sessions_expires ON sessions(expires_at);
```

---

### SCHEMA: cases

```sql
-- A case groups one or more device examinations
CREATE TABLE cases (
    id              BIGSERIAL PRIMARY KEY,
    case_number     TEXT NOT NULL UNIQUE,           -- e.g. TGCSB-2026-0042
    title           TEXT NOT NULL,
    description     TEXT,
    status          TEXT NOT NULL DEFAULT 'open',   -- 'open','closed','archived'
    classification  TEXT NOT NULL DEFAULT 'restricted', -- 'public','restricted','confidential','secret'
    lead_investigator_id BIGINT REFERENCES investigators(id),
    court_reference TEXT,
    opened_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    closed_at       TIMESTAMPTZ,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

-- Which investigators can access which cases (many-to-many)
CREATE TABLE case_investigators (
    case_id         BIGINT NOT NULL REFERENCES cases(id),
    investigator_id BIGINT NOT NULL REFERENCES investigators(id),
    access_level    TEXT NOT NULL DEFAULT 'read',   -- 'read','write','admin'
    granted_by      BIGINT REFERENCES investigators(id),
    granted_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (case_id, investigator_id)
);

-- Notes attached to a case
CREATE TABLE case_notes (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT NOT NULL REFERENCES cases(id),
    investigator_id BIGINT NOT NULL REFERENCES investigators(id),
    content         TEXT NOT NULL,
    is_pinned       BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
    -- deliberately no updated_at: notes are append-only
);
```

---

### SCHEMA: devices & extractions

```sql
-- A physical device examined in one or more cases
CREATE TABLE devices (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT NOT NULL REFERENCES cases(id),
    -- Hardware identity
    imei            TEXT,
    imei2           TEXT,
    serial_number   TEXT,
    adb_serial      TEXT,                           -- ADB device identifier
    manufacturer    TEXT,
    model           TEXT,
    brand           TEXT,
    -- Software state
    android_version TEXT,
    sdk_level       INTEGER,
    build_fingerprint TEXT,
    kernel_version  TEXT,
    -- Physical state
    is_rooted       BOOLEAN,
    bootloader_locked BOOLEAN,
    is_encrypted    BOOLEAN,
    -- Custody
    seized_at       TIMESTAMPTZ,
    seized_by       BIGINT REFERENCES investigators(id),
    seizure_location TEXT,
    chain_of_custody_notes TEXT,
    -- Meta
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_devices_case ON devices(case_id);
CREATE INDEX idx_devices_imei ON devices(imei) WHERE imei IS NOT NULL;

-- A single extraction session from a device
CREATE TABLE extractions (
    id              BIGSERIAL PRIMARY KEY,
    device_id       BIGINT NOT NULL REFERENCES devices(id),
    investigator_id BIGINT NOT NULL REFERENCES investigators(id),
    -- Extraction details
    extraction_type TEXT NOT NULL DEFAULT 'logical',  -- 'logical','backup','manual'
    status          TEXT NOT NULL DEFAULT 'pending',  -- 'pending','running','complete','failed'
    started_at      TIMESTAMPTZ,
    completed_at    TIMESTAMPTZ,
    duration_seconds INTEGER,
    -- What was extracted
    extracted_logcat    BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_sms       BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_calls     BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_contacts  BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_location  BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_packages  BOOLEAN NOT NULL DEFAULT FALSE,
    extracted_system    BOOLEAN NOT NULL DEFAULT FALSE,
    -- Integrity
    manifest_hash   TEXT,                           -- SHA-256 of the complete hash manifest
    manifest_path   TEXT,                           -- Path to hash manifest file in storage
    -- Error info
    error_message   TEXT,
    error_trace     TEXT,
    -- Meta
    notes           TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
    -- no updated_at: extractions are immutable once complete
);

CREATE INDEX idx_extractions_device ON extractions(device_id);
CREATE INDEX idx_extractions_status ON extractions(status);

-- Per-file integrity hashes within an extraction
CREATE TABLE extraction_file_hashes (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    filename        TEXT NOT NULL,
    file_path       TEXT NOT NULL,
    sha256          TEXT NOT NULL,
    md5             TEXT NOT NULL,
    file_size_bytes BIGINT NOT NULL,
    hashed_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (extraction_id, filename)
);
```

---

### SCHEMA: evidence records (immutable)

```sql
-- SMS messages extracted from device
CREATE TABLE sms_records (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    -- Content
    address         TEXT,                           -- phone number
    body            TEXT,
    type            TEXT,                           -- 'inbox','sent','draft','outbox'
    date_sent       TIMESTAMPTZ,
    date_received   TIMESTAMPTZ,
    thread_id       TEXT,
    -- Status
    is_read         BOOLEAN,
    is_deleted      BOOLEAN NOT NULL DEFAULT FALSE, -- flagged if recovered from deleted state
    -- Forensic
    source_row_id   TEXT,                           -- original row ID from device DB
    raw_data        JSONB,                          -- full raw record for reference
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_sms_extraction ON sms_records(extraction_id);
CREATE INDEX idx_sms_address ON sms_records(address);
CREATE INDEX idx_sms_date ON sms_records(date_sent);
-- Full text search on SMS content
CREATE INDEX idx_sms_body_fts ON sms_records USING GIN(to_tsvector('english', COALESCE(body, '')));

-- Call records
CREATE TABLE call_records (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    number          TEXT,
    contact_name    TEXT,
    duration_seconds INTEGER,
    type            TEXT,                           -- 'incoming','outgoing','missed','rejected'
    call_at         TIMESTAMPTZ,
    is_deleted      BOOLEAN NOT NULL DEFAULT FALSE,
    source_row_id   TEXT,
    raw_data        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_calls_extraction ON call_records(extraction_id);
CREATE INDEX idx_calls_number ON call_records(number);
CREATE INDEX idx_calls_date ON call_records(call_at);

-- Contacts
CREATE TABLE contact_records (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    display_name    TEXT,
    phone_numbers   JSONB,                          -- [{number, type, normalized}]
    emails          JSONB,
    organization    TEXT,
    last_contacted  TIMESTAMPTZ,
    times_contacted INTEGER,
    is_deleted      BOOLEAN NOT NULL DEFAULT FALSE,
    source_row_id   TEXT,
    raw_data        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_contacts_extraction ON contact_records(extraction_id);
CREATE INDEX idx_contacts_name_fts ON contact_records USING GIN(to_tsvector('english', COALESCE(display_name, '')));
```

---

### SCHEMA: location data (PostGIS)

```sql
-- Enable PostGIS
CREATE EXTENSION IF NOT EXISTS postgis;

-- Location data points
CREATE TABLE location_points (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    -- Coordinates (PostGIS geography type for accurate distance calculations)
    location        GEOGRAPHY(POINT, 4326) NOT NULL,
    latitude        DOUBLE PRECISION NOT NULL,
    longitude       DOUBLE PRECISION NOT NULL,
    altitude_m      DOUBLE PRECISION,
    accuracy_m      DOUBLE PRECISION,              -- uncertainty radius in metres
    -- Source
    source          TEXT NOT NULL,                 -- 'gps','cell_tower','wifi','fused','app'
    provider        TEXT,                          -- 'gps','network','fused'
    app_package     TEXT,                          -- which app triggered location
    -- Time
    recorded_at     TIMESTAMPTZ NOT NULL,
    -- Cell tower metadata (when source='cell_tower')
    cell_mcc        INTEGER,
    cell_mnc        INTEGER,
    cell_lac        INTEGER,
    cell_cid        INTEGER,
    cell_signal_dbm INTEGER,
    -- WiFi metadata (when source='wifi')
    wifi_bssid      TEXT,
    wifi_ssid       TEXT,
    wifi_signal_dbm INTEGER,
    -- Meta
    raw_data        JSONB,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_location_extraction ON location_points(extraction_id);
CREATE INDEX idx_location_time ON location_points(recorded_at);
-- Spatial index — enables fast radius searches, clustering, geofence queries
CREATE INDEX idx_location_geo ON location_points USING GIST(location);

-- Geofences defined by investigators (for alerting or filtering)
CREATE TABLE geofences (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT NOT NULL REFERENCES cases(id),
    label           TEXT NOT NULL,                 -- e.g. "Crime Scene", "Suspect's Home"
    center          GEOGRAPHY(POINT, 4326) NOT NULL,
    radius_m        DOUBLE PRECISION NOT NULL,
    boundary        GEOGRAPHY(POLYGON, 4326),      -- custom polygon if not circular
    created_by      BIGINT REFERENCES investigators(id),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

### SCHEMA: logcat & timeline

```sql
-- Individual logcat lines (high volume — partitioned by extraction)
CREATE TABLE logcat_lines (
    id              BIGSERIAL,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    -- Parsed fields
    logged_at       TIMESTAMPTZ,
    pid             INTEGER,
    tid             INTEGER,
    severity        CHAR(1),                       -- V,D,I,W,E,F
    tag             TEXT,
    message         TEXT NOT NULL,
    -- Classification
    log_type        TEXT,                          -- Application,System,Network,etc.
    -- Raw
    raw_line        TEXT NOT NULL,
    line_number     BIGINT NOT NULL,               -- position in original file
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (id, extraction_id)
) PARTITION BY LIST (extraction_id);
-- Create partition per extraction: CREATE TABLE logcat_lines_ext_42 PARTITION OF logcat_lines FOR VALUES IN (42);

-- Full-text search on logcat
CREATE INDEX idx_logcat_fts ON logcat_lines USING GIN(to_tsvector('english', COALESCE(tag,'') || ' ' || message));
CREATE INDEX idx_logcat_severity ON logcat_lines(extraction_id, severity);
CREATE INDEX idx_logcat_time ON logcat_lines(extraction_id, logged_at);
CREATE INDEX idx_logcat_tag ON logcat_lines(extraction_id, tag);

-- Unified timeline events (derived from logcat + call + SMS + location)
CREATE TABLE timeline_events (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    occurred_at     TIMESTAMPTZ NOT NULL,
    event_type      TEXT NOT NULL,                 -- 'sms','call','location','app_launch','screen','network','install'
    title           TEXT NOT NULL,
    description     TEXT,
    source_table    TEXT,                          -- 'sms_records','call_records', etc.
    source_id       BIGINT,                        -- FK to source record
    metadata        JSONB,
    is_flagged      BOOLEAN NOT NULL DEFAULT FALSE,-- investigator-flagged events
    flag_note       TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_timeline_extraction ON timeline_events(extraction_id);
CREATE INDEX idx_timeline_time ON timeline_events(extraction_id, occurred_at);
CREATE INDEX idx_timeline_type ON timeline_events(extraction_id, event_type);
CREATE INDEX idx_timeline_flagged ON timeline_events(extraction_id) WHERE is_flagged = TRUE;
```

---

### SCHEMA: installed packages & threat analysis

```sql
-- All installed packages on device
CREATE TABLE installed_packages (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    package_name    TEXT NOT NULL,
    app_label       TEXT,
    version_name    TEXT,
    version_code    BIGINT,
    install_source  TEXT,                          -- com.android.vending, manual, etc.
    first_install   TIMESTAMPTZ,
    last_update     TIMESTAMPTZ,
    is_system_app   BOOLEAN NOT NULL DEFAULT FALSE,
    is_enabled      BOOLEAN NOT NULL DEFAULT TRUE,
    -- Permissions
    permissions     TEXT[],                        -- array of granted permissions
    -- Risk
    is_sideloaded   BOOLEAN NOT NULL DEFAULT FALSE,
    apk_sha256      TEXT,
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (extraction_id, package_name)
);

CREATE INDEX idx_packages_extraction ON installed_packages(extraction_id);
CREATE INDEX idx_packages_sideloaded ON installed_packages(extraction_id) WHERE is_sideloaded = TRUE;

-- Threat findings from analysis
CREATE TABLE threat_findings (
    id              BIGSERIAL PRIMARY KEY,
    extraction_id   BIGINT NOT NULL REFERENCES extractions(id),
    -- Classification
    finding_type    TEXT NOT NULL,                 -- 'malware','spyware','stalkerware','rat','adware','data_exfil','priv_esc','suspicious_perm'
    severity        TEXT NOT NULL,                 -- 'critical','high','medium','low','info'
    title           TEXT NOT NULL,
    description     TEXT NOT NULL,
    recommendation  TEXT,
    -- Evidence
    evidence_source TEXT,                          -- 'logcat','package_list','permissions','network'
    evidence_line   TEXT,                          -- the specific log line or value
    package_name    TEXT,
    rule_id         TEXT,                          -- which signature rule triggered
    -- State
    status          TEXT NOT NULL DEFAULT 'new',   -- 'new','reviewed','false_positive','confirmed','escalated'
    reviewed_by     BIGINT REFERENCES investigators(id),
    reviewed_at     TIMESTAMPTZ,
    review_note     TEXT,
    -- Meta
    risk_score      SMALLINT,                      -- 0-100
    raw_data        JSONB,
    detected_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    created_at      TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_threats_extraction ON threat_findings(extraction_id);
CREATE INDEX idx_threats_severity ON threat_findings(extraction_id, severity);
CREATE INDEX idx_threats_status ON threat_findings(status);
```

---

### SCHEMA: audit trail (append-only, immutable)

```sql
-- Complete audit log — every significant action recorded
-- This table MUST never have UPDATE or DELETE (enforced by trigger below)
CREATE TABLE audit_log (
    id              BIGSERIAL PRIMARY KEY,
    -- Who
    investigator_id BIGINT REFERENCES investigators(id),
    ip_address      INET,
    session_id      TEXT,
    -- What
    action          TEXT NOT NULL,                 -- 'login','logout','case.create','extraction.start','evidence.view','report.export','data.delete', etc.
    resource_type   TEXT,                          -- 'case','device','extraction','report'
    resource_id     BIGINT,
    -- Detail
    description     TEXT,
    old_value       JSONB,                         -- for update operations
    new_value       JSONB,
    -- Result
    success         BOOLEAN NOT NULL DEFAULT TRUE,
    error_message   TEXT,
    -- When
    occurred_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    -- Integrity: hash of (id || investigator_id || action || resource_id || occurred_at)
    -- Allows verification that log has not been tampered with
    integrity_hash  TEXT NOT NULL
);

-- Prevent any modification of audit records
CREATE OR REPLACE FUNCTION prevent_audit_modification()
RETURNS TRIGGER AS $$
BEGIN
    RAISE EXCEPTION 'Audit log records are immutable. Attempted % on audit_log.', TG_OP;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER audit_log_immutable
    BEFORE UPDATE OR DELETE ON audit_log
    FOR EACH ROW EXECUTE FUNCTION prevent_audit_modification();

-- Indexes for audit queries
CREATE INDEX idx_audit_investigator ON audit_log(investigator_id);
CREATE INDEX idx_audit_action ON audit_log(action);
CREATE INDEX idx_audit_resource ON audit_log(resource_type, resource_id);
CREATE INDEX idx_audit_time ON audit_log(occurred_at DESC);

-- Reports generated
CREATE TABLE reports (
    id              BIGSERIAL PRIMARY KEY,
    case_id         BIGINT NOT NULL REFERENCES cases(id),
    extraction_id   BIGINT REFERENCES extractions(id),
    generated_by    BIGINT NOT NULL REFERENCES investigators(id),
    report_type     TEXT NOT NULL DEFAULT 'full',  -- 'full','summary','threat','location','timeline'
    format          TEXT NOT NULL DEFAULT 'html',  -- 'html','pdf'
    file_path       TEXT NOT NULL,
    file_hash_sha256 TEXT NOT NULL,
    file_size_bytes BIGINT,
    generated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
```

---

## REVISED APPLICATION ARCHITECTURE

### Directory Structure (target state)

```
tgcsb-forensic/
├── docker-compose.yml              ← One-command startup
├── .env                            ← Secrets (never committed)
├── .env.example
│
├── database/
│   ├── migrations/                 ← Numbered SQL migration files
│   │   ├── 001_initial_schema.sql
│   │   ├── 002_add_postgis.sql
│   │   └── ...
│   ├── seeds/                      ← Test/demo data
│   └── schema.sql                  ← Full schema (generated)
│
├── web/                            ← PHP application (unchanged location)
│   ├── index.php
│   ├── api/                        ← REST endpoints (all return JSON)
│   ├── pages/                      ← UI pages
│   ├── includes/
│   │   ├── config.php
│   │   ├── db.php                  ← NEW: PDO connection singleton
│   │   ├── auth.php                ← NEW: Session/token validation middleware
│   │   ├── repositories/           ← NEW: All DB queries live here
│   │   │   ├── CaseRepository.php
│   │   │   ├── ExtractionRepository.php
│   │   │   ├── EvidenceRepository.php
│   │   │   ├── ThreatRepository.php
│   │   │   ├── AuditRepository.php
│   │   │   └── LocationRepository.php
│   │   ├── models/                 ← Data transfer objects (no DB logic)
│   │   ├── services/               ← Business logic
│   │   ├── parsers/
│   │   ├── collectors/
│   │   └── interfaces/
│   └── assets/
│
├── scripts/                        ← Python ADB extraction (mostly unchanged)
│   ├── android_logs.py             ← Modified: writes to DB not flat files
│   ├── enhanced_extraction.py      ← Modified: writes to DB
│   ├── process_all.py              ← Modified: orchestrates via DB job queue
│   └── db_writer.py                ← NEW: Python → PostgreSQL writer
│
├── analysis/                       ← Python forensic analysis (mostly unchanged)
│   ├── threat_detector.py          ← Modified: reads/writes DB
│   ├── evidence_hasher.py          ← Modified: uses DB extraction records
│   └── ...
│
├── storage/                        ← Raw files (outside web root)
│   ├── cases/
│   │   └── {case_id}/
│   │       └── {extraction_id}/
│   │           ├── raw/            ← Original ADB dumps
│   │           ├── hashes/         ← Hash manifest files
│   │           └── reports/        ← Generated report files
│   └── tmp/                        ← Temporary extraction workspace
│
└── docker/
    ├── php/Dockerfile
    ├── python/Dockerfile
    └── nginx/nginx.conf
```

---

## REQUEST / RESPONSE FLOW (Production)

### Extraction Flow

```
Browser                 PHP API              Redis               Python Worker        PostgreSQL
  │                       │                    │                      │                   │
  │ POST /api/extract.php │                    │                      │                   │
  │──────────────────────►│                    │                      │                   │
  │                       │ INSERT extraction  │                      │                   │
  │                       │   status=pending   │                      │                   │
  │                       │──────────────────────────────────────────────────────────────►│
  │                       │                    │                      │                   │
  │                       │ RPUSH job_queue    │                      │                   │
  │                       │ {extraction_id}    │                      │                   │
  │                       │───────────────────►│                      │                   │
  │                       │                    │                      │                   │
  │ {extraction_id, ok}   │                    │                      │                   │
  │◄──────────────────────│                    │                      │                   │
  │                       │                    │ BLPOP job_queue      │                   │
  │                       │                    │◄─────────────────────│                   │
  │                       │                    │ {extraction_id}      │                   │
  │                       │                    │─────────────────────►│                   │
  │                       │                    │                      │ UPDATE status=running
  │                       │                    │                      │──────────────────►│
  │                       │                    │                      │                   │
  │                       │                    │                      │ ADB extraction    │
  │                       │                    │                      │ INSERT records    │
  │                       │                    │                      │──────────────────►│
  │                       │                    │                      │                   │
  │ GET /api/progress.php │                    │ HGET progress:{id}   │                   │
  │──────────────────────►│───────────────────►│                      │                   │
  │ {percent: 47}         │◄───────────────────│                      │                   │
  │◄──────────────────────│                    │                      │                   │
```

---

## NEW PHP DATABASE LAYER

### `web/includes/db.php` — Connection singleton

```php
<?php
class DB {
    private static ?PDO $pdo = null;

    public static function connection(): PDO {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
                getenv('DB_HOST') ?: 'localhost',
                getenv('DB_PORT') ?: '5432',
                getenv('DB_NAME') ?: 'tgcsb_forensic',
                getenv('DB_SSLMODE') ?: 'require'
            );
            self::$pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}
```

### `web/includes/auth.php` — Request authentication

```php
<?php
class Auth {
    public static function require(): array {
        $sessionId = $_COOKIE['session_id'] ?? $_SERVER['HTTP_X_SESSION_TOKEN'] ?? null;
        if (!$sessionId) self::deny();

        $row = DB::query(
            'SELECT s.investigator_id, i.role, i.full_name, i.badge_number
             FROM sessions s JOIN investigators i ON i.id = s.investigator_id
             WHERE s.id = ? AND s.expires_at > NOW()',
            [$sessionId]
        )->fetch();

        if (!$row) self::deny();
        return $row;
    }

    public static function requireCaseAccess(int $caseId, string $level = 'read'): void {
        $user = self::require();
        // Admins bypass
        if ($user['role'] === 'admin') return;
        $access = DB::query(
            'SELECT access_level FROM case_investigators WHERE case_id=? AND investigator_id=?',
            [$caseId, $user['investigator_id']]
        )->fetchColumn();
        if (!$access || ($level === 'write' && $access === 'read')) {
            http_response_code(403);
            exit(json_encode(['error' => 'Access denied']));
        }
    }

    private static function deny(): void {
        http_response_code(401);
        exit(json_encode(['error' => 'Authentication required']));
    }
}
```

### Example Repository pattern

```php
<?php
class ExtractionRepository {
    public function create(int $deviceId, int $investigatorId, array $options): int {
        DB::query('
            INSERT INTO extractions (device_id, investigator_id, extracted_logcat, extracted_sms, ...)
            VALUES (?, ?, ?, ?, ...)
        ', [$deviceId, $investigatorId, $options['logcat'], $options['sms']]);
        return (int)DB::connection()->lastInsertId();
    }

    public function getWithStats(int $id): ?array {
        return DB::query('
            SELECT e.*,
                   (SELECT COUNT(*) FROM sms_records WHERE extraction_id = e.id) AS sms_count,
                   (SELECT COUNT(*) FROM call_records WHERE extraction_id = e.id) AS call_count,
                   (SELECT COUNT(*) FROM threat_findings WHERE extraction_id = e.id) AS threat_count
            FROM extractions e WHERE e.id = ?
        ', [$id])->fetch() ?: null;
    }
}
```

---

## NEW PYTHON DATABASE LAYER

### `scripts/db_writer.py` — Python → PostgreSQL

```python
import os
import psycopg2
import psycopg2.extras
from contextlib import contextmanager

@contextmanager
def get_connection():
    conn = psycopg2.connect(
        host=os.getenv('DB_HOST', 'localhost'),
        port=int(os.getenv('DB_PORT', 5432)),
        dbname=os.getenv('DB_NAME', 'tgcsb_forensic'),
        user=os.getenv('DB_USER'),
        password=os.getenv('DB_PASS'),
        sslmode=os.getenv('DB_SSLMODE', 'require'),
    )
    conn.autocommit = False
    try:
        yield conn
        conn.commit()
    except Exception:
        conn.rollback()
        raise
    finally:
        conn.close()

def insert_sms_records(extraction_id: int, records: list[dict]) -> int:
    with get_connection() as conn:
        with conn.cursor() as cur:
            psycopg2.extras.execute_values(cur, """
                INSERT INTO sms_records
                    (extraction_id, address, body, type, date_sent, date_received, thread_id, is_read, source_row_id, raw_data)
                VALUES %s
                ON CONFLICT DO NOTHING
            """, [(
                extraction_id,
                r.get('address'), r.get('body'), r.get('type'),
                r.get('date_sent'), r.get('date_received'), r.get('thread_id'),
                r.get('read'), r.get('_id'), psycopg2.extras.Json(r)
            ) for r in records])
        return cur.rowcount

def update_extraction_progress(extraction_id: int, percent: int, redis_client=None):
    """Write progress to both DB (for persistence) and Redis (for real-time UI)."""
    if redis_client:
        redis_client.hset(f'progress:{extraction_id}', mapping={
            'percent': percent, 'updated_at': time.time()
        })
    if percent == 100:
        with get_connection() as conn:
            with conn.cursor() as cur:
                cur.execute("""
                    UPDATE extractions
                    SET status = 'complete', completed_at = NOW(),
                        duration_seconds = EXTRACT(EPOCH FROM NOW() - started_at)
                    WHERE id = %s
                """, (extraction_id,))
```

---

## DOCKER DEPLOYMENT

### `docker-compose.yml`

```yaml
version: '3.9'

services:
  postgres:
    image: postgis/postgis:16-3.4
    environment:
      POSTGRES_DB: tgcsb_forensic
      POSTGRES_USER: ${DB_USER}
      POSTGRES_PASSWORD: ${DB_PASS}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./database/migrations:/docker-entrypoint-initdb.d  # auto-runs on first start
    ports:
      - "5432:5432"
    healthcheck:
      test: ["CMD", "pg_isready", "-U", "${DB_USER}"]
      interval: 5s
      retries: 10

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASS}
    volumes:
      - redis_data:/data

  php:
    build: ./docker/php
    depends_on:
      postgres:
        condition: service_healthy
    environment:
      DB_HOST: postgres
      DB_PORT: 5432
      DB_NAME: tgcsb_forensic
      DB_USER: ${DB_USER}
      DB_PASS: ${DB_PASS}
      REDIS_HOST: redis
      REDIS_PASS: ${REDIS_PASS}
    volumes:
      - ./web:/var/www/html
      - ./storage:/storage
    ports:
      - "8080:80"

  python-worker:
    build: ./docker/python
    depends_on:
      - postgres
      - redis
    environment:
      DB_HOST: postgres
      DB_USER: ${DB_USER}
      DB_PASS: ${DB_PASS}
      REDIS_HOST: redis
      REDIS_PASS: ${REDIS_PASS}
    volumes:
      - ./scripts:/app/scripts
      - ./analysis:/app/analysis
      - ./storage:/storage
      - /dev/bus/usb:/dev/bus/usb  # USB ADB passthrough
    privileged: true               # required for ADB USB access
    command: python3 /app/scripts/worker.py

volumes:
  postgres_data:
  redis_data:
```

---

## UPDATED .env STRUCTURE

```bash
# ── Database ────────────────────────────────────────────────
DB_HOST=localhost
DB_PORT=5432
DB_NAME=tgcsb_forensic
DB_USER=forensic_app
DB_PASS=                   # strong random password
DB_SSLMODE=require

# ── Redis ───────────────────────────────────────────────────
REDIS_HOST=localhost
REDIS_PORT=6379
REDIS_PASS=                # strong random password

# ── App ─────────────────────────────────────────────────────
APP_ENV=production          # 'development' or 'production'
APP_SECRET=                 # 64-char random hex, used for session signing
APP_URL=http://localhost:8080

# ── Python ──────────────────────────────────────────────────
PYTHON_PATH=python3
STORAGE_PATH=/storage

# ── External APIs ───────────────────────────────────────────
OPENCELLID_API_KEY=
UNWIREDLABS_API_KEY=
VIRUSTOTAL_API_KEY=        # optional, for APK hash lookup

# ── Feature Flags ───────────────────────────────────────────
DEBUG_MODE=false
ENABLE_VIRUSTOTAL=false
ENABLE_CLOUD_GEOLOCATION=false
```

---

## MIGRATION PATH FROM CURRENT STATE

### Phase 1 — Add DB alongside flat files (no breaking changes)
1. Add `docker-compose.yml` with PostgreSQL + Redis
2. Create `web/includes/db.php` and all repositories
3. Create `scripts/db_writer.py`
4. Modify extraction scripts to write to BOTH flat files AND DB
5. Fix all critical bugs (BUG-001 through BUG-007)

### Phase 2 — PHP reads from DB instead of flat files
6. Replace file-reading logic in all PHP API endpoints with repository calls
7. Add authentication (login page, session management)
8. Add case management UI
9. Migrate history: write a one-time script to import any existing flat-file logs into DB

### Phase 3 — Remove flat files entirely
10. Remove flat-file writing from Python scripts (DB is now source of truth)
11. Move raw ADB dumps to `storage/` (keep for chain of custody, but not used for display)
12. Enable all disabled pages now that they have real DB data sources
13. Complete chain of custody: hash all files, store in `extraction_file_hashes`

### Phase 4 — Production hardening
14. Enable SSL on PostgreSQL
15. Enable authentication on all endpoints
16. Docker deployment
17. Run penetration test against all SEC- issues
18. Complete PDF report generation (court-ready format)

---

## TECHNOLOGY SUMMARY

| Layer | Technology | Why |
|---|---|---|
| Primary database | PostgreSQL 16 + PostGIS | ACID, geospatial, full-text search, forensic integrity |
| Real-time / cache | Redis 7 | Job queue, progress streaming, sessions |
| Web backend | PHP 8.3 | Already in use, mature, good PDO support |
| Analysis backend | Python 3.11+ | Already in use, ADB/forensic ecosystem |
| File storage | Local filesystem (`storage/`) | Raw evidence files, hashes, reports |
| Containerisation | Docker + Compose | Eliminates Windows/macOS/Linux compat issues |
| Frontend | Vanilla JS + Chart.js + Leaflet | Already in use |
| Authentication | Session tokens (bcrypt + CSPRNG) | Simple, auditable, no external dependency |

---

*This document supersedes `docs/ARCHITECTURE.md`. Implement Phase 1 first — it provides immediate value without requiring a full rewrite.*
