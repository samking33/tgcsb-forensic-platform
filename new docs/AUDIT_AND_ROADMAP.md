# TGCSB Android Forensic Tool — Full Audit & Roadmap
**Date:** 2026-05-31  
**Version Audited:** v2.1.0  
**Scope:** Full codebase — PHP web app, Python extraction backend, analysis modules, JavaScript UI

---

## TABLE OF CONTENTS
1. [Architecture Overview](#1-architecture-overview)
2. [Critical Bugs — Blockers](#2-critical-bugs--blockers)
3. [High Priority Bugs](#3-high-priority-bugs)
4. [Medium Priority Issues](#4-medium-priority-issues)
5. [Low Priority / Code Quality](#5-low-priority--code-quality)
6. [Unimplemented / Stub Features](#6-unimplemented--stub-features)
7. [Security Vulnerabilities](#7-security-vulnerabilities)
8. [Industry Comparison](#8-industry-comparison)
9. [Feature Roadmap — What to Add](#9-feature-roadmap--what-to-add)
10. [Priority Fix Order](#10-priority-fix-order)

---

## 1. Architecture Overview

```
Android Device (ADB/USB)
        │
        ▼
scripts/android_logs.py          ← ADB extraction (logcat, SMS, calls, location)
scripts/enhanced_extraction.py   ← Deep package/system extraction
scripts/process_all.py           ← Orchestration pipeline
        │
        ▼
logs/ directory                  ← Flat text files (sms_logs.txt, call_logs.txt, etc.)
        │
        ├──► analysis/*.py       ← Python forensic analysis modules
        │         │
        │         ▼
        │    logs/unified_timeline.json, threat_report.json, etc.
        │
        └──► web/ (PHP)          ← Web UI reads from logs/ directly
              ├── api/           ← API endpoints called by browser
              ├── pages/         ← UI pages
              ├── includes/      ← Services, parsers, collectors, models
              └── assets/js/     ← Frontend JavaScript
```

**The core problem:** PHP web app reads from `logs/` which is only populated after Python scripts run. No data = empty UI everywhere. There is no in-app extraction trigger that works on macOS/Linux.

---

## 2. Critical Bugs — Blockers

These prevent the app from functioning at all.

---

### BUG-001 — Extraction Completely Broken on macOS/Linux
**File:** `web/api/extract.php`  
**Severity:** CRITICAL — Core feature non-functional

The extraction pipeline uses Windows-only shell commands:
```php
$phpBin = dirname(dirname(__DIR__)) . '\\php\\php.exe';  // Windows path
$cmd = "start /B \"\" \"$phpBin\" ... > NUL 2>&1";       // Windows cmd
```
`start /B`, `\\` path separators, `NUL`, and `php.exe` are all Windows-specific. On macOS/Linux this silently fails and returns a false success message to the user.

**Fix:** Cross-platform process spawning:
```php
if (PHP_OS_FAMILY === 'Windows') {
    $cmd = "start /B \"\" \"$phpBin\" ...";
} else {
    $cmd = "nohup python3 " . escapeshellarg($scriptPath) . " > /dev/null 2>&1 &";
}
```

---

### BUG-002 — Live Stream ADB Paths All Windows-Only
**File:** `web/api/live-stream.php`  
**Severity:** CRITICAL — Live monitoring non-functional on macOS/Linux

All ADB binary discovery paths are hardcoded Windows paths:
```php
getenv('USERPROFILE') . '\\platform-tools\\adb.exe',
getenv('LOCALAPPDATA') . '\\Android\\Sdk\\platform-tools\\adb.exe',
```
`USERPROFILE` and `LOCALAPPDATA` env vars don't exist on macOS/Linux.

**Fix:** Detect OS and use appropriate paths:
```php
$candidates = PHP_OS_FAMILY === 'Windows' ? [
    getenv('USERPROFILE') . '\\platform-tools\\adb.exe',
] : [
    '/usr/local/bin/adb',
    '/opt/homebrew/bin/adb',
    getenv('HOME') . '/platform-tools/adb',
    'adb',  // PATH fallback
];
```

---

### BUG-003 — Syntax Error in threat_scanner.py (IndentationError)
**File:** `threat_scanner.py` line 278  
**Severity:** CRITICAL — File cannot be imported or executed

```python
        if critical or high:       # correct indentation
            ...
       if medium:                  # WRONG: 3-space indent (should be 8)
```
Python will raise `IndentationError: unindent does not match any outer indentation level`. The entire threat scanning system is broken at import time.

**Fix:** Correct indentation to 8 spaces on line 278.

---

### BUG-004 — Export Report Command is Windows-Only
**File:** `web/api/export-report.php` line 41  
**Severity:** CRITICAL — PDF/HTML report export broken on macOS/Linux

```php
$command = "cd /d \"$rootDir\" && $pythonPath -c \"from reporting import ...\"";
```
`cd /d` is a Windows `cmd.exe` flag. On macOS/Linux, `cd /d` tries to navigate to a directory literally named `/d`.

**Fix:** Use cross-platform `chdir()` or pass `cwd` parameter:
```php
$command = "cd " . escapeshellarg($rootDir) . " && python3 -c ...";
```

---

### BUG-005 — Timeline Never Loads (Broken Data Flow)
**File:** `web/api/timeline-acquisition.php`, `web/assets/js/timeline-viewer.js`  
**Severity:** CRITICAL — Timeline feature always shows empty

When `process_all.py` fails (or hasn't been run), the API returns `success: true` with empty events array. The UI shows a loading spinner indefinitely — no error state, no message.

**Fix:** API must validate that `unified_timeline.json` exists and has content before reporting success. UI must handle empty response with a clear "Run extraction first" message.

---

### BUG-006 — Dashboard Stats Always Zero
**File:** `web/api/stats.php`, `web/index.php`  
**Severity:** CRITICAL — Main dashboard shows no data

Stats API is called on page load but if log files are missing or empty, returns all zeros with no indication of why. The `countMatchesInFile()` function loads entire files into memory — on 100MB+ logcat files this will exhaust PHP memory limit.

**Fix:** Stream file counting instead of full load. Add status flags to response indicating whether source files exist.

---

### BUG-007 — Duplicate `_collect_context()` Function
**File:** `reporting.py` lines 221 and 355  
**Severity:** HIGH — First function definition is dead code, misleading

Function defined twice. Second definition silently overrides first. The first 83-line definition (lines 221–303) is never executed.

**Fix:** Remove the first definition (lines 221–303).

---

## 3. High Priority Bugs

---

### BUG-008 — Year Inference Wrong for Cross-Year Logs
**File:** `analysis/unified_timeline.py`, `analysis/fake_log_detector.py`  
**Severity:** HIGH — Evidence timestamps can be wrong by 1 year

```python
current_year = datetime.now().year
ts = datetime.strptime(f"{current_year}-{match.group(1)}", "%Y-%m-%d %H:%M:%S")
```
If a device captured Dec 29–31 2024 and is examined Jan 1 2025, those log entries get timestamped 2025-12-29. In forensic evidence this is chain-of-custody breaking.

**Fix:** If inferred timestamp is in the future by more than 7 days, subtract 1 year.

---

### BUG-009 — Hardcoded Relative Paths in reporting.py
**File:** `reporting.py` lines 40–59  
**Severity:** HIGH — Report generation fails if not run from project root

```python
path = "logs/system_properties.txt"
device_info_path = "logs/device_info.txt"
```
If PHP calls this via `python3 -c "from reporting import export_full_report; ..."`, the working directory may not be the project root.

**Fix:** Use `__file__`-based absolute paths:
```python
BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
path = os.path.join(BASE, "logs", "system_properties.txt")
```

---

### BUG-010 — Cascading Failures from Missing unified_timeline.json
**Files:** `analysis/check_gaps.py:5`, `analysis/analyze_timeline.py:3`, `analysis/show_ghosts.py:4`  
**Severity:** HIGH — Multiple analysis modules crash on first run

All three files open `logs/unified_timeline.json` without checking if it exists. If `unified_timeline.py` fails (or hasn't been run), all downstream analysis crashes with unhandled `FileNotFoundError`.

**Fix:** Add existence check at top of each file:
```python
if not os.path.exists('logs/unified_timeline.json'):
    print("ERROR: Run scripts/process_all.py first.")
    sys.exit(1)
```

---

### BUG-011 — Evidence Hasher Only Covers 14 of 30+ Files
**File:** `analysis/evidence_hasher.py` lines 154–169  
**Severity:** HIGH — Chain of custody incomplete

`enhanced_extraction.py` creates 30+ output files, but `evidence_hasher.py` only knows about 14. The remaining files are extracted but never hashed, invalidating the chain of custody for law enforcement use.

**Fix:** Auto-discover all files in `logs/` directory for hashing, not just hardcoded list.

---

### BUG-012 — Filter Logs Page Has No Table Element
**File:** `web/pages/filter-logs.php`, `web/assets/js/app.js` line 714  
**Severity:** HIGH — Filter feature produces no visible output

`app.js` calls `displayFilterResults()` which initializes a DataTable with id `filterTable`, but no element with that ID exists in `filter-logs.php`. Results silently disappear.

**Fix:** Add `<table id="filterTable">` to `filter-logs.php` or rewrite the function to target the correct existing element.

---

### BUG-013 — ADB Detection Fails Silently in enhanced_extraction.py
**File:** `scripts/enhanced_extraction.py` lines 216–239  
**Severity:** HIGH — Extraction looks successful but produces empty/corrupted data

```python
result = subprocess.run(..., check=False, timeout=15)
# saves result.stdout even when returncode != 0
```
When ADB fails, the stdout contains ADB error messages instead of package lists. These error strings get written to output files and then parsed as if they were real package data, corrupting downstream analysis.

**Fix:** Check `returncode != 0` and raise or skip writing the file.

---

### BUG-014 — Progress Polling Reads Stale Data
**File:** `web/api/extract.php`, `web/pages/extract-logs.php`  
**Severity:** HIGH — Progress bar shows wrong % from previous run

Progress file is written at extraction start but not cleared first. On re-extraction, polling may read the 100%-complete progress from the previous run and immediately show completion.

**Fix:** Write a sentinel value `{"percent": 0, "session": "<uuid>"}` before starting. Client validates session ID matches.

---

### BUG-015 — `appendLog()` Function Not Defined
**File:** `web/pages/extract-logs.php` lines 336–444  
**Severity:** HIGH — Extraction log output invisible to user

Function `appendLog()` is called throughout but never defined in the page or in any included JS. JavaScript console error on every extraction event, log output never displayed.

**Fix:** Define the function in `extract-logs.php`'s script block or add it to `app.js`.

---

### BUG-016 — 29+ Bare `except:` Blocks
**File:** `parsers.py`, `analysis/unified_timeline.py` and others  
**Severity:** HIGH — Silent data loss in forensic parsing

```python
except:          # catches KeyboardInterrupt, SystemExit, everything
    record['date'] = 'Unknown'
```
Forensic tools must never silently corrupt data. "Unknown" dates in evidence could render records inadmissible.

**Fix:** Replace with `except (ValueError, KeyError) as e:` and log the actual error.

---

### BUG-017 — Known Spyware Detection is a Pass Statement
**File:** `analysis/threat_detector.py` line 196  
**Severity:** HIGH — Threat detection doesn't detect known spyware

```python
# 4. Known Spyware Signatures
pass   # NOT IMPLEMENTED
```
This is a core forensic feature (detecting stalkerware, RATs, spyware) that is completely absent.

**Fix:** Implement against `threat_signatures.py` which already contains signature patterns.

---

### BUG-018 — Sideload Detection Misses Main Install Vector
**File:** `analysis/threat_detector.py` lines 129–134  
**Severity:** HIGH — Sideloaded APKs go undetected

Logic only flags `installer != "null"` but misses:
- `com.google.android.packageinstaller` (manual APK install — the primary sideload method)
- Apps with empty installer string
- Apps from third-party stores (APKPure, Aptoide)

**Fix:** Add explicit check for package installer values that indicate sideloading.

---

## 4. Medium Priority Issues

---

### BUG-019 — Disabled Pages Still Accessible
**File:** `web/includes/sidebar.php` lines 112–119, `web/pages/`  
7 pages are commented out of the sidebar but still accessible by direct URL:
- `network-intelligence.php`
- `pii-detector.php`
- `power-forensics.php`
- `intent-hunter.php`
- `beacon-map.php`
- `clipboard-forensics.php`
- `app-sessionizer.php`

These pages load but show empty data or broken functionality with no explanation.

**Fix:** Add a `disabled.php` include at the top of each page that shows a clear "Feature unavailable on modern Android" banner.

---

### BUG-020 — Duplicate Map Implementations (Leaflet + ForensicLocationMap)
**File:** `web/assets/js/app.js` lines 729–777, `web/assets/js/forensic-location-map.js`  
Two competing map systems coexist. Leaflet initialization code in `app.js` is never called (superseded by `ForensicLocationMap` class). Dead code that confuses any future developer.

**Fix:** Remove the Leaflet functions from `app.js`.

---

### BUG-021 — Cell Tower and WiFi Geolocation Are Stubs
**File:** `web/includes/collectors/CellTowerCollector.php` line 115, `WiFiCollector.php` line 131  
Both `lookupCellTower()` and `geolocateWiFi()` return `null`. These are not just disabled — they contain only commented example code. The location map will never show cell/WiFi-derived locations.

**Fix:** Implement using the already-configured OpenCellID and Unwired Labs API keys in `config.php`.

---

### BUG-022 — RootCollector Always Returns Empty
**File:** `web/includes/collectors/RootCollector.php` line 29, 50  
`canRun()` always returns `false`. Root-derived location data (GPS history in SQLite databases accessible only on rooted devices) is never collected even if device is rooted.

**Fix:** Implement canRun() to check for rooted device indicators and collect root-accessible location databases.

---

### BUG-023 — UTF-8 BOM Breaks First Log Line Parsing
**File:** `web/includes/parsers/LogcatTimelineParser.php` line 36  
If logcat file has a UTF-8 BOM (common on Windows ADB), first line starts with `\xEF\xBB\xBF` which breaks the regex on line 64. First log event is silently dropped.

**Fix:** `$line = ltrim($line, "\xEF\xBB\xBF");` at line 65.

---

### BUG-024 — process_all.py Modules Lack `__main__` Entry Points
**File:** `scripts/process_all.py`, multiple `analysis/*.py` files  
The orchestrator calls `run_script("analysis/notification_parser.py")` etc., but many analysis modules have no `if __name__ == "__main__":` block. They define functions but never call them when run as scripts, producing no output.

**Fix:** Add entry points to all modules called by `process_all.py`.

---

### BUG-025 — No Authentication or Session Management
**File:** All `web/api/*.php`  
The tool is designed for forensic workstations (local only), but all API endpoints are completely open. Any process on the same machine, or on the network if firewall is misconfigured, can call extract, clear data, or export reports.

**Fix:** Add a simple token-based auth or restrict to `127.0.0.1` at the PHP level.

---

### BUG-026 — Memory Exhaustion on Large Logcat Files
**File:** `web/index.php` lines 12–23  
`countMatchesInFile()` opens entire files into a while loop — acceptable. But the logcat line count uses a different method that may load the full file. On 100MB+ logcat files (common after 24h of extraction), this causes PHP fatal memory errors.

**Fix:** Use `exec('wc -l < ' . escapeshellarg($file))` on Unix or stream-count in PHP.

---

### BUG-027 — Python Path Assumes `python` in PATH
**File:** `web/includes/config.php` line 18  
```php
define('PYTHON_PATH', 'python');
```
On macOS/Linux modern systems, the binary is `python3`. The app will fail all Python-dependent features silently.

**Fix:** Auto-detect:
```php
$py = shell_exec('which python3 2>/dev/null') ?: shell_exec('which python 2>/dev/null') ?: 'python3';
define('PYTHON_PATH', trim($py));
```

---

## 5. Low Priority / Code Quality

---

### BUG-028 — Wildcard Import in threat_scanner.py
`from threat_signatures import *` pollutes namespace and makes it impossible to know what symbols are available. Replace with explicit imports.

### BUG-029 — Inconsistent subprocess Encoding
Some files use `encoding="utf-8"`, others use `text=True`. Standardize to `text=True, encoding="utf-8", errors="replace"` everywhere.

### BUG-030 — No App Version in Audit Log
`web/includes/audit.php` does not include `APP_VERSION` in log entries. Cannot correlate evidence collected under different tool versions.

### BUG-031 — Device Image Relative Path Breaks in Subdirectories
`web/includes/device-image-helper.php` line 48 returns `assets/images/devices/generic-phone.svg` — a relative path that resolves incorrectly when page is in `pages/` subdirectory.

### BUG-032 — CORS Headers Inconsistent Across APIs
Some endpoints set `Access-Control-Allow-Origin: *`, most set nothing. Create a shared `cors_headers()` include.

### BUG-033 — Commented-Out Dead Code Blocks
`web/index.php` lines 96–106 contain a large commented stats block. Remove.

---

## 6. Unimplemented / Stub Features

| Feature | Location | Status | Impact |
|---|---|---|---|
| Known spyware detection | `analysis/threat_detector.py:196` | `pass` — never implemented | No RAT/stalkerware detection |
| Cell tower geolocation | `CellTowerCollector.php:115` | Returns `null` | No cell-based location |
| WiFi geolocation | `WiFiCollector.php:131` | Returns `null` | No WiFi-based location |
| Root database collection | `RootCollector.php:29` | `canRun()` always false | Root location data ignored |
| Fake log detection | `analysis/fake_log_detector.py:100` | Function truncated/incomplete | Log tampering not detected |
| Dual-space analysis | `analysis/dual_space_analyzer.py:80` | Implementation cut off | Dual-app spaces on Samsung/MIUI ignored |
| Network Intelligence page | `web/pages/network-intelligence.php` | UI exists, marked disabled | Network forensics missing |
| PII Detector page | `web/pages/pii-detector.php` | UI exists, marked disabled | PII scanning missing |
| Clipboard forensics | `web/pages/clipboard-forensics.php` | UI exists, marked disabled | Clipboard history not recovered |
| Intent Hunter | `web/pages/intent-hunter.php` | UI exists, marked disabled | App intent analysis missing |
| PDF export on macOS/Linux | `reporting.py` | WeasyPrint optional | Reports are HTML-only |
| Evidence hash for new files | `evidence_hasher.py:154` | Only 14 of 30+ files hashed | Chain of custody incomplete |

---

## 7. Security Vulnerabilities

| ID | File | Issue | Risk |
|---|---|---|---|
| SEC-01 | `web/includes/device-image-helper.php:63` | Path constructed from unvalidated manufacturer/model strings | Path traversal → file disclosure |
| SEC-02 | `web/index.php:444` | HTML injected into DOM via innerHTML without escaping | Reflected XSS |
| SEC-03 | `web/api/live-stream.php:18` | `set_time_limit(0)` with no max connections | DoS via resource exhaustion |
| SEC-04 | `web/api/clear-data.php:55` | File deletion doesn't resolve symlinks before deleting | Symlink attack → delete arbitrary files |
| SEC-05 | All API files | No authentication | Any local process can call destructive APIs |
| SEC-06 | `web/api/cell-lookup.php:263` | Negative cell tower IDs accepted | Invalid requests to paid external APIs |
| SEC-07 | Multiple | `getLogsPath()` creates dirs with 0755 (world-readable) | Log data readable by all local users |

---

## 8. Industry Comparison

### Commercial Tools
| Feature | This Tool | Cellebrite UFED | Magnet AXIOM | Oxygen Forensic Detective | Andriller (free) |
|---|---|---|---|---|---|
| ADB extraction | ✅ Basic | ✅ Advanced | ✅ Advanced | ✅ Advanced | ✅ Basic |
| Physical extraction (chip-off) | ❌ | ✅ | ✅ | ✅ | ❌ |
| Encrypted backup extraction | ❌ | ✅ | ✅ | ✅ | ✅ |
| iOS support | ❌ | ✅ | ✅ | ✅ | ❌ |
| Cloud data extraction | ❌ | ✅ | ✅ | ✅ | ❌ |
| UFED format (.ufd) | ❌ | Native | ✅ Import | ✅ Import | ❌ |
| E01/AFF4 evidence containers | ❌ | ✅ | ✅ | ✅ | ❌ |
| Timeline visualization | ✅ Basic | ✅ Advanced | ✅ Advanced | ✅ Advanced | ❌ |
| Cell tower mapping | ⚠️ Stub | ✅ | ✅ | ✅ | ❌ |
| Deleted data recovery | ❌ | ✅ | ✅ | ✅ | ✅ |
| SQLite database browser | ❌ | ✅ | ✅ | ✅ | ✅ |
| Social media analysis | ❌ | ✅ | ✅ | ✅ | ❌ |
| Messaging app decryption | ❌ | ✅ | ✅ | ✅ | ❌ |
| Chain of custody (hashing) | ✅ Partial | ✅ Full | ✅ Full | ✅ Full | ✅ |
| Court-ready reports | ⚠️ Basic | ✅ | ✅ | ✅ | ✅ |
| Case management | ❌ | ✅ | ✅ | ✅ | ❌ |
| Multi-device comparison | ❌ | ✅ | ✅ | ✅ | ❌ |
| Keyword search across all data | ⚠️ Partial | ✅ | ✅ | ✅ | ❌ |
| Authentication / user accounts | ❌ | ✅ | ✅ | ✅ | N/A |
| Audit trail | ✅ Basic | ✅ Full | ✅ Full | ✅ Full | ❌ |
| DFIR reporting standards | ❌ | ✅ SWGDE | ✅ | ✅ | ❌ |

### Open Source Comparators
| Feature | This Tool | Autopsy | ALEAPP | MVT (Mobile Verification Toolkit) |
|---|---|---|---|---|
| Web-based UI | ✅ | ✅ | ❌ | ❌ |
| Android ADB extraction | ✅ | ✅ (plugin) | ✅ | ✅ |
| iOS support | ❌ | ✅ | ❌ | ✅ |
| Artifact parsers | ⚠️ Limited | ✅ Extensive | ✅ Extensive | ✅ (spyware focus) |
| Spyware/stalkerware detection | ⚠️ Broken | ❌ | ❌ | ✅ (Pegasus/NSO) |
| Timeline analysis | ✅ | ✅ | ✅ | ⚠️ |
| Plugin/module system | ❌ | ✅ | ✅ | ❌ |
| Hash verification | ✅ Partial | ✅ | ❌ | ✅ |
| Active maintenance | ? | ✅ | ✅ | ✅ |

### Key Gaps vs Industry Standard
1. **No deleted data recovery** — this is a baseline requirement for forensic tools. ADB only gets live data.
2. **No SQLite browser** — most app data (WhatsApp, Signal, SMS on older Android) lives in SQLite DBs.
3. **No encrypted backup support** — Android allows ADB backup of many apps; this tool skips it.
4. **No cloud extraction** — Google Drive, Gmail, Google Photos data is out of scope.
5. **No case management** — investigators work multiple cases; no way to separate evidence sets.
6. **Incomplete chain of custody** — only 14 of 30+ extracted files are hashed.
7. **No DFIR report standard compliance** — reports don't follow SWGDE, ACPO, or ISO 27037.

---

## 9. Feature Roadmap — What to Add

### TIER 1 — Must Have (Market Readiness)

#### T1-01 Case Management System
Investigators need to work multiple cases simultaneously. Each case should have:
- Case number, investigator name, date/time, device ID
- Isolated `cases/<case_id>/logs/` directory per case
- All extractions, analysis, and reports scoped to case
- Case export as single ZIP with full chain of custody manifest

#### T1-02 Authentication & Authorization
Even on a local workstation this is required for court admissibility:
- Login with username + password (bcrypt hashed)
- Session management with timeout
- Per-case access control (who can access which case)
- Full audit trail of who accessed what and when

#### T1-03 Complete Chain of Custody
Extend `evidence_hasher.py` to:
- Hash ALL extracted files (auto-discover, not hardcoded list)
- SHA-256 + MD5 dual hashing (courts accept both)
- Generate signed manifest with investigator ID and timestamp
- Re-verify hashes at any point and report any tampering
- Hash the hash manifest itself

#### T1-04 SQLite Database Browser
Most forensic evidence lives in app SQLite databases:
- Mount extracted SQLite files in a web-based browser
- Auto-identify known databases (WhatsApp, SMS, contacts, browser history, Gmail)
- Show table contents with filtering and export
- Flag deleted records (SQLite freelist)

#### T1-05 Court-Ready Report Generator
Current HTML report is functional but not court-standard. Needs:
- PDF with page numbers, case number header, investigator details on every page
- Evidence integrity section (all hashes, verification status)
- Exhibits with source file reference and line numbers
- Chain of custody timeline (when each item was extracted, by whom)
- SWGDE/ACPO compliance section

#### T1-06 Fix All Critical Bugs (BUG-001 through BUG-007)
Nothing else matters until the extraction pipeline works on macOS/Linux.

#### T1-07 Real Cell Tower & WiFi Geolocation
Implement the already-designed but stubbed `CellTowerCollector` and `WiFiCollector`:
- OpenCellID integration (free, API key already in config)
- Unwired Labs fallback
- Plot geofences around cell towers (not just points) to show uncertainty radius
- WiFi SSID history with Google Geolocation API or WiGLE

#### T1-08 ADB Encrypted Backup Extraction
```bash
adb backup -all -apk -shared -nosystem -f backup.ab
```
Parse `.ab` backup files (they're zipped Java serialization) to extract:
- App private data that ADB shell can't reach
- WhatsApp, Telegram databases (if backup not disabled)
- Browser bookmarks and history

---

### TIER 2 — Should Have (Competitive Parity)

#### T2-01 Deleted Data Recovery Indicators
ADB can't do chip-off, but can surface deletion artifacts:
- SQLite WAL files (contain recently deleted records)
- SQLite freelist pages (uncleared deleted rows)
- Tombstone crash reports referencing deleted apps
- `logcat` entries for recently uninstalled packages

#### T2-02 Messaging App Artifact Parser
Dedicated parsers for common apps — where backup/ADB provides access:
- WhatsApp: `msgstore.db` message database
- Telegram: local database
- SMS/MMS: `mmssms.db`
- Browser history: Chrome `History` SQLite, Firefox `places.sqlite`

#### T2-03 Complete Spyware/Stalkerware Detection
Build on the started-but-broken threat_detector.py:
- Match against NSRL and known-bad package hash database
- Detect common stalkerware: Cerberus, FlexiSpy, mSpy, Hoverwatch, iKeyMonitor
- Detect RATs: AhMyth, AndroRAT, DroidJack
- Flag apps with dangerous permission combinations (SMS + RECORD_AUDIO + LOCATION)
- Check against VirusTotal API (optional, rate-limited)

#### T2-04 Network Intelligence (Fix Disabled Page)
Currently disabled due to "inconsistent data" — implement properly:
- Parse `netstat` output from ADB for active connections
- DNS query history from logcat
- App-level network usage from `dumpsys netstats`
- Flag connections to known C2 servers, TOR exit nodes, suspicious TLDs

#### T2-05 Cross-Device Comparison
TGCSB likely deals with multiple devices in related cases:
- Import two case extractions and diff their contact lists
- Find shared accounts, IMEI associations
- Timeline overlay of two devices to find communications

#### T2-06 Keyword & Regex Search Across All Evidence
Global search that works across:
- All log files
- Parsed SMS/call records
- Extracted contacts
- App data from SQLite browsers
- Highlight matches in context

#### T2-07 OSINT Integration for Contact Analysis
Enrich extracted contacts and numbers:
- Reverse phone lookup (NumLookup, AbuseIPDB for IPs)
- IMEI validation and carrier lookup (free APIs)
- Flag numbers in financial crime databases (if TGCSB has access)

---

### TIER 3 — Nice to Have (Differentiation)

#### T3-01 Real-Time Collaboration
Multiple investigators on same case:
- WebSocket-based real-time updates
- Comment/annotation system on evidence items
- Task assignment per evidence item

#### T3-02 AI-Assisted Analysis
- LLM summarization of SMS conversation threads
- Anomaly detection on call patterns (unusual hours, frequent short calls — drug dealing indicators)
- Auto-categorize apps by purpose (banking, social, comms, utility)
- Auto-generate narrative summary for inclusion in court report

#### T3-03 iOS Support via iTunes Backup
Using `libimobiledevice` or iTunes backup parsing:
- Parse iOS backup (unencrypted) for messages, contacts, locations
- iOS-equivalent timeline analysis
- Unified Android+iOS dashboard for related cases

#### T3-04 Geofence & Location Intelligence
Beyond just plotting points:
- Cluster frequent locations (identify home, work, regular meeting points)
- Speed analysis between location points (impossible travel detection)
- Cross-reference locations with known crime scene coordinates
- Export to KML/GPX for court-admissible maps

#### T3-05 Evidence Integrity Blockchain Anchoring
For highest-assurance court evidence:
- Anchor SHA-256 of the evidence manifest to a public blockchain (Bitcoin OP_RETURN or Ethereum)
- Proves evidence existed at a specific time without any party being able to alter it
- Generate court-submissible blockchain proof

#### T3-06 Scheduled / Automated Extraction
For ongoing monitoring cases (with legal authorization):
- Schedule ADB extraction every N minutes
- Delta extraction (only new data since last run)
- Alert on specific keywords appearing in new data

#### T3-07 Docker Deployment
Package entire tool as Docker image:
- Pre-configured PHP + Python environment
- ADB in container (with USB passthrough)
- One-command deployment: `docker run -p 8080:8080 tgcsb-forensic`
- Eliminates all the Windows/macOS/Linux compatibility issues

---

## 10. Priority Fix Order

### Week 1 — Make the App Work
1. BUG-001 Cross-platform extraction (extract.php)
2. BUG-002 Cross-platform ADB in live-stream.php
3. BUG-003 Fix indentation in threat_scanner.py
4. BUG-004 Fix export-report.php Windows cmd
5. BUG-027 Fix Python path detection
6. BUG-015 Define appendLog() function
7. BUG-007 Remove duplicate _collect_context()
8. BUG-010 Add file existence checks in analysis scripts

### Week 2 — Make Features Work
9. BUG-005 Timeline empty state handling
10. BUG-006 Dashboard stats streaming
11. BUG-012 Filter logs table element
12. BUG-008 Fix cross-year timestamp inference
13. BUG-009 Absolute paths in reporting.py
14. BUG-013 ADB returncode checking
15. BUG-014 Progress polling session IDs
16. BUG-016 Replace bare except blocks
17. BUG-017 Implement spyware detection
18. BUG-018 Fix sideload detection
19. BUG-024 Add __main__ to analysis modules

### Week 3 — Security & Chain of Custody
20. SEC-01 through SEC-07 all security fixes
21. BUG-011 Expand evidence hasher to all files
22. BUG-021 Implement cell tower geolocation
23. BUG-022 Implement root collector
24. T1-03 Complete chain of custody system

### Month 2 — Market Readiness
25. T1-01 Case management system
26. T1-02 Authentication & authorization
27. T1-05 Court-ready PDF reports
28. T1-04 SQLite database browser
29. T2-03 Complete spyware/stalkerware detection
30. T2-02 Messaging app artifact parsers

### Month 3 — Competitive Differentiation
31. T2-04 Network intelligence (fix disabled page)
32. T2-06 Cross-evidence keyword search
33. T2-01 Deleted data recovery indicators
34. T1-07 Encrypted backup extraction
35. T1-08 Docker packaging

---

*Document prepared for TGCSB internal review. All findings are based on static code analysis of v2.1.0.*
