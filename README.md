# Follow-up CRM API (Laravel 12 + Swagger)

REST API for the **Lead Generation & Follow-up** Android (Kotlin) app.
Laravel 12 · MySQL (`followup-kotlin` on XAMPP) · Sanctum token auth · L5-Swagger (OpenAPI 3) · OpenSpout (Excel/CSV).

| | URL |
|---|---|
| Swagger UI | http://localhost/followup-api-kotlin/public/api/documentation |
| API base | http://localhost/followup-api-kotlin/public/api |
| From the Android emulator | http://10.0.2.2/followup-api-kotlin/public/api |
| From a phone on the same Wi-Fi | http://&lt;PC-LAN-IP&gt;/followup-api-kotlin/public/api |

Demo login (seeded): **admin@example.com / password**

## Setup

```bash
composer install
cp .env.example .env && php artisan key:generate   # already done here
# .env → DB_DATABASE=followup-kotlin, DB_USERNAME=root, DB_PASSWORD=
php artisan migrate --seed          # or migrate:fresh --seed to reset demo data
```

Start Apache + MySQL in XAMPP and open the Swagger UI. Click **Authorize** and paste the
`token` returned by `POST /api/auth/login`.

`L5_SWAGGER_GENERATE_ALWAYS=true` rebuilds the docs on every page load. For production, set it to
`false` and run `php artisan l5-swagger:generate` after each change.

## Endpoints

All endpoints except `options`, `auth/register` and `auth/login` need `Authorization: Bearer <token>`.
Each user sees only their own leads; another user's record returns 404.

| Method | Path | Purpose |
|---|---|---|
| GET | `/api/options` | Dropdown values: sources, statuses, priorities, call statuses |
| POST | `/api/auth/register` · `/api/auth/login` | Get a token |
| GET / POST | `/api/auth/me` · `/api/auth/logout` | Current user / revoke token |
| GET | `/api/dashboard` | Total leads, today's / completed / pending / overdue follow-ups, today's list, recent leads |
| GET | `/api/leads?search=&status=&priority=&source=&sort=` | List and search by name, company or phone (paginated) |
| POST | `/api/leads` | Add lead (including `next_followup_date` + `next_followup_time`) |
| GET / PUT / DELETE | `/api/leads/{id}` | Details with timeline / edit / delete |
| GET | `/api/leads/{id}/followups` | Follow-up history (timeline) |
| POST | `/api/leads/{id}/followups` | Add a note: date, time, note, `call_status` (+ optional next follow-up) |
| GET | `/api/followups?type=today\|upcoming\|overdue\|pending\|completed` | Follow-up module lists. `is_overdue: true` means show it in red |
| POST | `/api/followups/{id}/complete` | Mark completed. Auto-schedules the next follow-up |
| PUT / DELETE | `/api/followups/{id}` | Reschedule / cancel or delete a note |
| GET | `/api/followups/reminders?days=7` | Upcoming pending items, for scheduling local notifications on the phone |
| GET | `/api/followups/daily-summary` | Text and counts for the daily pending-follow-ups reminder |
| GET | `/api/leads/export?format=xlsx\|csv` | Download leads as Excel or CSV |
| POST | `/api/leads/import` (multipart `file`) | Import .xlsx / .csv. Matches on ID or mobile to update; blank cells never overwrite existing data |

### Follow-up rules
- A lead has at most **one pending follow-up**, mirrored in `leads.next_followup_at`.
  Completed follow-ups are the timeline notes.
- Completing a follow-up auto-schedules the next one unless you send `schedule_next=false` or an
  explicit `next_followup_date`/`time`:

  | Status after call | Next follow-up |
  |---|---|
  | Not Answered / Callback | +1 day |
  | Interested | +1 day (High) · +3 (Medium) · +5 (Low) |
  | Closed | none |

  The time of day is kept and Sundays are skipped.
- Lead status follows obvious outcomes: Interested → `Interested`; Callback / Not Answered on a
  `New` lead → `Follow-up`.

### Notifications
Local notifications are the Android app's job. The API supplies what it needs:
`/api/followups/reminders` (each due time) and `/api/followups/daily-summary` (the daily message).

## Code map
```
app/Enums/                      LeadSource, LeadStatus, LeadPriority, CallStatus, FollowupStatus
app/Models/                     Lead (soft deletes), Followup, User (HasApiTokens)
app/Services/FollowupService    scheduling rules (sync next, add note, complete, auto-suggest)
app/Services/LeadTransferService  Excel / CSV export + import (OpenSpout)
app/Http/Requests/              validation (next follow-up date/time parsing in Concerns/)
app/Http/Resources/             JSON shapes + OpenAPI schemas
app/Http/Controllers/Api/       endpoints + OpenAPI attributes
app/OpenApi/                    global Swagger info, security scheme, shared components
tests/                          php artisan test (in-memory SQLite, never touches MySQL)
```
