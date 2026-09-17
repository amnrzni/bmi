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
| Team A / Team B (two fixed teams) | **N admin-managed teams** (~8), ranked — see §11 |
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

## 11. Teams: from two fixed to N managed (supersedes the TAH/BKN framing)

Changed after launch, on the office's call. The original design used **two mixed teams (TAH vs BKN),
deliberately un-ranked**, so nobody had a natural in-group and there was no us-vs-them — see §7 and
HANDOFF §7. That reasoning no longer applies:

- **Teams are now an admin-managed set** (~8), created and named on the roster screen like
  departments. No code deploy to change them.
- **Standings are a ranked leaderboard** on the home page — best average % change from baseline on
  top. This is explicitly a competition between named teams now, not two anonymous halves.
- Team assignment is a **dropdown** per person; the analytics filter is a dropdown too.
- Balance is judged as a **per-team headcount + avg-BMI list**, flagging lopsided teams, rather than
  the old two-team difference bar.
- The short team tag (`code`, shown in the weekly grid) is **auto-derived from the name** — the admin
  only types names.

The scoring itself is unchanged: still average % change from baseline per team, still excludes members
below `min_records_team`. Only the count of teams and the presentation changed.

**Consequence to keep in mind:** a visible leaderboard raises the shaming risk §7 warned about, now at
team level. Kept in check by ranking *teams*, never individuals, and by the individual view still
showing no personal rank.

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

## 12. Merdeka corner contest (a separate feature sharing the app)

Judged decoration contest for the 2026 Merdeka corners. It shares the deployment and the
`departments` table, and nothing else.

- **Judges do not use SSO and are not app users.** The panel is a list of names and emails in
  `merdeka_judges`; typing a listed address at `/merdeka/masuk` is the whole sign-in. Two founders
  judging office decorations once did not justify roster rows, consent and QCXIS identities.
- **The consequence, stated plainly:** the email is the entire credential, so anyone who knows a
  judge's address can score as them. Accepted for this contest. The sign-in is throttled (10 wrong
  addresses per IP per 15 min) so the door can't be walked one address at a time.
- **Scores key on `merdeka_judges`, and a judge who has filed anything can't be removed** — the same
  rail the roster puts in front of deleting someone with weigh-ins. Removing them would take signed
  sheets with them and silently move a corner's average.
- **Locked on submit.** No edit path at all — the judge signs the sheet, so changing it afterwards
  would leave the signature attesting to something else. An admin has no override either.
- **Malay UI, overriding §2's English rule for these screens only.** The rubric's band descriptions
  are the substance of the contest, and translating them is where a rubric loses its precision.
- **Its own palette** (`--color-merdeka-*`), flag red on near-black, scoped to `/merdeka`.
- **Total is computed server-side** from the 1–5 bands on every write, same rule as BMI (§4).
- **Entrants are `departments` rows**, so the contest needs the real org departments —
  `php artisan merdeka:departments` adds them and reports the placeholders.
- **Two doors, deliberately.** `/merdeka` is guest-accessible and held by `merdeka.judge`;
  `/merdeka/keputusan` is an ordinary admin screen behind app auth. A judge session grants nothing on
  the admin side and an admin session grants nothing on the judging side.
- **Unlisted in the nav.** Both are reached by direct link; a one-week event doesn't earn a permanent
  tab for 38 people.
- **Admins see partial results live**; judges see only their own sheets, so neither founder can
  anchor on the other's marks.

## 13. Tournaments (sports days, starting with Kudeta Bola Baling)

Built from the organisers' single-file prototype for Kudeta Bola Baling, Sat 19 Sep 2026. More
tournaments are expected, so it is a list of tournaments rather than one hardcoded event.

- **Watching needs no sign-in.** `/tournaments` and `/tournaments/{slug}` are public, so anyone at
  the venue can follow from a shared link. **Squad names and "not attending" are therefore public** —
  accepted by the organisers. Signed-in staff also get a nav tab.
- **Players are roster members.** Squads are picked from `users`, one squad per person per
  tournament. The division (men/women) lives on the squad row, never on the user — §3's "no gender"
  still holds for the challenge itself.
- **Existing app admins run it.** Scores, finals, captains and attendance are entered on the public
  page, where every action re-checks `manage-tournaments`. Teams, squads, fixtures and the programme
  are on a separate admin-only setup screen, because the public page polls every 15s for viewers and
  a refresh landing mid-edit would lose the edit. Admin views don't poll.
- **Format:** a round-robin group whose pairings are shared by both divisions, each division scored
  separately, then a final per division between that division's top two. **Win 3, draw 1, loss 0;**
  level on points → score difference → scored → team list order. No head-to-head.
- **A level final is decided on penalties**, entered as a second score. A level shoot-out is refused.
- **Finalists lock when the final is first saved.** Correcting a group score afterwards doesn't move
  them; the page warns the admin, and clearing the final re-seeds it from the table.
- **The combined table is information only** — both divisions' group lines summed, no champion.
- **Everything stays editable**, scored matches included; people mistype at a courtside table.
  Changing a scored fixture's teams keeps the score on that match number. Every score and final
  write is stamped with who made it and goes to the activity log.
- **The slug is set once.** Renaming a tournament doesn't break a link already shared.
- **Not carried over from the prototype:** the match timer (the organisers use the phone's clock
  app) and the Excel template import. Export is CSV, as for events.
- **`php artisan tournaments:kudeta`** creates Kudeta's teams, fixtures and programme, and places
  squad names onto the roster only where a name matches exactly one roster member on whole words
  and that member matches no other name. Everything else is listed for an admin to add by hand.
  Re-runnable once the roster is filled in. Use `--dry-run` first.

## Still open

1. **The real roster** — names, departments, heights, team assignments. Placeholder data seeded
   meanwhile.
2. **The Google Sheet's columns** — needed before a CSV importer can be written.
3. **QCXIS SSO protocol and endpoints** — the driver is stubbed until these arrive.
4. **Challenge end date** — open-ended in config; the grid grows as weeks are recorded.
5. **Merdeka: the founders' emails** — typed into the panel form on the results screen. Any address
   works; it is a contest credential, not a QCXIS identity.
6. **Merdeka: the placeholder departments** — Academic, Operations, Finance, IT Support and Customer
   Service still hold staff and will appear on the judging screen until they are cleared.
