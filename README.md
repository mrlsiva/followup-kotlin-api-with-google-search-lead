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

## Business Lead Finder (`/api/business-finder/*`)

Free, manual prospecting across Tamil Nadu. **No Google Places API, no scraping, no paid services.**
Laravel only builds Google Maps / Google Search links; the salesperson researches by hand and saves the business.

| Method | Path | Purpose |
|---|---|---|
| GET | `options` · `categories` · `locations?district=` | Dropdowns: categories, website/contact statuses, 38 districts + towns |
| GET | `search-links?category=&city=&district=&area=&pincode=&business_name=&match_type=&keywords=&quantity=` | Maps + Search links (`Hospitals in Chennai`), saved to history; returns `search_id` |
| GET / DELETE | `history` · `history/{id}` | Search history (each entry includes its links again) |
| GET | `suggestions` · `dashboard` | What to search or verify next · statistics + summary text |
| GET | `check-duplicate?phone=&business_name=&city=` | Check before saving |
| POST | `verify-website` (`url`) | Ad-hoc website check (not saved), 10/min |
| GET / POST | `leads` | List with filters (`website_status`, `contact`, `scope`, `saved`, `lead_status` …) / **Add Business to CRM** |
| GET / PUT / DELETE | `leads/{id}` | Details with the CRM lead + timeline / edit (synced to the CRM lead) / remove |
| POST | `leads/{id}/convert` | Save a researched-only business to the CRM (+ first call) |
| POST | `leads/{id}/verify-contact` | Manual contact verification (Pending, Verified, Incorrect number, Disconnected, Do not contact) |
| POST | `leads/{id}/verify-website` | Check the website and store last checked date, HTTP status and result |
| GET | `export/csv` · `export/excel` · `export/pdf` | Export with `scope=all\|verified_contacts\|no_website\|followups`, `ids[]=` or list filters, 10/min |

**CRM integration.** Saving creates a normal lead in `leads`: business name → `company`, contact person (or business
name) → `name`, phone → `mobile`, email → `email`, source `Business Finder`, status `New`, priority `Medium`;
category, location and website are added to `notes`. The first call (`next_followup_date` / `time`) goes through
`FollowupService`, so the existing rules apply (one pending follow-up, auto-scheduling, Sunday skipping).
Later calls use the existing `/api/leads/{id}` and `/api/followups/*` endpoints. `/api/leads/{id}` now includes `business_lead`.

**Duplicates.** Phone numbers are compared by digits (`+91 98765 43210` = `098765 43210`). A phone already used by
one of your leads returns **409** with that lead; resend with `on_duplicate=link` to attach to it instead.

**Do not contact.** Setting contact status to *Do not contact* sets `leads.do_not_contact`, cancels the pending
follow-up, blocks new scheduling (422) and leaves the record out of exports (unless `include_do_not_contact=1`).

**Website checker.** Optional and polite: only http/https on ports 80/443; refuses localhost, private, link-local and
metadata IPs (each redirect hop is checked again and the connection is pinned to the checked IP); reads robots.txt
first; 5 s connect / 10 s total timeout; up to 5 redirects; on SSL errors it retries without verification and reports
`ssl_valid=false`; results are cached for 10 minutes. It only ever *suggests* "Website available". A failed check is
reported as "could not reach right now", never as "does not exist", and the status is left to the salesperson.

Every change, verification and export is written to `audit_logs`. Settings: [config/business_finder.php](config/business_finder.php).
PDF export uses `barryvdh/laravel-dompdf` (max 1000 rows).

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
