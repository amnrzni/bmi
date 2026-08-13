# BMI Challenge — Decisions Log

Companion to `HANDOFF.md`. Where this file and the handoff disagree, **this file wins** — it records
decisions made after the handoff was written. Last updated: 2026-08-12.

---

## Superseding the handoff

| Handoff said | Now |
|---|---|
| Filament for admin/master-data CRUD | **No Filament.** Everything custom Livewire. |
| Copy in Malay slang (§6) | **English** — see open question Q2 |
| Malay UI labels (`Markas`, `Sesi Timbang`, `Analitik`) | English equivalents |
| Team A / Team B | **Team TAH** and **Team BKN** |
| Ranking metric unresolved (§7, §9.1) | **Resolved: % change from baseline** |
| Compliance grid — decide during build (§5.7) | **Resolved: toggle on the weekly grid** |
| Event attendance → scoring, undecided (§9.6) | **Resolved: attendance feeds score** — mechanism TBD, see Q3 |
| Auto-balance helper, optional (§9.4) | **Skipped** |
| Height capture needs a screen (§9.5) | Admin types it in the roster screen; CSV import later |

---

## 1. Timing

- Challenge is **already running**. Week 1 was missed and must be backfilled by admin.
- Existing data lives in **Google Sheets** only — not yet available.
- Target: **Friday 14 Aug 2026** for a first usable build.
- Week keyed on `week_start_date` = the **Monday**.
- Normal edit window: **Monday → Wednesday 23:59**, then locked.
- Admin can enter/edit **any past week**, including closed ones. Backfill is restricted to weeks that
  have already elapsed.
- Timezone: `Asia/Kuala_Lumpur`.

## 2. Stack & environment

- Brand-new Laravel 12 app, PHP 8.3+, Livewire 3. **No Filament, no SPA framework.**
- MySQL, deployed on **aaPanel**.
- Auth: **QCXIS SSO** (docs to come) with **email match**; no match = reject.
  Build behind an interface so the real driver drops in later.
- **Local email/password login for admins only** as a fallback.
- ~**1–2 admins** total.

## 3. Data model rules

- Height: **integer cm**.
- Height corrections **recompute past BMIs silently** — no per-row height snapshot.
- Weight guard: **reject outside 30–250 kg**; **warn but allow** on >5 kg change from last record.
- Scale photo: **optional**, **admin-view only**, retained with the rest of the data.
- No scale/location tracking. No gender, no date of birth.
- Leavers: **soft delete**. Past weigh-ins are immutable and remain; they stop generating "missing" flags.
- Joiners mid-challenge: just add them — their first weigh-in becomes their baseline.
- `recorded_by` stamped on every weigh-in. On backfill this is the admin doing the typing.

## 4. BMI

Malaysian / Asian cutoffs (**not** WHO):

| Category | BMI |
|---|---|
| Underweight | < 18.5 |
| Normal | 18.5 – 22.9 |
| Overweight | 23.0 – 27.4 |
| Obese | ≥ 27.5 |

BMI is **derived server-side** (`weight_kg / (height_cm/100)²`). Never trust a client value.

## 5. Scoring & fairness

- **Ranking metric: % change from personal baseline.** Raw kg, BMI, and weeks-recorded shown alongside
  so the number can't silently lie.
- **Minimum 4 records** to appear in the ranking.
- Leaderboard is **ambient** — no prize, no crowned winner.
- Team standing = **average % change from baseline** across members. Individuals who gain weight
  legitimately drag their team's number down; that's intended.
- No auto-balance helper. Teams assigned manually.

## 6. Privacy (user is a DPO)

- **Opt-in consent screen at first login**, with stored timestamp. States who can see their weight,
  that participation is voluntary, and the right to withdraw.
- Withdrawal mid-challenge: **anonymise**, keep the team aggregate. Do not purge.
- **No auto-purge** after November. Data is kept.
- Staff see **their own data + their team's overall figures only**. Never a named colleague's number.
- **Audit log** on every create/edit/delete of a weigh-in, beyond the `recorded_by` stamp.
- Admin analytics showing every name + exact weight to the admin group is accepted, mitigated by the
  admin group being 1–2 people and by up-front consent.

## 7. Screens

| Screen | Notes |
|---|---|
| Consent | First login, one-time, timestamped |
| Landing | Neutral TAH vs BKN standings, collection-progress callout, events strip |
| Weigh-in batch entry | Department picker → roster. **Phone-first.** Admin week selector for backfill |
| Admin roster | Department accordions, height field, team toggle, live balance readout |
| Admin analytics | Weekly grid (Weight / BMI / Change / **Compliance** toggle) + deltas summary, sortable |
| Individual view | Δ-from-baseline hero, weight line chart, weekly records with provenance, own RSVPs, **no personal ranking** |
| Events | One `/events` page for everyone. Admin-created, RSVP **yes/no** only, no capacity, no waitlist. **Staff check themselves in** on the day. CSV attendee export, no .ics |

The mock's unused single-person weigh-in card (giant input + BMI "verdict") is **repurposed as the
staff's read-only view of their own latest weigh-in**.

## 8. Not building (for now)

- Reminders / notifications of any kind — **wanted later**, not for the first build.
- Verified check-in (QR, geofence, admin confirmation) — self check-in is trusted.
- .ics calendar files.
- Event capacity limits or waitlists.
- Multi-season / multi-cycle abstraction, roster archival, reusability (per handoff §1).

## 9. Quality

- **Pest feature tests on the calculation layer** — baseline, Δ from baseline, week-over-week,
  team averages, compliance. The math is the product.
- **Seeder with realistic fake data** so the app can be demoed before real data lands.

---

## 10. Resolved since

- **Week 1 = Monday 10 Aug 2026.** Its normal window closed Wed 12 Aug; admin backfill is how it
  gets entered.
- **Language:** English UI chrome, Malay slang kept for headlines and flavour.
- **Attendance and weight stay separate figures** — no blended score. A composite number nobody can
  interpret would be worse than two honest ones.
- **Check-in is self-service**, opening 60 minutes before an event and closing at the end of that day
  (`checkin_opens_before_minutes`). It is unverified — someone can mark themselves present without
  turning up. Accepted for a voluntary challenge; admins can see the full attendee list.
- **Turnout denominator is simple**: past events × current team members. Slightly unkind to late
  joiners, and chosen because a number people can explain beats a fairer one they can't.
- **Team-average threshold:** `min_records_team` = 2, separate from the ≥4 leaderboard rule.
  Two records is the minimum needed to have any delta at all.
- **Team names:** TAH and BKN, equal visual weight everywhere.
- **Admins are participants** — they weigh in and compete like everyone else.

## Still open

1. **The real roster** — names, departments, heights, team assignments. Placeholder data seeded
   meanwhile.
2. **The Google Sheet's columns** — needed before a CSV importer can be written.
3. **QCXIS SSO protocol and endpoints** — the driver is stubbed until these arrive.
4. **Challenge end date** — open-ended in config; the grid grows as weeks are recorded.
