# BMI Challenge — Build Handoff

Internal office "BMI Challenge" web app. This doc is the spec for building it in Claude Code. The companion file `bmi-challenge-mockup.html` is the **visual + interaction reference** — open it alongside this. Where this doc and the mock disagree, this doc wins (the mock has placeholder data and a couple of shortcuts noted below).

---

## 1. What this is (and isn't)

A staff health challenge running **Aug–Nov 2026 (~16 weeks)**, one rotation only. Staff are split into **Team A** and **Team B**; teams compete on weight/BMI progress. Weekly weigh-ins are recorded, events are scheduled with RSVP, and there's an analytics view.

**Scope is deliberately throwaway.** One rotation, this cycle only. Do **not** build multi-season/multi-cycle abstraction, roster archival, or reusability for the next rotation's team. Hardcode the current cycle. If it's ever reused, that's a rewrite, and that's an accepted trade.

**Stack:** Laravel + Filament + Livewire. Filament for the admin/master-data CRUD (near-free); Livewire for the staff-facing pages. No SPA framework.

---

## 2. Core data model

Two kinds of data — get this separation right, everything else follows.

**Master data (set once by admin, rarely changes):**
- **Staff** — belongs to exactly one **department** (permanent org identity), assigned to **Team A or B** (challenge overlay), has a **height** (cm, set once).
- **Department** — a collection bucket for the PIC and an org identity. **Not** a competitive unit.
- **Team (A/B)** — the competitive unit. Assignment is **per-individual**, not per-department. A department can be split across both teams.

**Transactional data (weekly):**
- **Weigh-in record** — one row per staff per week: `staff_id`, `week` (use `week_start_date`, the Monday — not a bare week number), `weight_kg`, `recorded_by`, `created_at`. **Never overwritten across weeks** — the whole point is the weekly series. BMI is derived (`weight / (height_m)²`), not stored as truth; store it as a convenience column if you want cheap reads, but compute server-side, never trust a client value.

**Key relationships:**
- Height lives on the staff master record, so weekly entry only needs weight.
- "Who didn't submit this week" = staff in the master roster with **no** weigh-in row for that week. This only works because the roster is the source of truth and weekly records are checked against it.
- "Change over time" = a staff member's weigh-in rows strung into a series.

---

## 3. Baseline & change definitions

Locked decisions — don't reinterpret these:

- **Baseline = each staff member's own first recorded weigh-in** (personal baseline, not a fixed challenge week 1). Someone who first weighs in week 3 has their week-3 weight as their zero. This handles latecomers and mid-challenge joiners with no null-handling.
- **Δ from baseline** = current vs their first record. This is the progress number.
- **Δ week-over-week** = a week's value vs their *previous actual recorded* week (skip gaps — compare to last real record, not the prior calendar week). This is the momentum number.
- **Consequence to preserve:** because baselines are personal, people are measured over **different numbers of weeks**, so raw total Δ is NOT directly comparable between a 10-week person and a 3-week person. Any leaderboard/summary MUST show weeks-of-data per person so the number doesn't silently lie. (The mock's summary table has a "Minggu direkod" column for exactly this.)

---

## 4. Roles (RBAC)

Three conceptual roles, but **PIC is folded into Admin** for this build (accepted trade-off, see §8):

- **Admin** — master roster (departments, staff, heights, team assignment), events, full analytics, AND batch weight entry (the PIC function). The organizing group this rotation.
- **User (staff)** — SSO login. Sees **only their own** records + analytics, and RSVPs to events. Read-only on their own weight (they don't self-log — the PIC/admin records it; see §8).

Every staff authenticates via **SSO** as themselves. Because weight is entered *for* them by the PIC, the user-facing view should show provenance ("recorded by <PIC> on <date>") so a user isn't confused seeing data they never entered.

Build real permission checks from the start — retrofitting RBAC is where this goes wrong.

---

## 5. Screens

### 5.1 Landing (`Markas`) — built in mock
Hero (challenge name, tagline), **neutral Team A vs Team B** standings (no us-vs-them framing), a weigh-in **collection-progress** callout (e.g. "28 of 43 recorded, 3 departments outstanding"), and an upcoming-events strip with RSVP.

Team standing metric: **average % change from baseline per team** — NOT raw kg totals. Raw kg can't be a team metric (different people, different starting points; taller/heavier teams win by default). This is non-negotiable for fairness; see §7.

### 5.2 Weekly weigh-in — PIC batch entry (`Sesi Timbang`) — built in mock
Two-step:
1. **Department picker** — grid of departments, each showing collection status (done / partial / not started).
2. **Roster** — tap a department, get its staff list; enter each weight down the line. BMI computes per-row from that person's locked height. Progress counter ticks up. Save the batch.

Editable until the week closes (Monday midnight in the mock copy) via `updateOrCreate` keyed on staff + week; then locked.

### 5.3 Admin — master roster (`Admin`) — built in mock
**Grouped by department** (expandable). Each staff row: editable height + an **A/B team toggle**. A **persistent team-balance readout** at the top shows both teams' size and average starting BMI, with a bar that shifts colour as teams get lopsided.

The balance tool tracks headcount + avg BMI, but scores balance on headcount only. It gives **defensible, not perfect** fairness — do not present it to staff as scientific. See §7.

Master roster is editable mid-challenge (people join/leave/transfer), but **past weekly records must be immutable** — someone who leaves keeps their logged weeks and just stops generating "missing" flags after exit. Don't let a roster edit rewrite history.

### 5.4 Admin analytics (`Analitik`) — built in mock
Two tables:
- **Weekly grid (`Rekod Mingguan`)** — rows = staff, columns = each week (M1…Mn). A three-way toggle changes what cells show: **Berat (kg) / BMI / Perubahan** (Perubahan = week-over-week Δ, colour-coded). Missing weeks show "—"; first recorded week in Perubahan mode shows "·" (no prior to compare). Name column sticky on horizontal scroll. Read across a row for one person's journey.
- **Deltas summary (`Ringkasan Perubahan`)** — one row per person: weeks-recorded, start weight, current weight, Δ weight, start BMI, current BMI, Δ BMI. This is the leaderboard. **Sorting isn't wired in the mock — add it** (esp. sort by Δ weight). Colour: gold/green = down (loss), red = up (gain).
- Team A-vs-B summary block at the top, echoing the landing page. Filter by team (Semua / A / B).

A **separate change-only table was considered and rejected** — the Perubahan toggle covers it; don't build a redundant table.

### 5.5 Event RSVP — partial in mock
Events (title, date, location, description, RSVP deadline) + responses (staff, event, yes/no/maybe). Value over Google Forms is the instant headcount rollup + exportable attendee list + deadline-close. Optional: day-of attendance check-in if attendance should feed scoring.

### 5.6 User individual view — NOT BUILT
The staff-facing SSO view: a user sees **only their own** weight line over the weeks, BMI trend, and Δ from baseline. Show their own progress prominently; team context (rank/contribution) gently or not at all — see §7 on shaming. This is the most-used screen by regular staff and doesn't exist yet. **First thing to design after the admin side is solid.**

### 5.7 Weekly compliance grid — NOT SEPARATELY BUILT
"Who logged / who's missing" per week. **Likely not a separate screen** — the weekly grid's "—" cells already carry this signal. Recommended: a toggle on the existing grid that recolours to emphasize presence/absence (green cell vs red gap) rather than a whole new screen. Decide during build.

---

## 6. Design / tone
Cinematic "gangster council" aesthetic (see mock + the team's poster refs): near-black background, distressed gold + bone-white display type, blood-red accent, heavy condensed headline fonts (Oswald / Barlow Condensed). Copy is in **Malay slang**, matching the team's poster voice ("Timbang hari Isnin", "New week, new weight"). The tone is part of the product — keep it. Adjust register if the office wants more formal/bilingual.

Weigh-in framed as a ritual ("report for weigh-in", BMI shown as a "verdict"), not a sterile form.

---

## 7. Fairness — the recurring tension (READ THIS)
"Assigned fairly across departments" and "feels like a team" are slightly at odds. Teams are deliberately mixed across departments, so **nobody starts with a natural in-group** — team spirit must be manufactured (team identity, a channel, banter, the leaderboard). Invest in that layer; it doesn't come from the roster.

On the scoring metric: the user's stated preference is **raw weight + BMI** for individual recording, which is fine. But:
- **Team scoring must be % change from baseline**, not raw kg (fairness across body types). The mock does this.
- **Leaderboard ranking** on raw kg is unfair across different week-counts and body sizes. % change is the honest ranking. The user has been resistant to % as the headline — this is UNRESOLVED. Current compromise: show raw weight + BMI + their deltas, surface weeks-recorded so the reader can judge, and don't crown a "winner" on raw kg alone. **Flag this to the user before finalizing any ranking.**
- Weight-loss *potential* can't be measured at assignment time, so no team split is truly fair. The balance tool gets you defensible, not perfect. Say so.
- **Shaming risk:** showing a user "you're worst on Team A" is demotivating and can get personal in a workplace. Keep individual views progress-focused; go light on comparison.

---

## 8. Privacy / DPO notes (user is a DPO — take seriously)
- **Admin analytics shows every staff member's exact weight + BMI, by name, to the whole organizing group.** Body weight is sensitive. For a voluntary opt-in fun challenge this is probably fine, but: (a) make participation **opt-in with that visibility stated up front**, and (b) keep the admin group small.
- **PIC-folded-into-admin means anyone who does data entry can also restructure teams, edit heights, and delete records** — a separation-of-duties gap. Accepted for *this* low-stakes throwaway. Do NOT carry this pattern into QCXIS proper.
- Cheap insurance worth adding: a `recorded_by` stamp on every weigh-in (already in the model above), so entry is attributable.

---

## 9. Open decisions to settle before/during build
1. **Ranking metric** — raw kg vs % change for the leaderboard. Unresolved (§7). Blocks the summary table's default sort + any "winner" declaration.
2. **User individual view** — not designed yet (§5.6).
3. **Compliance as toggle vs separate screen** (§5.7) — lean toggle.
4. **Team assignment method** — manual per-person in the mock. If ~40+ staff, hand-balancing is tedious; consider an auto-balance helper (snake draft on starting BMI). Optional.
5. **Height capture timing** — heights must exist before week 1 or BMI breaks. Needs a registration pass or admin import up front. Not yet a screen.
6. **Event attendance → scoring?** — decide if day-of attendance feeds any score, or if events are purely social.

---

## 10. Build order (suggested)
1. Master data + RBAC + SSO (foundation — everything depends on the roster).
2. Height capture (blocks BMI).
3. PIC weekly batch entry + the weekly record model.
4. Admin analytics (weekly grid + summary).
5. User individual view.
6. Events + RSVP.
7. Compliance toggle, leaderboard sorting, polish.

Do master data and the weekly record model first — the input and analytics screens are mostly views over data that doesn't exist until those are right.
