# PMS → L&D Integration Contract (L&D System View)

> **Last updated:** August 22, 2026
> **Status:** Integration documented — see Known Issues section for code fixes needed
> **Prepared by:** smart-pms coding agent

---

## Overview

The **CapstoneFinalSystem** (L&D) is one pillar of the HRIS. It receives low-performing employees from **smart-pms** via API, manages their training workflow, and notifies PMS when training is complete so PMS can unlock the employee's account.

This document covers:
- What L&D receives from PMS (intake)
- What L&D must send back to PMS (callback)
- L&D-side database schema
- Known code issues that need fixing

For the PMS-side perspective and PMS database schema, see `L&D.md` in the smart-pms project.

---

## PMS Database Structure (Context for L&D Agents)

> This section explains why `pms_user_id` in the payload maps to `users.id` in PMS,
> and why you should never expect PMS to send a separate `employee_record_id`.
> Column lists verified against PMS live database on August 22, 2026.

PMS uses a two-table structure:

```
users     — auth only
  id, name, role, email, password, two_factor_*, remember_token,
  office_id (redundant copy), position (redundant copy),
  created_at, updated_at

employees — employee profile (authoritative for all profile data)
  id, user_id (FK→users, unique),
  first_name, middle_name, last_name,
  employee_id, hms_employee_id,
  office_id, position,       ← authoritative copies
  is_active, is_disabled, activated_at, profile_photo_path,
  training_locked,           ← ★ L&D lock flag
  lnd_reference_id,          ← ★ L&D reference ID
  created_at, updated_at
```

**Note:** `office_id` and `position` exist on BOTH tables due to a re-add migration (`2026_07_28`),
but `employees` is authoritative. `training_locked` and `lnd_reference_id` exist ONLY on `employees`.

The `employee.id` in the PMS payload = `users.id` in PMS = the cross-system identity key.
Store this as `pms_user_id` in `training_referrals` and `lnd_trainees`.

When PMS locks an employee for training, it must write `training_locked = true` to `employees`
(not `users` — `users` has no such column). When L&D fires the callback, PMS uses `pms_user_id`
to look up the `User`, then does `$user->employee->update(['training_locked' => false])`.

---

## How the Handoff Works (Big Picture)

```
PMT clicks "Submit to L&D" in smart-pms
        │
        ▼
PMS POSTs full employee IDP payload → POST /api/lnd/development-plans
        │
        ▼
L&D (PmsIntakeController::store()):
  - Creates training_referral record
  - Upserts lnd_trainee cross-system identity map
  - Provisions or finds L&D user account for the employee
  - Creates TrainingApplication(s) per IDP row
  - Returns { status: "acknowledged", lnd_reference_id: "LND-REF-2026-XXXXX" }
        │
        ▼
PMS receives response:
  - Stores lnd_reference_id on development_plans
  - Writes training_locked = true to employees table
  - Writes lnd_reference_id to employees table
  - Employee is redirected to L&D website on next PMS login
        │
        ▼
Employee logs into L&D, completes training workflow
        │
        ▼
L&D Secretariat marks training as completed
PmsCallbackService::notifyComplete() fires:
  POST {PMS_BASE_URL}/api/lnd-callback/complete-training
        │
        ▼
PMS receives callback:
  - Marks development_plan status = 'completed'
  - Writes training_locked = false to employees table
  - Clears lnd_reference_id on employees table
  - Employee can log into PMS again
```

---

## Part 1: Inbound from PMS — Intake Endpoint

### Route (already built)

```
POST /api/lnd/development-plans
Authorization: Bearer {LND_API_TOKEN}
Content-Type: application/json
```

Middleware: `VerifyLndApiToken` — validates the Bearer token against `config('services.lnd.api_token')` / `LND_API_TOKEN` in `.env`.

### Controller

`App\Http\Controllers\Api\PmsIntakeController::store()`

### What the controller does (current implementation)

1. Validates the payload
2. Guards against duplicate `external_plan_id` (idempotent — returns existing reference if already processed)
3. Generates `lnd_reference_id` in format `LND-REF-{year}-{5-digit-seq}`
4. Inside a DB transaction:
   - Calls `provisionUser()` — finds or creates L&D user account by email, sends activation email if new
   - Upserts `lnd_trainees` with `pms_user_id` → `lnd_user_id` mapping
   - Creates `training_referrals` record with full JSON snapshots
   - Creates `TrainingApplication` record(s) per IDP row
5. Returns HTTP 201 `{ "status": "acknowledged", "lnd_reference_id": "..." }`

### Known Issue in PmsIntakeController

**File:** `app/Http/Controllers/Api/PmsIntakeController.php`

The `provisionUser()` method tries to set `'office' => $emp['office_name']` when creating a new `User`:

```php
User::query()->create([
    ...
    'office' => $emp['office_name'] ?? null,   // ← 'office' is not a column on L&D's users table
    ...
]);
```

The L&D `users` table does not have an `office` column. This silently drops the value.

**Fix:** Remove `'office' => ...` from the create call, or store the office name in the `lnd_trainees` table (which already has `office_name`). The `lnd_trainees` upsert immediately after already stores the office name correctly.

---

### Full Request Payload Reference

```json
{
  "external_plan_id": "PMS-DP-42",
  "source_system": "PMS",

  "period": {
    "id": 3,
    "name": "Jan-Jun 2026"
  },

  "employee": {
    "id": 17,
    "name": "Carlos Mendoza",
    "email": "carlos.mendoza@agency.gov.ph",
    "position": "HR Assistant II",
    "office_id": 5,
    "office_name": "Human Resource Management Office"
  },

  "performance": {
    "official_score": 1.50,
    "official_rating": "Poor",
    "pmt_adjusted_score": null,
    "pmt_adjusted_rating": null,
    "released_at": "2026-06-28T00:00:00.000000Z"
  },

  "ipcr": {
    "id": 88,
    "functions": [
      {
        "id": 1,
        "name": "Core Functions",
        "function_type": "core",
        "weight_percent": 70,
        "mfos": [
          {
            "id": 5,
            "title": "Recruitment, Selection and Placement",
            "indicators": [
              {
                "id": 21,
                "indicator_text": "Monthly reports submitted on time",
                "target_quantity": 6,
                "target_timeline": "Monthly",
                "ratings": {
                  "Q": 1.20,
                  "E": 0.83,
                  "T": 1.50,
                  "A": 1.18,
                  "actual_quantity": 1
                },
                "standards": [
                  { "dimension": "quality",    "rating": 5, "standard_text": "100% accurate, zero errors" },
                  { "dimension": "timeliness", "rating": 5, "standard_text": "Submitted 2+ days before deadline" }
                ]
              }
            ]
          }
        ]
      }
    ],
    "weighted_summary": [
      {
        "function_name": "Core Functions",
        "weight_percent": 70,
        "average_rating": 1.18,
        "weighted_score": 0.83
      }
    ]
  },

  "idp_rows": [
    {
      "performance_gap": "Low output quantity in recruitment processes",
      "developmental_activity": "Attend advanced HR training",
      "support_needed": "Training budget, mentorship",
      "support_from_supervisor": "Weekly coaching sessions",
      "expected_completion": "Q3 2026",
      "results": ""
    }
  ],

  "references": {
    "ipcr_id": 88,
    "opcr_id": null
  }
}
```

### Field Reference

#### `employee` block

| Field | Type | Meaning in PMS |
|---|---|---|
| `id` | integer | `users.id` in PMS — **the cross-system key** — store as `pms_user_id` |
| `name` | string | `users.name` |
| `email` | string | `users.email` — use for account provisioning |
| `position` | string | `employees.position` in PMS |
| `office_id` | integer | `employees.office_id` in PMS |
| `office_name` | string | `offices.name` in PMS |

#### `idp_rows[]`

| Field | Description |
|---|---|
| `performance_gap` | What the employee struggles with |
| `developmental_activity` | Planned training — used as `training_title` in `TrainingApplication` |
| `support_needed` | Resources or support required |
| `support_from_supervisor` | What the supervisor committed to provide |
| `expected_completion` | Target completion timeline |
| `results` | Outcome after training (may be empty at submission) |

---

### Required Response from L&D

**Success — HTTP 201 Created:**
```json
{
  "status": "acknowledged",
  "lnd_reference_id": "LND-REF-2026-00042"
}
```

`lnd_reference_id` format: `LND-REF-{year}-{5-digit-seq}` e.g. `LND-REF-2026-00042`.
PMS stores this in `development_plans.lnd_reference_id` AND `employees.lnd_reference_id`.
Echo it back in the training completion callback.

---

## Part 2: Employee Redirect from PMS

When a training-locked employee tries to log into smart-pms, PMS redirects them to the L&D intake URL:

```
https://{LND_HOST}/intake?pms_user_id=17&plan=LND-REF-2026-00042&sig={hmac}
```

| Parameter | Value Source |
|---|---|
| `pms_user_id` | `users.id` in PMS |
| `plan` | `employees.lnd_reference_id` in PMS (the same value L&D returned) |
| `sig` | HMAC-SHA256 of `pms_user_id + plan` using `LND_REDIRECT_HMAC_SECRET` |

L&D should verify `sig` to confirm the redirect came from PMS.
Use `plan` (`lnd_reference_id`) to look up the `training_referral` record and show the employee their training path.

The `IntakeController` at `app/Http/Controllers/IntakeController.php` handles this route.

---

## Part 3: Outbound to PMS — Training Completion Callback

### Service (already built)

`App\Services\PmsCallbackService::notifyComplete()`

Reads from `config('services.pms.base_url')` and `config('services.pms.callback_token')`.

### Endpoint on PMS (already built)

```
POST {PMS_BASE_URL}/api/lnd-callback/complete-training
Authorization: Bearer {PMS_CALLBACK_TOKEN}
Content-Type: application/json
```

### Callback Payload

```json
{
  "pms_user_id": 17,
  "lnd_reference_id": "LND-REF-2026-00042",
  "external_plan_id": "PMS-DP-42",
  "completed_at": "2026-09-15T10:30:00Z",
  "courses_completed": [
    {
      "course_code": "LND-HR-101",
      "title": "Advanced HR Fundamentals",
      "completed_at": "2026-09-10T14:00:00Z"
    }
  ],
  "trainer_remarks": "Employee demonstrated marked improvement."
}
```

| Field | Required | Source |
|---|---|---|
| `pms_user_id` | yes | `training_referrals.pms_user_id` |
| `lnd_reference_id` | yes | `training_referrals.lnd_reference_id` — echo back what PMS sent |
| `external_plan_id` | yes | `training_referrals.external_plan_id` — echo back `PMS-DP-{id}` |
| `completed_at` | yes | ISO 8601 — when training was marked complete |
| `courses_completed` | yes | Array from `lnd_courses_completed`. Can be `[]` if not tracked per-course. |
| `trainer_remarks` | no | Optional notes from the trainer |

### What PMS Does After Receiving the Callback

| Location | Column | Before | After |
|---|---|---|---|
| `development_plans` | `status` | `submitted_to_ld` | `completed` |
| `development_plans` | `lnd_completed_at` | null | `completed_at` from payload |
| `development_plans` | `lnd_completion_remarks` | null | `trainer_remarks` |
| `development_plans` | `lnd_courses_completed` | null | `courses_completed` array |
| `employees` | `training_locked` | `true` | `false` |
| `employees` | `lnd_reference_id` | set | `null` (optional cleanup) |

> Note: PMS currently has a bug where it writes to `users` instead of `employees` for the unlock.
> That is documented in the PMS `L&D.md` and will be fixed there. L&D's callback payload itself is correct.

---

## Part 4: L&D Database Schema

### `training_referrals`
Primary intake table. One record per IDP submission from PMS.

```sql
id                   BIGINT PK AUTO_INCREMENT
lnd_reference_id     VARCHAR(64) UNIQUE NOT NULL   -- "LND-REF-2026-00042"
external_plan_id     VARCHAR(64) NOT NULL           -- "PMS-DP-42"
source_system        VARCHAR(32) DEFAULT 'PMS'

pms_user_id          BIGINT NOT NULL               -- users.id from PMS (cross-system key)
pms_period_id        BIGINT NOT NULL               -- period.id from PMS
period_name          VARCHAR(128) nullable

employee_name        VARCHAR(255) nullable
employee_email       VARCHAR(255) nullable
employee_position    VARCHAR(255) nullable
employee_office_id   INT nullable
employee_office      VARCHAR(255) nullable

official_score       DECIMAL(5,2) nullable
official_rating      VARCHAR(64) nullable

ipcr_snapshot        JSON NOT NULL                 -- full ipcr block from PMS payload
idp_rows             JSON NOT NULL                 -- full idp_rows array from PMS payload

status               VARCHAR(64) DEFAULT 'received'  -- received | in_progress | completed
received_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP
completed_at         TIMESTAMP nullable

pms_notified_at      TIMESTAMP nullable            -- when callback was sent to PMS
pms_notify_error     TEXT nullable                 -- error if callback failed

created_at, updated_at
```

### `lnd_trainees`
Cross-system identity map. One record per employee.

```sql
id              BIGINT PK AUTO_INCREMENT
pms_user_id     BIGINT UNIQUE NOT NULL     -- stable link to PMS users.id
name            VARCHAR(255)
email           VARCHAR(255)
position        VARCHAR(255)
office_name     VARCHAR(255)
lnd_user_id     BIGINT nullable            -- FK → L&D users.id
created_at, updated_at
```

### `lnd_courses_completed`
Per-course completion tracking (used to build the callback payload).

```sql
id                      BIGINT PK AUTO_INCREMENT
training_referral_id    BIGINT NOT NULL FK → training_referrals.id
course_code             VARCHAR(64)
title                   VARCHAR(255)
completed_at            TIMESTAMP
created_at, updated_at
```

### `training_applications`
Created automatically per IDP row by `PmsIntakeController`. One application per developmental activity.

Key columns relevant to integration:
```sql
training_referral_id   BIGINT nullable FK → training_referrals.id
user_id                BIGINT FK → users.id (L&D user)
employee_id            VARCHAR nullable
training_title         VARCHAR
training_type          VARCHAR
status                 VARCHAR default 'applied'
```

When Secretariat marks a `TrainingApplication` linked to a `training_referral_id` as `completed`,
`PmsCallbackService::notifyComplete()` should be called with the parent `TrainingReferral`.

---

## Part 5: L&D Files — What Needs Fixing

| File | Method | Issue | Fix |
|---|---|---|---|
| `app/Http/Controllers/Api/PmsIntakeController.php` | `provisionUser()` | `'office' => $emp['office_name']` passed to `User::create()` but `office` is not a `users` column | Remove that line; office is already stored in `lnd_trainees.office_name` |

**Files that are already correct:**
- `app/Services/PmsCallbackService.php` — outbound callback is correct
- `app/Http/Middleware/VerifyLndApiToken.php` — correct
- `app/Models/TrainingReferral.php` — correct
- `app/Models/LndTrainee.php` — correct
- `routes/api.php` — correct
- `config/services.php` — correct

---

## Part 6: Environment Variables

### L&D `.env`
```env
LND_API_TOKEN={token}                    # L&D generates this; PMS stores it as LND_API_TOKEN
LND_REDIRECT_HMAC_SECRET={shared-secret} # Must match smart-pms value exactly
PMS_BASE_URL=http://smart-pms.test       # PMS URL — use ngrok if cross-machine
PMS_CALLBACK_TOKEN={token}               # PMS generates this; L&D stores it here
PMS_TIMEOUT=20
```

### config/services.php (L&D)
```php
'lnd' => [
    'api_token'            => env('LND_API_TOKEN'),
    'redirect_hmac_secret' => env('LND_REDIRECT_HMAC_SECRET'),
],
'pms' => [
    'base_url'       => env('PMS_BASE_URL'),
    'callback_token' => env('PMS_CALLBACK_TOKEN'),
    'timeout'        => env('PMS_TIMEOUT', 20),
],
```

### Local Development Note

`PMS_BASE_URL=http://smart-pms.test` only works if L&D and PMS run on the same machine (both on Herd).
For cross-machine testing, use ngrok:
- PMS exposes via ngrok → set that URL as `PMS_BASE_URL` in L&D's `.env`
- L&D exposes via ngrok → set that URL as `LND_BASE_URL` in PMS's `.env`

---

## Part 7: Implementation Checklist

### L&D already built:
- [x] `POST /api/lnd/development-plans` endpoint with Bearer token auth
- [x] `training_referrals` migration + model
- [x] `lnd_trainees` migration + model
- [x] `lnd_courses_completed` migration + model
- [x] `lnd_reference_id` generator (`LND-REF-{year}-{seq}`)
- [x] Auto-create `TrainingApplication` per IDP row on intake
- [x] `PmsCallbackService` — outbound callback to PMS
- [x] `VerifyLndApiToken` middleware
- [x] Duplicate intake guard (idempotent on `external_plan_id`)

### L&D still needs to fix:
- [ ] `PmsIntakeController::provisionUser()` — remove `'office'` from `User::create()`
- [ ] Ensure `PmsCallbackService::notifyComplete()` is called when Secretariat marks training complete (verify this trigger exists in the Secretariat portal controller)

### PMS still needs to fix (see PMS `L&D.md`):
- [ ] `DevelopmentPlanningController::submitToLd()` — lock employee via `employees` table
- [ ] `LndCallbackController::completeTraining()` — unlock employee via `employees` table

---

*Document updated: August 22, 2026*
*Both systems: Laravel + Inertia stack*
*L&D stack: Laravel 11, Inertia, React (TSX), Spatie Permissions*
