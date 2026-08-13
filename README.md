# BMI Challenge

Internal office health challenge, Aug–Nov 2026. Staff are split across **Team TAH** and **Team BKN**,
weigh-ins are recorded weekly by an admin, and progress is measured from each person's own first
recorded weight.

Laravel 13 · Livewire 4 · Tailwind v4 · Pest. No Filament, no SPA framework.

## The documents

| File | What it is |
|---|---|
| [HANDOFF.md](HANDOFF.md) | Original spec — screen intent, and the fairness/privacy reasoning |
| [DECISIONS.md](DECISIONS.md) | Every decision since, and what it supersedes in the handoff |
| [SCHEMA.md](SCHEMA.md) | Tables, derived-logic definitions, authorization model |
| [bmi-challenge-mockup.html](bmi-challenge-mockup.html) | Approved visual reference |

**Read `DECISIONS.md` first** — it overrides the handoff in about ten places.

## Running it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate:fresh --seed
npm run build          # or: npm run dev
php artisan serve
```

Sign in at `/login` as **dev@qcxis.com / password**, or use the development SSO picker to sign in as
any seeded staff member.

### Seeing a full analytics grid

Week 1 started 10 Aug 2026, so with real config there's exactly one week of data and the grid has one
column. To preview what it looks like mid-challenge, move the start date back and reseed:

```bash
CHALLENGE_START_MONDAY=2026-06-08 php artisan migrate:fresh --seed
CHALLENGE_START_MONDAY=2026-06-08 php artisan serve
```

The seeder generates history for whatever weeks have elapsed, so this needs no fake data path.

## Tests

```bash
php artisan test
```

The calculation layer is tested first and hardest — baselines, deltas, week-over-week with gaps,
latecomers, leavers, missing heights, team averages, compliance. The math is the product.

## Configuration

`config/challenge.php` holds everything cycle-specific:

| Key | Default | Notes |
|---|---|---|
| `start_monday` | `2026-08-10` | Week 1. Everything derives from this |
| `end_date` | `null` | Open-ended; the grid grows as weeks are recorded |
| `edit_window_days` | `2` | Mon 00:00 → Wed 23:59. Admins ignore this |
| `min_records_rank` | `4` | To appear on the individual leaderboard |
| `min_records_team` | `2` | To count toward a team average |
| `weight_min` / `weight_max` | `30` / `250` | Rejected outside this |
| `weight_jump_warn` | `5.0` | kg change that warns but never blocks |
| `checkin_opens_before_minutes` | `60` | Check-in window opens this early; closes end of event day |
| `bmi_cutoffs` | `18.5 / 23.0 / 27.5` | Malaysian, **not** WHO |

## Two things to know before changing anything

**No date arithmetic in SQL.** Local dev is SQLite, production is MySQL, and their date functions
differ. All week logic lives in `App\Support\ChallengeWeek` and every weigh-in lookup uses
`whereDate`. Laravel's `date` cast writes `2026-08-10 00:00:00` while lookup values are `2026-08-10`
— MySQL's DATE column truncates and hides the mismatch, SQLite does not.

**Write weigh-ins only through `App\Services\WeighInRecorder`.** It derives BMI server-side, stamps
`recorded_by`, and enforces the plausibility guard.

**Write weigh-ins only through `App\Services\WeighInRecorder`**, and **RSVP/check-in only through
`App\Services\ParticipationService`**. Both derive their values server-side and enforce the window
rules, so the same logic applies wherever it's called from.

## Not built yet

CSV import of the Google Sheet · the real QCXIS SSO driver · reminders. See `DECISIONS.md` §8.
