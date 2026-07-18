# PMS → L&D Integration Contract

> **Last updated:** July 18, 2026
> **Status:** Ready for implementation on both sides
> **Prepared by:** smart-pms coding agent

---

## Overview

The **smart-pms** system identifies low-performing employees from released IPCR results. After their Individual Development Plan (IDP) is approved and reviewed by PMT, PMT submits the employee to the L&D system for training.

This document is the **complete API contract** between the two systems. The L&D developer should implement based on this document.

---

## How the Handoff Works (Big Picture)

```
PMT clicks "Submit to L&D" in smart-pms
        │
        ▼
smart-pms POSTs full employee payload → L&D API
        │
        ▼
L&D stores the record, returns lnd_reference_id
        │
        ▼
smart-pms locks employee PMS account
Employee is redirected to L&D website when they try to log in
        │
        ▼
Employee completes training in L&D system
        │
        ▼
L&D POSTs callback → smart-pms
        │
        ▼
smart-pms unlocks employee account
Employee can log into PMS again
```

---

## Part 1: smart-pms → L&D (Intake Endpoint)

### Endpoint L&D must build

```
POST /api/lnd/development-plans
Authorization: Bearer {LND_API_TOKEN}
Content-Type: application/json
Accept: application/json
```

- `LND_API_TOKEN` — a static Bearer token that L&D defines and shares with the PMS team
- PMS stores this token in its `.env` as `LND_API_TOKEN`
- PMS stores the L&D base URL in its `.env` as `LND_BASE_URL`

---

### Full Request Payload

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
                  { "dimension": "quality",     "rating": 5, "standard_text": "100% accurate, zero errors" },
                  { "dimension": "quality",     "rating": 4, "standard_text": "Accurate with 1-2 minor errors" },
                  { "dimension": "quality",     "rating": 3, "standard_text": "Accurate with 3-4 minor errors" },
                  { "dimension": "quality",     "rating": 2, "standard_text": "With major errors" },
                  { "dimension": "quality",     "rating": 1, "standard_text": "Inaccurate, requires full revision" },
                  { "dimension": "timeliness",  "rating": 5, "standard_text": "Submitted 2+ days before deadline" },
                  { "dimension": "timeliness",  "rating": 3, "standard_text": "Submitted on deadline" },
                  { "dimension": "timeliness",  "rating": 1, "standard_text": "Submitted 2+ days after deadline" }
                ]
              }
            ]
          }
        ]
      },
      {
        "id": 2,
        "name": "Support Functions",
        "function_type": "support",
        "weight_percent": 20,
        "mfos": []
      },
      {
        "id": 3,
        "name": "Strategic Functions",
        "function_type": "strategic",
        "weight_percent": 10,
        "mfos": []
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

---

### Field Reference

#### Top level

| Field | Type | Description |
|---|---|---|
| `external_plan_id` | string | PMS stable identifier. Format: `PMS-DP-{id}`. Store this and echo it back in the callback. |
| `source_system` | string | Always `"PMS"` |

#### `period`

| Field | Type | Description |
|---|---|---|
| `id` | integer | PMS performance period ID |
| `name` | string | Human-readable label e.g. `"Jan-Jun 2026"` |

#### `employee`

| Field | Type | Description |
|---|---|---|
| `id` | integer | PMS `users.id` — use this as `pms_user_id` in L&D. This is the cross-system link key. |
| `name` | string | Full name |
| `email` | string | Work email — use for account provisioning |
| `position` | string | Job position title |
| `office_id` | integer | PMS office ID |
| `office_name` | string | Office name |

#### `performance`

| Field | Type | Description |
|---|---|---|
| `official_score` | decimal | Final IPCR score (1.00–5.00). This is what triggered the IDP. |
| `official_rating` | string | `Outstanding`, `Very Satisfactory`, `Satisfactory`, `Unsatisfactory`, or `Poor` |
| `pmt_adjusted_score` | decimal\|null | PMT-calibrated score override (if PMT adjusted it) |
| `pmt_adjusted_rating` | string\|null | PMT-calibrated rating override |
| `released_at` | ISO 8601\|null | When IPCR was officially released by PMT |

#### `ipcr`

| Field | Type | Description |
|---|---|---|
| `id` | integer | PMS IPCR record ID |
| `functions` | array | Full IPCR breakdown — see below |
| `weighted_summary` | array | Per-function weighted score summary |

#### `ipcr.functions[].mfos[].indicators[]`

| Field | Type | Description |
|---|---|---|
| `indicator_text` | string | The success indicator description |
| `target_quantity` | number | Target number of outputs for the period |
| `target_timeline` | string | e.g. `"Monthly"`, `"Quarterly"` |
| `ratings.Q` | decimal\|null | Quality — average quality rating from supervisor |
| `ratings.E` | decimal\|null | Efficiency — actual qty ÷ target qty × 5 |
| `ratings.T` | decimal\|null | Timeliness — average timeliness rating from supervisor |
| `ratings.A` | decimal\|null | Average of Q + E + T |
| `ratings.actual_quantity` | decimal | Total quantity of outputs actually accomplished |
| `standards` | array | QET rating scale descriptions (what each score means) |

#### `ipcr.weighted_summary[]`

| Field | Type | Description |
|---|---|---|
| `function_name` | string | Name of the function type |
| `weight_percent` | decimal | Weight of this function in the overall score |
| `average_rating` | decimal | Average A rating across all indicators in this function |
| `weighted_score` | decimal | `average_rating × (weight_percent / 100)` |

#### `idp_rows[]`

This is the **core training input** — what the employee identified as their development needs.

| Field | Type | Description |
|---|---|---|
| `performance_gap` | string | What the employee struggles with |
| `developmental_activity` | string | Planned training or intervention |
| `support_needed` | string | Resources or support required |
| `support_from_supervisor` | string | What the supervisor committed to provide |
| `expected_completion` | string | Target completion timeline |
| `results` | string | Outcome (filled after training — may be empty at submission) |

---

### Required Response from L&D

**Success — HTTP 201 Created:**
```json
{
  "status": "acknowledged",
  "lnd_reference_id": "LND-REF-2026-00042"
}
```

| Field | Type | Description |
|---|---|---|
| `status` | string | Must be `"acknowledged"` to mark sync as fully received. Any other value is stored as `"sent"`. |
| `lnd_reference_id` | string\|null | L&D's internal reference ID for this training record. PMS stores it. **Echo this back in the training completion callback.** |

**Error — HTTP 422 / 400 / 500:**
```json
{
  "message": "Validation failed: employee.id is required"
}
```
PMS will store the error message in `lnd_last_error` and mark the plan as `lnd_sync_status = failed`. PMT can retry.

---

### What PMS does after a successful response

| Column | Before | After |
|---|---|---|
| `development_plans.status` | `submitted_to_pmt` | `submitted_to_ld` |
| `development_plans.lnd_sync_status` | `not_sent` | `acknowledged` or `sent` |
| `development_plans.lnd_reference_id` | `null` | value from L&D response |
| `development_plans.submitted_to_ld_at` | `null` | current timestamp |
| `users.training_locked` | `false` | `true` ← employee cannot log into PMS |

---

## Part 2: Employee Redirect to L&D

When a training-locked employee tries to log into smart-pms, they are redirected to the L&D website instead of the dashboard.

### Redirect URL format

```
https://{LND_HOST}/intake?pms_user_id=17&plan=LND-REF-2026-00042&sig={hmac}
```

| Parameter | Description |
|---|---|
| `pms_user_id` | The employee's PMS user ID |
| `plan` | The `lnd_reference_id` returned by L&D — so L&D knows which training plan to show |
| `sig` | HMAC-SHA256 signature over `pms_user_id + plan` using the shared `LND_REDIRECT_HMAC_SECRET` |

L&D must verify the `sig` to confirm the redirect came from PMS and was not tampered with.

**If URL verification is not ready yet**, at minimum L&D should accept `pms_user_id` to pre-identify the employee on their login/onboarding page.

---

## Part 3: L&D → PMS (Training Completion Callback)

When the employee completes all training in L&D, L&D must notify PMS so the employee's account is unlocked.

### Endpoint PMS will build

```
POST /api/lnd-callback/complete-training
Authorization: Bearer {PMS_CALLBACK_TOKEN}
Content-Type: application/json
Accept: application/json
```

- `PMS_CALLBACK_TOKEN` — a static Bearer token that PMS defines and shares with the L&D team
- L&D stores this in its `.env` as `PMS_CALLBACK_TOKEN`
- L&D stores the PMS base URL in its `.env` as `PMS_BASE_URL`

---

### Callback Request Payload (L&D sends this)

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

| Field | Required | Description |
|---|---|---|
| `pms_user_id` | yes | Must match `users.id` in PMS — primary lookup key |
| `lnd_reference_id` | yes | Echo back what PMS sent — used for record matching |
| `external_plan_id` | yes | Echo back `PMS-DP-{id}` — secondary verification |
| `completed_at` | yes | ISO 8601 timestamp of training completion |
| `courses_completed` | yes | Array of completed courses. Can be empty array `[]` if not tracked per-course. |
| `trainer_remarks` | no | Optional narrative stored in PMS for PMT reference |

---

### PMS Response to Callback

**Success — HTTP 200 OK:**
```json
{
  "ok": true,
  "message": "Training completion recorded."
}
```

**What PMS does after receiving the callback:**

| Column | Before | After |
|---|---|---|
| `development_plans.status` | `submitted_to_ld` | `completed` |
| `users.training_locked` | `true` | `false` ← employee can log into PMS again |

---

## Part 4: Database Tables L&D needs to build

### `training_referrals`
Primary intake table. One record per employee per IDP submission from PMS.

```sql
id                  BIGINT PRIMARY KEY AUTO_INCREMENT
lnd_reference_id    VARCHAR(64) UNIQUE NOT NULL   -- generated by L&D, e.g. "LND-REF-2026-00042"
external_plan_id    VARCHAR(64) NOT NULL           -- "PMS-DP-42" from PMS
source_system       VARCHAR(32) DEFAULT 'PMS'
pms_user_id         BIGINT NOT NULL                -- employee.id from PMS payload
pms_period_id       INT NOT NULL                   -- period.id
period_name         VARCHAR(128)
employee_name       VARCHAR(255)
employee_email      VARCHAR(255)
employee_position   VARCHAR(255)
employee_office_id  INT
employee_office     VARCHAR(255)
official_score      DECIMAL(5,2)
official_rating     VARCHAR(64)
ipcr_snapshot       JSON                           -- full ipcr block from payload
idp_rows            JSON                           -- full idp_rows array from payload
status              VARCHAR(64) DEFAULT 'received' -- received, in_progress, completed
received_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP
completed_at        TIMESTAMP NULL
pms_notified_at     TIMESTAMP NULL                 -- when callback was sent to PMS
pms_notify_error    TEXT NULL                      -- if callback failed, store error here
created_at          TIMESTAMP
updated_at          TIMESTAMP
```

### `lnd_trainees`
Cross-system identity map. One record per employee.

```sql
id              BIGINT PRIMARY KEY AUTO_INCREMENT
pms_user_id     BIGINT UNIQUE NOT NULL     -- stable link to PMS users.id
name            VARCHAR(255)
email           VARCHAR(255)
position        VARCHAR(255)
office_name     VARCHAR(255)
lnd_user_id     BIGINT NULL                -- FK to L&D's own users/accounts table
created_at      TIMESTAMP
updated_at      TIMESTAMP
```

### `lnd_courses_completed`
Per-course completion tracking for the callback payload.

```sql
id                      BIGINT PRIMARY KEY AUTO_INCREMENT
training_referral_id    BIGINT NOT NULL    -- FK -> training_referrals.id
course_code             VARCHAR(64)
title                   VARCHAR(255)
completed_at            TIMESTAMP
created_at              TIMESTAMP
updated_at              TIMESTAMP
```

---

## Part 5: Environment Variables Summary

### smart-pms `.env` (PMS team fills these in)

```env
LND_BASE_URL=https://{lnd-host}          # L&D provides
LND_API_TOKEN={token}                    # L&D decides and shares with PMS
LND_TIMEOUT=20
LND_REDIRECT_HMAC_SECRET={shared-secret} # Both teams agree on this value
```

### L&D `.env` (L&D team fills these in)

```env
PMS_BASE_URL=https://{pms-host}          # PMS provides
PMS_CALLBACK_TOKEN={token}               # PMS decides and shares with L&D
PMS_INBOUND_TOKEN={token}                # L&D decides — used to authenticate PMS inbound calls
LND_REDIRECT_HMAC_SECRET={shared-secret} # Must match smart-pms value exactly
```

---

## Part 6: What Each Side Needs to Build

### L&D must build:
- [ ] `POST /api/lnd/development-plans` — intake endpoint with Bearer token auth
- [ ] `training_referrals` migration + model
- [ ] `lnd_trainees` migration + model (upsert on each intake)
- [ ] `lnd_courses_completed` migration + model
- [ ] `lnd_reference_id` generator (format: `LND-REF-{year}-{sequential}`)
- [ ] Employee training path/dashboard inside L&D system
- [ ] Outbound callback service — POST to PMS when training is marked complete
- [ ] (Optional) URL signature verification for the redirect parameter `sig`

### smart-pms must build:
- [ ] `POST /api/lnd-callback/complete-training` — inbound webhook from L&D
- [ ] `training_locked` column on `users` table migration
- [ ] Login middleware — redirect locked employees to L&D URL
- [ ] Add `employee.email` to `LndHandoffService::buildPayload()`

---

## Part 7: Shared Secrets to Agree On

Before either side goes live, both teams must exchange these values:

| Value | Who generates it | Who needs it |
|---|---|---|
| `LND_API_TOKEN` | L&D team | PMS puts in `.env` |
| `PMS_CALLBACK_TOKEN` | PMS team | L&D puts in `.env` |
| `LND_REDIRECT_HMAC_SECRET` | Agree together | Both `.env` files |
| `LND_BASE_URL` | L&D provides their URL | PMS puts in `.env` |
| `PMS_BASE_URL` | PMS provides their URL | L&D puts in `.env` |

For local development with ngrok:
- L&D exposes their local server via ngrok → share the ngrok URL as `LND_BASE_URL` with PMS
- PMS exposes their local server via ngrok → share the ngrok URL as `PMS_BASE_URL` with L&D

---

*Document prepared: July 18, 2026*
*Both systems: Laravel + Inertia stack*
