<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BusinessLead;
use App\Models\User;
use App\Services\BusinessFinder\HostResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BusinessFinderApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00')); // a Tuesday
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
        Http::preventStrayRequests();

        // DNS: *.test resolve to a public documentation-free IP, "internal.example" to a private one
        $this->app->instance(HostResolver::class, new class extends HostResolver
        {
            public function resolve(string $host): array
            {
                return match (true) {
                    $host === 'internal.example.com' => ['10.0.0.5'],
                    $host === 'nowhere.example.com' => [],
                    filter_var($host, FILTER_VALIDATE_IP) !== false => [$host],
                    default => ['93.184.216.34'],
                };
            }
        });
    }

    private function addBusiness(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/business-finder/leads', array_merge([
            'business_name' => 'Sri Lakshmi Hospital',
            'category' => 'Hospitals',
            'contact_person' => 'Dr. Senthil',
            'phone' => '+91 98400 12345',
            'city' => 'Chennai',
            'district' => 'Chennai',
            'website_status' => 'No website found',
            'notes' => 'Found on Maps',
        ], $overrides));
    }

    // ---------------- Auth & catalogue ----------------

    public function test_endpoints_require_authentication(): void
    {
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', '');
        $this->getJson('/api/business-finder/search-links?category=Hospitals&city=Chennai')->assertUnauthorized();
        $this->getJson('/api/business-finder/leads')->assertUnauthorized();
        $this->postJson('/api/business-finder/verify-website', ['url' => 'example.com'])->assertUnauthorized();
        $this->get('/api/business-finder/export/csv')->assertUnauthorized();
    }

    public function test_categories_locations_and_options(): void
    {
        $this->getJson('/api/business-finder/categories')->assertOk()
            ->assertJsonFragment(['value' => 'Hospitals', 'assignable' => true])
            ->assertJsonFragment(['value' => 'All businesses', 'assignable' => false]);

        $all = $this->getJson('/api/business-finder/locations')->assertOk()->json('data');
        $this->assertCount(38, $all);
        $this->getJson('/api/business-finder/locations?district=madurai')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.district', 'Madurai');

        $this->getJson('/api/business-finder/options')->assertOk()
            ->assertJsonPath('quantities', [20, 50, 100])
            ->assertJsonPath('website_statuses.0', 'No website found');
        $this->getJson('/api/options')->assertOk()->assertJsonFragment(['Business Finder']);
    }

    // ---------------- Search links ----------------

    public function test_search_links_are_generated_locally_and_saved_to_history(): void
    {
        $res = $this->getJson('/api/business-finder/search-links?category=Hospitals&city=Chennai&quantity=50')->assertOk();

        $res->assertJsonPath('data.query', 'Hospitals in Chennai')
            ->assertJsonPath('data.google_maps_url', 'https://www.google.com/maps/search/?api=1&query=Hospitals%20in%20Chennai')
            ->assertJsonPath('data.google_search_url', 'https://www.google.com/search?q=Hospitals+in+Chennai+contact+number')
            ->assertJsonCount(5, 'data.result_pages');
        $this->assertNotNull($res->json('search_id'));

        $this->getJson('/api/business-finder/history')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.query', 'Hospitals in Chennai')
            ->assertJsonPath('data.0.links.google_maps_url', 'https://www.google.com/maps/search/?api=1&query=Hospitals%20in%20Chennai');
        Http::assertNothingSent();
    }

    public function test_search_link_variants_and_validation(): void
    {
        $this->getJson('/api/business-finder/search-links?business_name=Apollo%20Hospital&match_type=exact&city=Madurai&save_history=0')
            ->assertOk()->assertJsonPath('data.query', '"Apollo Hospital" in Madurai')->assertJsonPath('search_id', null);
        $this->getJson('/api/business-finder/search-links?category=Schools&district=Theni')
            ->assertJsonPath('data.query', 'Schools in Theni district, Tamil Nadu');
        $this->getJson('/api/business-finder/search-links?category=All%20businesses&area=Anna%20Nagar&city=Madurai&pincode=625020')
            ->assertJsonPath('data.query', 'Businesses in Anna Nagar, Madurai 625020');

        $this->getJson('/api/business-finder/search-links')->assertUnprocessable()->assertJsonValidationErrors(['category']);
        $this->getJson('/api/business-finder/search-links?category=Other%20businesses')->assertJsonValidationErrors(['keywords']);
        $this->getJson('/api/business-finder/search-links?category=Hospitals&quantity=30&pincode=110001&district=Delhi')
            ->assertJsonValidationErrors(['quantity', 'pincode', 'district']);
    }

    // ---------------- Save to CRM ----------------

    public function test_add_business_creates_crm_lead_with_first_call(): void
    {
        $res = $this->addBusiness(['next_followup_date' => '2026-11-11', 'next_followup_time' => '11:30'])->assertCreated();

        $res->assertJsonPath('data.saved_to_crm', true)
            ->assertJsonPath('data.website_status', 'No website found')
            ->assertJsonPath('data.contact_status', 'Pending')
            ->assertJsonPath('lead.name', 'Dr. Senthil')
            ->assertJsonPath('lead.company', 'Sri Lakshmi Hospital')
            ->assertJsonPath('lead.mobile', '+91 98400 12345')
            ->assertJsonPath('lead.source', 'Business Finder')
            ->assertJsonPath('lead.status', 'New')
            ->assertJsonPath('lead.priority', 'Medium')
            ->assertJsonPath('lead.next_followup_date', '2026-11-11')
            ->assertJsonPath('lead.next_followup_time', '11:30');
        $this->assertStringContainsString('[Business Finder] Category: Hospitals', $res->json('lead.notes'));

        // Appears in the existing CRM list, follow-up list and dashboard
        $this->getJson('/api/leads?source=Business%20Finder')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/followups?type=upcoming')->assertJsonCount(1, 'data');
        $this->getJson('/api/dashboard')->assertOk()
            ->assertJsonPath('stats.total_leads', 1)
            ->assertJsonPath('business_finder.total_researched', 1)
            ->assertJsonPath('business_finder.saved_to_crm', 1)
            ->assertJsonPath('business_finder.no_website', 1)
            ->assertJsonPath('business_finder.new_leads', 1);

        // Lead details expose the Business Finder record
        $this->getJson('/api/leads/'.$res->json('lead.id'))->assertJsonPath('data.business_lead.business_name', 'Sri Lakshmi Hospital');
        $this->assertDatabaseHas('audit_logs', ['action' => 'business_lead.saved_to_crm', 'user_id' => $this->user->id]);
    }

    public function test_business_without_contact_person_uses_business_name_and_can_stay_unsaved(): void
    {
        $res = $this->addBusiness(['contact_person' => null, 'save_to_crm' => false, 'phone' => null])->assertCreated();
        $res->assertJsonPath('data.saved_to_crm', false)->assertJsonPath('lead', null);
        $this->getJson('/api/leads')->assertJsonCount(0, 'data');

        $id = $res->json('data.id');
        $this->postJson("/api/business-finder/leads/$id/convert", ['priority' => 'High', 'next_followup_date' => '2026-11-12'])
            ->assertOk()->assertJsonPath('lead.name', 'Sri Lakshmi Hospital')->assertJsonPath('lead.priority', 'High')
            ->assertJsonPath('lead.next_followup_time', '10:00');

        $this->postJson("/api/business-finder/leads/$id/convert")->assertStatus(409);
    }

    public function test_duplicate_phone_is_detected_and_can_be_linked(): void
    {
        $existing = $this->postJson('/api/leads', ['name' => 'Ravi', 'mobile' => '098400 12345'])->assertCreated()->json('data');

        $this->getJson('/api/business-finder/check-duplicate?phone=%2B919840012345')->assertOk()
            ->assertJsonPath('is_duplicate', true)->assertJsonPath('lead.id', $existing['id']);

        $this->addBusiness()->assertStatus(409)
            ->assertJsonPath('duplicate.type', 'lead')
            ->assertJsonPath('duplicate.lead.id', $existing['id'])
            ->assertJsonPath('duplicate.can_link', true);
        $this->assertSame(0, BusinessLead::count());

        $this->addBusiness(['on_duplicate' => 'link'])->assertCreated()->assertJsonPath('lead.id', $existing['id']);
        $this->getJson('/api/leads')->assertJsonCount(1, 'data');   // no second lead

        // Same phone again → duplicate business
        $this->addBusiness(['business_name' => 'Other name', 'on_duplicate' => 'link'])->assertStatus(409)
            ->assertJsonPath('duplicate.type', 'business_lead');
    }

    public function test_users_cannot_access_each_others_businesses(): void
    {
        $id = $this->addBusiness()->json('data.id');
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/business-finder/leads/$id")->assertNotFound()->assertJson(['message' => 'Record not found.']);
        $this->putJson("/api/business-finder/leads/$id", ['notes' => 'x'])->assertNotFound();
        $this->postJson("/api/business-finder/leads/$id/verify-contact", ['verification_status' => 'Verified'])->assertNotFound();
        $this->getJson('/api/business-finder/leads')->assertJsonCount(0, 'data');
        // Same phone is not a duplicate for another user
        $this->addBusiness()->assertCreated();
    }

    public function test_update_syncs_crm_lead_and_list_filters(): void
    {
        $id = $this->addBusiness()->json('data.id');
        $this->addBusiness(['business_name' => 'Green Valley School', 'category' => 'Schools', 'phone' => '9000000001', 'city' => 'Madurai', 'website_status' => 'Website available', 'website_url' => 'greenvalley.test']);

        $this->putJson("/api/business-finder/leads/$id", ['business_name' => 'Sri Lakshmi Multispeciality', 'email' => 'info@srilakshmi.test'])
            ->assertOk()->assertJsonPath('lead.company', 'Sri Lakshmi Multispeciality')->assertJsonPath('lead.email', 'info@srilakshmi.test');

        $this->getJson('/api/business-finder/leads?website_status=Website%20available')->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.website_url', 'https://greenvalley.test/');
        $this->getJson('/api/business-finder/leads?category=Hospitals')->assertJsonCount(1, 'data');
        $this->getJson('/api/business-finder/leads?search=madurai')->assertJsonCount(1, 'data');
        $this->getJson('/api/business-finder/leads?contact=pending')->assertJsonCount(2, 'data');
        $this->getJson('/api/business-finder/leads?scope=no_website')->assertJsonCount(1, 'data');
        $this->getJson('/api/business-finder/leads?website_status=Website%20available&website_url=')->assertOk();

        $this->putJson("/api/business-finder/leads/$id", ['website_status' => 'Website outdated'])
            ->assertUnprocessable()->assertJsonValidationErrors(['website_url']);
    }

    // ---------------- Contact verification & do-not-contact ----------------

    public function test_contact_verification_is_manual_and_do_not_contact_stops_followups(): void
    {
        $res = $this->addBusiness(['next_followup_date' => '2026-11-11']);
        $id = $res->json('data.id');
        $leadId = $res->json('lead.id');

        $this->postJson("/api/business-finder/leads/$id/verify-contact", [
            'verification_status' => 'Verified', 'contact_source' => 'Called reception', 'notes' => 'Owner available after 5 pm',
        ])->assertOk()
            ->assertJsonPath('data.contact_status', 'Verified')
            ->assertJsonPath('data.contact_verified_at', '2026-11-10')
            ->assertJsonPath('data.contact_source', 'Called reception');
        $this->assertStringContainsString('Contact Verified: Owner available after 5 pm', BusinessLead::find($id)->notes);

        $this->postJson("/api/business-finder/leads/$id/verify-contact", ['verification_status' => 'Do not contact'])
            ->assertOk()->assertJsonPath('data.do_not_contact', true)
            ->assertJsonPath('lead.do_not_contact', true)
            ->assertJsonPath('lead.next_followup_at', null);
        $this->getJson('/api/followups?type=pending')->assertJsonCount(0, 'data');

        // Scheduling through the existing lead endpoint is refused
        $this->putJson("/api/leads/$leadId", ['next_followup_date' => '2026-11-12'])
            ->assertUnprocessable()->assertJsonValidationErrors(['next_followup_date']);
        // Adding a call note does not auto-schedule a new follow-up
        $this->postJson("/api/leads/$leadId/followups", ['call_status' => 'Callback', 'schedule_next' => true])
            ->assertCreated()->assertJsonPath('lead.next_followup_at', null);

        $this->postJson("/api/business-finder/leads/$id/verify-contact", ['verification_status' => 'Maybe'])
            ->assertUnprocessable()->assertJsonValidationErrors(['verification_status']);
    }

    // ---------------- Follow-up integration ----------------

    public function test_first_call_uses_existing_followup_rules(): void
    {
        $res = $this->addBusiness(['next_followup_date' => '2026-11-10', 'next_followup_time' => '15:00', 'priority' => 'Low']);
        $fid = $res->json('lead.followups.0.id') ?? $this->getJson('/api/followups?type=today')->json('data.0.id');

        // Low + Interested → +5 days = Sunday 15th → skipped to Monday 16th, time kept
        $this->postJson("/api/followups/$fid/complete", ['call_status' => 'Interested'])
            ->assertOk()->assertJsonPath('next_followup.date', '2026-11-16')->assertJsonPath('next_followup.time', '15:00')
            ->assertJsonPath('lead.status', 'Interested');

        $this->getJson('/api/business-finder/dashboard')->assertOk()
            ->assertJsonPath('stats.interested', 1)
            ->assertJsonPath('stats.saved_to_crm', 1);
        $this->assertSame(1, \App\Models\Followup::pending()->count());   // still one pending
    }

    // ---------------- Website checker ----------------

    public function test_website_check_success_with_redirect(): void
    {
        Http::fake([
            'http://shop.test/robots.txt' => Http::response("User-agent: *\nDisallow: /admin", 200),
            'http://shop.test/' => Http::response('', 301, ['Location' => 'https://shop.test/']),
            'https://shop.test/' => Http::response('', 200),
        ]);

        $this->postJson('/api/business-finder/verify-website', ['url' => 'http://shop.test'])->assertOk()
            ->assertJsonPath('data.result', 'reachable')
            ->assertJsonPath('data.http_status', 200)
            ->assertJsonPath('data.final_url', 'https://shop.test/')
            ->assertJsonPath('data.redirects', 1)
            ->assertJsonPath('data.suggested_status', 'Website available');
    }

    public function test_website_check_blocks_private_and_invalid_addresses(): void
    {
        Http::fake();
        foreach (['http://localhost/', 'http://127.0.0.1', 'http://192.168.1.10', 'http://169.254.169.254/latest', 'http://intranet', 'https://internal.example.com', 'http://user:pw@shop.test', 'http://shop.test:8080'] as $url) {
            $this->postJson('/api/business-finder/verify-website', ['url' => $url])->assertOk()
                ->assertJsonPath('data.result', 'not_allowed', "Expected $url to be blocked");
        }
        $this->travel(2)->minutes();   // stay under the 10-checks-per-minute limit
        $this->postJson('/api/business-finder/verify-website', ['url' => 'ftp://shop.test'])->assertJsonPath('data.result', 'invalid_url');
        $this->postJson('/api/business-finder/verify-website', ['url' => 'not a url'])->assertJsonPath('data.result', 'invalid_url');
        $this->postJson('/api/business-finder/verify-website', [])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_redirect_to_private_address_is_not_followed(): void
    {
        Http::fake([
            'https://shop.test/robots.txt' => Http::response('', 404),
            'https://shop.test/' => Http::response('', 302, ['Location' => 'http://10.0.0.1/admin']),
        ]);
        $this->postJson('/api/business-finder/verify-website', ['url' => 'shop.test'])
            ->assertJsonPath('data.result', 'not_allowed');
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '10.0.0.1'));
    }

    public function test_robots_txt_disallow_is_respected(): void
    {
        Http::fake(['https://private.test/robots.txt' => Http::response("User-agent: *\nDisallow: /", 200)]);

        $this->postJson('/api/business-finder/verify-website', ['url' => 'https://private.test/'])
            ->assertJsonPath('data.result', 'blocked_by_robots')
            ->assertJsonPath('data.suggested_status', null);
        Http::assertSentCount(1);   // only robots.txt
    }

    public function test_unreachable_site_is_not_reported_as_nonexistent_and_status_is_kept(): void
    {
        $id = $this->addBusiness(['website_url' => 'https://down.test', 'website_status' => 'Website available'])->json('data.id');
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        $res = $this->postJson("/api/business-finder/leads/$id/verify-website")->assertOk()
            ->assertJsonPath('check.result', 'unreachable')
            ->assertJsonPath('data.website_status', 'Website available')     // unchanged
            ->assertJsonPath('data.website_check_result', 'unreachable');
        $this->assertStringContainsString('does not mean the website does not exist', $res->json('check.message'));
        $this->assertNotNull($res->json('data.website_checked_at'));
    }

    public function test_ssl_error_falls_back_and_stored_check_marks_site_available(): void
    {
        $id = $this->addBusiness(['website_status' => 'Pending verification'])->json('data.id');
        $calls = 0;
        Http::fake(function ($request, $options) use (&$calls) {
            $calls++;
            if (($options['verify'] ?? true) !== false) {
                throw new ConnectionException('cURL error 60: SSL certificate problem: certificate has expired');
            }

            return str_ends_with($request->url(), 'robots.txt') ? Http::response('', 404) : Http::response('', 200);
        });

        $this->postJson("/api/business-finder/leads/$id/verify-website", ['url' => 'expired.test'])->assertOk()
            ->assertJsonPath('check.result', 'reachable')
            ->assertJsonPath('check.ssl_valid', false)
            ->assertJsonPath('data.website_url', 'https://expired.test/')
            ->assertJsonPath('data.website_status', 'Website available')
            ->assertJsonPath('data.website_http_status', 200);
        $this->assertDatabaseHas('audit_logs', ['action' => 'business_lead.website_checked']);
    }

    public function test_http_error_and_dns_failure_leave_status_to_salesperson(): void
    {
        Http::fake([
            'https://gone.test/robots.txt' => Http::response('', 404),
            'https://gone.test/*' => Http::response('', 404),
        ]);
        $this->postJson('/api/business-finder/verify-website', ['url' => 'gone.test/old-page'])
            ->assertJsonPath('data.result', 'http_error')->assertJsonPath('data.http_status', 404)
            ->assertJsonPath('data.suggested_status', null);

        $this->postJson('/api/business-finder/verify-website', ['url' => 'nowhere.example.com'])
            ->assertJsonPath('data.result', 'unreachable');
    }

    public function test_website_check_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/business-finder/verify-website', ['url' => "site$i.test"])->assertOk();
        }
        $this->postJson('/api/business-finder/verify-website', ['url' => 'site11.test'])->assertStatus(429);
    }

    // ---------------- Exports ----------------

    public function test_exports_csv_excel_pdf_with_filters_and_dnc_excluded(): void
    {
        $a = $this->addBusiness()->json('data.id');
        $b = $this->addBusiness(['business_name' => 'Green Valley School', 'category' => 'Schools', 'phone' => '9000000001', 'website_status' => 'Pending verification'])->json('data.id');
        $c = $this->addBusiness(['business_name' => 'Blocked Traders', 'category' => 'Traders', 'phone' => '9000000002', 'contact_status' => 'Do not contact'])->json('data.id');
        $this->postJson("/api/business-finder/leads/$b/verify-contact", ['verification_status' => 'Verified']);

        $csv = $this->get('/api/business-finder/export/csv')->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $body = file_get_contents($csv->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Sri Lakshmi Hospital', $body);
        $this->assertStringNotContainsString('Blocked Traders', $body);

        $verified = $this->get('/api/business-finder/export/csv?scope=verified_contacts')->assertOk();
        $body = file_get_contents($verified->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Green Valley School', $body);
        $this->assertStringNotContainsString('Sri Lakshmi Hospital', $body);

        $selected = $this->get("/api/business-finder/export/csv?ids[]=$a&ids[]=$c&include_do_not_contact=1")->assertOk();
        $body = file_get_contents($selected->baseResponse->getFile()->getPathname());
        $this->assertStringContainsString('Blocked Traders', $body);
        $this->assertStringNotContainsString('Green Valley School', $body);

        $xlsx = $this->get('/api/business-finder/export/excel?scope=no_website')->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('Content-Type'));

        $pdf = $this->get('/api/business-finder/export/pdf')->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', file_get_contents($pdf->baseResponse->getFile()->getPathname()));

        $this->get('/api/business-finder/export/doc')->assertNotFound();
        $this->getJson('/api/business-finder/export/csv?scope=everything')->assertUnprocessable();
        $this->assertSame(5, AuditLog::where('action', 'business_lead.exported')->count());

        // Another user's export is empty
        Sanctum::actingAs(User::factory()->create());
        $other = $this->get('/api/business-finder/export/csv')->assertOk();
        $lines = array_filter(explode("\n", trim(file_get_contents($other->baseResponse->getFile()->getPathname()))));
        $this->assertCount(1, $lines);   // header only
    }

    // ---------------- Dashboard & suggestions ----------------

    public function test_dashboard_and_suggestions(): void
    {
        $this->getJson('/api/business-finder/search-links?category=Hospitals&city=Madurai');
        $this->addBusiness(['city' => 'Madurai', 'contact_status' => 'Verified']);
        $this->addBusiness(['business_name' => 'X Traders', 'phone' => '9000000005', 'save_to_crm' => false, 'website_status' => 'Pending verification']);

        $this->getJson('/api/business-finder/dashboard')->assertOk()
            ->assertJsonPath('stats.total_researched', 2)
            ->assertJsonPath('stats.saved_to_crm', 1)
            ->assertJsonPath('stats.contact_verified', 1)
            ->assertJsonPath('stats.pending_verification', 1)
            ->assertJsonPath('stats.searches', 1)
            ->assertJsonPath('by_category.Hospitals', 2)
            ->assertJsonCount(2, 'recent_businesses');

        $s = $this->getJson('/api/business-finder/suggestions')->assertOk();
        $this->assertSame('Madurai', $s->json('suggested_searches.0.city'));
        $this->assertNotContains('Hospitals', array_column($s->json('suggested_searches'), 'category'));
        $s->assertJsonCount(1, 'recent_searches')->assertJsonCount(1, 'not_saved_to_crm')->assertJsonCount(1, 'needs_verification');
    }
}
