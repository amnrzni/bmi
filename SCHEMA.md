# BMI Challenge — Schema & Build Plan

Review this before I write migrations. Decisions it implements are in `DECISIONS.md`.

---

## Tables

### `users`
Auth identity, org identity, and challenge overlay in one table. No separate `staff` table — the
handoff's "throwaway, don't abstract" rule applies.

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `name` | string | |
| `email` | string unique | SSO match key |
| `password` | string **nullable** | null for SSO-only users; set for admin fallback login |
| `role` | enum('admin','staff') | default `staff` |
| `is_participant` | boolean | default true — an admin may not weigh in |
| `department_id` | fk nullable | |
| `team_id` | fk nullable | null = unassigned |
| `height_cm` | unsignedSmallInteger nullable | integer cm; null blocks BMI |
| `joined_at` | date nullable | |
| `left_at` | date nullable | stops "missing" flags from this week on |
| `consented_at` | timestamp nullable | null = must pass the consent gate |
| `anonymised_at` | timestamp nullable | withdrawal: name/email scrubbed, rows kept |
| `deleted_at` | softDeletes | |

### `departments`
| Column | Type | Notes |
|---|---|---|
| `id` `name` | | |
| `pic_user_id` | fk nullable | collection bucket owner |
| `sort_order` | smallint | |

Not a competitive unit. Purely org identity + a collection bucket for weigh-in progress.

### `teams`
Two seeded rows: `TAH`, `BKN`. A table rather than an enum only so a rename doesn't need a migration.

| Column | Type |
|---|---|
| `id` `code` `name` `accent_color` `sort_order` | |

### `weigh_ins`
The whole point. One row per person per week, **never overwritten across weeks**.

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `user_id` | fk | |
| `week_start_date` | date | the Monday |
| `weight_kg` | decimal(5,2) | |
| `bmi` | decimal(4,2) nullable | convenience column, computed server-side on save |
| `photo_path` | string nullable | optional scale photo, admin-view only |
| `recorded_by_user_id` | fk | provenance shown to the staff member |
| `notes` | string nullable | |
| timestamps | | |

- `unique(user_id, week_start_date)` — the key `updateOrCreate` writes against.
- `index(week_start_date)` for the weekly grid and compliance query.
- Height is **not** snapshotted here — a height correction recomputes history, per decision.

### `events`
`id`, `title`, `description` (text), `starts_at` (datetime), `location`, `rsvp_deadline` (datetime nullable),
`created_by_user_id`, timestamps, softDeletes.

### `event_responses`
`id`, `event_id`, `user_id`, `response` enum('yes','no'), `responded_at`. `unique(event_id, user_id)`.

### `event_attendances`
Separate from RSVP — an RSVP is a promise, attendance is a fact, and attendance feeds the score.

`id`, `event_id`, `user_id`, `checked_in_at`, `checked_in_by_user_id`. `unique(event_id, user_id)`.

### `activity_log`
Via `spatie/laravel-activitylog`. Logs create/update/delete on `weigh_ins`, `users`, `events` with
actor, old values, new values.

### Challenge config
`config/challenge.php`, env-overridable — not a table. One rotation, hardcoded per handoff §1.

```
start_monday      2026-08-??   // Q1 — blocks backfill
end_date          2026-11-??
weeks             16
edit_window_days  2            // Monday 00:00 → Wednesday 23:59
min_records_rank  4
weight_min / max  30 / 250
weight_jump_warn  5.0
bmi_cutoffs       18.5 / 23.0 / 27.5
```

---

## Derived logic — `ProgressService`

Nothing below is stored as truth; all of it is computed from `weigh_ins`.

| Concept | Definition |
|---|---|
| **Baseline** | The person's own **first** weigh-in by `week_start_date`. Not a fixed challenge week 1. |
| **Δ from baseline** | latest − baseline. The progress number. |
| **% change** | (latest − baseline) / baseline × 100. **The ranking metric.** |
| **Δ week-over-week** | vs their **previous actual record**, skipping gaps — not the prior calendar week. |
| **Weeks recorded** | count of rows. Must be displayed next to any delta so the number can't lie. |
| **Ranked** | weeks recorded ≥ 4. |
| **Team standing** | mean of member % changes. Threshold rule → Q4. |
| **Compliance (week W)** | active roster for W (`joined_at` ≤ W, `left_at` null or > W, not soft-deleted) minus users with a row for W. |
| **BMI** | `weight_kg / (height_cm/100)²`, Malaysian cutoffs. |
| **Week open?** | `today ≤ week_start_date + 2 days` — or always, for an admin. |

Pest tests cover every row of that table, including the awkward cases: one-record person, gap in the
middle, joiner at week 6, leaver at week 9, missing height.

---

## Authorization

Two policies, real from the start (handoff §4 — retrofitting RBAC is where this goes wrong).

- **Admin** — everything: roster, heights, team assignment, batch entry for any week, full analytics,
  events, check-in, exports.
- **Staff** — read-only on their **own** weigh-ins; own analytics; their **team's aggregate only**;
  RSVP for themselves. Never another named person's number.

Global gate: no `consented_at` → redirected to the consent screen, everything else blocked.

---

## Build order for Friday

1. Schema, migrations, models, factories, seeder
2. `ProgressService` + Pest tests ← *the math, before any UI*
3. Auth: admin local login + SSO adapter interface (stubbed until docs arrive) + consent gate
4. Admin roster: departments, staff, heights, team toggle, live balance readout
5. Batch weigh-in: department picker → roster, phone-first, week selector for backfill
6. Analytics: weekly grid (Weight / BMI / Change / Compliance) + sortable deltas summary
7. Landing page

**After Friday:** individual staff view, events + RSVP + check-in, CSV import/export, notifications.

The individual view is the most-used screen by regular staff (handoff §5.6) — it's after Friday only
because no staff can log in until the SSO docs land.
