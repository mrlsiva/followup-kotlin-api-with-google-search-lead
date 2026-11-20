<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\Followup;
use App\Models\Lead;
use App\Models\User;
use App\Services\BusinessFinder\BusinessLeadService;
use App\Services\FollowupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WebPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $manager;
    private User $sales;      // in the manager's team
    private User $other;      // not in the team

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00:00'));   // a Tuesday
        $this->admin = $this->makeUser('admin');
        $this->manager = $this->makeUser('manager');
        $this->sales = $this->makeUser('sales', $this->manager->id);
        $this->other = $this->makeUser('sales');
    }

    private function makeUser(string $role, ?int $managerId = null): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => $role, 'manager_id' => $managerId])->save();

        return $u->refresh();
    }

    private function leadFor(User $user, array $attrs = []): Lead
    {
        $lead = $user->leads()->create(array_merge(['name' => 'Lead of '.$user->name, 'mobile' => '9'.random_int(100000000, 999999999), 'company' => 'Acme'], $attrs));
        app(FollowupService::class)->syncNextFollowup($lead, now()->setTime(15, 0));

        return $lead->refresh();
    }

    // ---------------- Auth ----------------

    public function test_guests_are_sent_to_login_and_can_log_in(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Log in');

        $user = User::factory()->create(['email' => 'rep@sling.test', 'password' => 'secret123']);
        $this->post('/login', ['email' => 'rep@sling.test', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'rep@sling.test', 'password' => 'secret123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->last_login_at);

        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_disabled_users_cannot_log_in_or_use_the_api(): void
    {
        $user = User::factory()->create(['email' => 'off@sling.test', 'password' => 'secret123']);
        $user->forceFill(['is_active' => false])->save();

        $this->post('/login', ['email' => 'off@sling.test', 'password' => 'secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->postJson('/api/auth/login', ['email' => 'off@sling.test', 'password' => 'secret123'])->assertUnprocessable();

        Sanctum::actingAs($user);
        $this->getJson('/api/leads')->assertForbidden();
    }

    public function test_public_registration_is_closed_by_default(): void
    {
        $this->get('/register')->assertNotFound();
        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'x@y.z', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertForbidden();

        config(['crm.allow_registration' => true]);
        $this->get('/register')->assertOk();
        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'x@y.z', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertCreated();
    }

    // ---------------- Every page renders ----------------

    public function test_all_pages_render_for_admin_in_english_and_tamil(): void
    {
        $lead = $this->leadFor($this->admin);
        $business = app(BusinessLeadService::class)->create($this->admin, [
            'business_name' => 'Sri Lakshmi Hospital', 'category' => 'Hospitals', 'phone' => '9840012345', 'city' => 'Chennai',
            'website_status' => 'No website found',
        ], ['next_at' => now()->addDay()]);
        $this->actingAs($this->admin);
        $this->get(route('finder.results', ['category' => 'Hospitals', 'city' => 'Chennai']))->assertOk();
        $search = $this->admin->businessSearches()->first();

        $pages = [
            route('dashboard'), route('leads.index'), route('leads.create'), route('leads.show', $lead), route('leads.edit', $lead),
            route('followups.index'), route('followups.index', ['type' => 'overdue']), route('followups.index', ['type' => 'upcoming']),
            route('followups.index', ['type' => 'pending']), route('followups.index', ['type' => 'completed']),
            route('transfer.index'), route('finder.dashboard'), route('finder.search'), route('finder.history'),
            route('businesses.index'), route('businesses.create'), route('businesses.create', ['search_id' => $search->id]),
            route('businesses.show', $business), route('businesses.edit', $business),
            route('reports.index'), route('reports.index', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-10']),
            route('admin.users.index'), route('admin.users.create'), route('admin.users.edit', $this->sales), route('profile.edit'),
        ];
        foreach (['en', 'ta'] as $locale) {
            $this->admin->forceFill(['locale' => $locale])->save();
            foreach ($pages as $url) {
                $this->get($url)->assertOk();
            }
        }
        $this->get(route('dashboard'))->assertSee('டாஷ்போர்டு');    // Tamil label

        $this->get(route('reports.export', ['format' => 'excel']))->assertOk();
        $this->get(route('reports.export', ['format' => 'pdf']))->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->get(route('businesses.export', ['format' => 'pdf']))->assertOk();
        $this->get(route('leads.export'))->assertOk();
    }

    public function test_locale_switch_is_saved(): void
    {
        $this->get('/locale/ta')->assertRedirect();
        $this->get('/login')->assertSee('உள்நுழை');

        $this->actingAs($this->sales)->get('/locale/ta');
        $this->assertSame('ta', $this->sales->refresh()->locale);
        $this->get('/locale/xx')->assertNotFound();
    }

    // ---------------- Visibility & permissions ----------------

    public function test_sales_see_only_their_own_records(): void
    {
        $mine = $this->leadFor($this->sales);
        $theirs = $this->leadFor($this->other);
        $this->actingAs($this->sales);

        $this->get(route('leads.index'))->assertSee($mine->name)->assertDontSee($theirs->name);
        $this->get(route('leads.show', $theirs))->assertNotFound();
        $this->get(route('reports.index'))->assertForbidden();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_manager_edits_team_records_and_can_reassign_within_team(): void
    {
        $teamLead = $this->leadFor($this->sales);
        $outside = $this->leadFor($this->other);
        $this->actingAs($this->manager);

        $this->get(route('leads.index'))->assertSee($teamLead->name)->assertDontSee($outside->name);
        $this->get(route('leads.show', $teamLead))->assertOk()->assertDontSee('read-only')->assertSee(route('leads.edit', $teamLead));
        $this->get(route('leads.show', $outside))->assertNotFound();

        // Edit, call notes and follow-ups on a team member's lead; the lead stays theirs
        $this->get(route('leads.edit', $teamLead))->assertOk();
        $this->put(route('leads.update', $teamLead), ['name' => 'Renamed by manager'])->assertRedirect();
        $this->assertSame('Renamed by manager', $teamLead->refresh()->name);
        $this->post(route('followups.complete', $teamLead->followups()->pending()->first()), ['call_status' => 'Callback', 'schedule_next' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->post(route('leads.notes.store', $teamLead), ['call_status' => 'Not Answered'])->assertRedirect();
        $this->assertSame(2, $teamLead->followups()->completed()->count());
        $this->assertSame([$this->sales->id], Followup::where('lead_id', $teamLead->id)->distinct()->pluck('user_id')->all());

        // Own leads work as before
        $this->post(route('leads.store'), ['name' => 'Manager lead', 'mobile' => '9111111111'])->assertRedirect();
        $this->assertSame($this->manager->id, Lead::where('name', 'Manager lead')->value('user_id'));

        $this->get(route('reports.index'))->assertOk()->assertSee($this->sales->name)->assertDontSee($this->other->name);
        $this->get(route('admin.users.index'))->assertForbidden();

        // Reassign to the manager (within the team) works; to someone outside the team does not
        $this->post(route('leads.reassign', $teamLead), ['user_id' => $this->other->id])->assertForbidden();
        $this->post(route('leads.reassign', $teamLead), ['user_id' => $this->manager->id])->assertRedirect();
        $this->assertSame($this->manager->id, $teamLead->refresh()->user_id);
        $this->assertSame($this->manager->id, Followup::where('lead_id', $teamLead->id)->first()->user_id);
    }

    public function test_sales_cannot_edit_records_outside_their_own(): void
    {
        $teammate = $this->makeUser('sales', $this->manager->id);
        $theirs = $this->leadFor($teammate);
        $this->actingAs($this->sales);

        $this->get(route('leads.edit', $theirs))->assertNotFound();
        $this->put(route('leads.update', $theirs), ['name' => 'Hacked'])->assertNotFound();
    }

    public function test_admin_edits_everything_and_reassigns_with_business_record(): void
    {
        $business = app(BusinessLeadService::class)->create($this->other, ['business_name' => 'Green School', 'category' => 'Schools', 'phone' => '9000000077']);
        $this->actingAs($this->admin);

        $this->get(route('businesses.index'))->assertSee('Green School');
        $this->get(route('businesses.show', $business))->assertOk();
        $this->get(route('businesses.edit', $business))->assertOk();
        $this->post(route('businesses.verify-contact', $business), ['verification_status' => 'Verified'])->assertRedirect();
        $this->assertSame('Verified', $business->refresh()->contact_status->value);
        $this->post(route('leads.notes.store', $business->lead), ['call_status' => 'Interested'])->assertRedirect();
        $this->assertSame($this->other->id, $business->lead->followups()->completed()->first()->user_id);

        $this->post(route('leads.reassign', $business->lead), ['user_id' => $this->sales->id])->assertRedirect();
        $this->assertSame($this->sales->id, $business->refresh()->user_id);
        $this->assertSame($this->sales->id, $business->lead->user_id);

        // The new owner can now work on it through the API too
        Sanctum::actingAs($this->sales);
        $this->getJson("/api/business-finder/leads/{$business->id}")->assertOk();
    }

    // ---------------- Researcher (Business Finder only) ----------------

    public function test_researcher_only_uses_business_finder(): void
    {
        $researcher = $this->makeUser('researcher', $this->manager->id);
        $this->actingAs($researcher);

        $this->get('/')->assertRedirect(route('finder.dashboard'));
        $this->get(route('finder.dashboard'))->assertOk()->assertDontSee(route('leads.index'));
        foreach ([route('leads.index'), route('leads.create'), route('followups.index'), route('transfer.index'), route('reports.index')] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post(route('leads.store'), ['name' => 'X', 'mobile' => '9222222222'])->assertForbidden();
        $this->get(route('businesses.create'))->assertOk()->assertDontSee('Create a CRM lead now');

        // Saving a business never creates a CRM lead, even if asked to
        $this->post(route('businesses.store'), [
            'business_name' => 'Sun Clinic', 'category' => 'Hospitals', 'phone' => '9333333333',
            'save_to_crm' => '1', 'next_followup_date' => '2026-11-11', 'next_followup_time' => '10:00',
        ])->assertRedirect();
        $business = BusinessLead::where('business_name', 'Sun Clinic')->firstOrFail();
        $this->assertSame($researcher->id, $business->user_id);
        $this->assertNull($business->lead_id);
        $this->assertSame(0, Lead::count());

        $this->get(route('businesses.show', $business))->assertOk()->assertDontSee(route('businesses.convert', $business));
        $this->post(route('businesses.convert', $business))->assertForbidden();
        $this->post(route('businesses.verify-contact', $business), ['verification_status' => 'Verified'])->assertRedirect();

        // API: Business Finder works, CRM endpoints are refused
        Sanctum::actingAs($researcher);
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.role', 'researcher')->assertJsonPath('data.can_use_crm', false);
        $this->getJson('/api/business-finder/leads')->assertOk()->assertJsonPath('data.0.business_name', 'Sun Clinic');
        $this->postJson('/api/business-finder/leads', ['business_name' => 'Moon Store', 'category' => 'Hospitals', 'phone' => '9444444444'])
            ->assertCreated()->assertJsonPath('lead', null);
        $this->postJson("/api/business-finder/leads/{$business->id}/convert")->assertForbidden();
        foreach (['/api/dashboard', '/api/leads', '/api/followups', '/api/followups/reminders'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_manager_saves_researchers_business_to_crm_for_a_salesperson(): void
    {
        $researcher = $this->makeUser('researcher', $this->manager->id);
        $business = app(BusinessLeadService::class)->create($researcher, ['business_name' => 'Blue Hotel', 'category' => 'Hotels', 'phone' => '9555555555'], ['save_to_crm' => false]);
        $this->actingAs($this->manager);

        $this->get(route('businesses.index', ['saved' => '0']))->assertSee('Blue Hotel');
        $this->get(route('businesses.show', $business))->assertOk()
            ->assertSee(route('businesses.convert', $business))->assertSee('Assign lead to');

        // Cannot assign to someone outside the team or to a researcher
        $this->post(route('businesses.convert', $business), ['assign_to' => $this->other->id])->assertForbidden();
        $this->post(route('businesses.convert', $business), ['assign_to' => $researcher->id])->assertForbidden();

        $this->post(route('businesses.convert', $business), [
            'assign_to' => $this->sales->id, 'next_followup_date' => '2026-11-11', 'next_followup_time' => '11:00',
        ])->assertRedirect()->assertSessionHas('success');

        $business->refresh();
        $this->assertSame($researcher->id, $business->user_id);        // still credited to the researcher
        $this->assertSame($this->sales->id, $business->lead->user_id); // the salesperson works the lead
        $this->assertSame('2026-11-11 11:00', $business->lead->next_followup_at->format('Y-m-d H:i'));
        $this->assertSame($this->sales->id, $business->lead->followups()->pending()->first()->user_id);

        // Reassigning the lead later leaves the researcher's business record alone
        $this->post(route('leads.reassign', $business->lead), ['user_id' => $this->manager->id])->assertRedirect();
        $this->assertSame($researcher->id, $business->refresh()->user_id);
        $this->assertSame($this->manager->id, $business->lead->user_id);
    }

    public function test_admin_can_create_a_researcher_with_a_manager(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.users.store'), [
            'name' => 'Rita', 'email' => 'rita@sling.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'researcher', 'manager_id' => $this->manager->id, 'locale' => 'en', 'is_active' => 1,
        ])->assertRedirect();
        $rita = User::where('email', 'rita@sling.test')->firstOrFail();
        $this->assertTrue($rita->isResearcher());
        $this->assertSame($this->manager->id, $rita->manager_id);
        $this->get(route('admin.users.index'))->assertSee('Researcher');
    }

    public function test_api_stays_owner_only_for_admins(): void
    {
        $theirs = $this->leadFor($this->sales);
        Sanctum::actingAs($this->admin);
        $this->getJson("/api/leads/{$theirs->id}")->assertNotFound();
    }

    // ---------------- Main flows ----------------

    public function test_lead_and_followup_flow_on_the_web(): void
    {
        $this->actingAs($this->sales);
        $this->post(route('leads.store'), [
            'name' => 'Ravi', 'mobile' => '+91 98765 43210', 'source' => 'Call', 'status' => 'New', 'priority' => 'Medium',
            'next_followup_date' => '2026-11-10', 'next_followup_time' => '15:00',
        ])->assertRedirect();
        $lead = Lead::where('name', 'Ravi')->firstOrFail();
        $this->assertSame('2026-11-10 15:00', $lead->next_followup_at->format('Y-m-d H:i'));

        $f = $lead->followups()->pending()->first();
        $this->post(route('followups.complete', $f), ['call_status' => 'Interested', 'note' => 'Liked it', 'schedule_next' => 1])
            ->assertRedirect()->assertSessionHas('success');
        $this->assertSame('2026-11-13 15:00', $lead->refresh()->next_followup_at->format('Y-m-d H:i'));   // Medium → +3 days

        $this->post(route('leads.notes.store', $lead), ['call_status' => 'Callback', 'note' => 'Call later'])->assertRedirect();
        $this->assertSame(2, $lead->followups()->completed()->count());

        $this->post(route('leads.store'), ['name' => ''])->assertSessionHasErrors('name');
    }

    public function test_add_business_with_duplicate_review_and_link(): void
    {
        $existing = $this->leadFor($this->sales, ['mobile' => '+91 98400 12345']);
        $this->actingAs($this->sales);
        $form = ['business_name' => 'Sri Lakshmi Hospital', 'category' => 'Hospitals', 'phone' => '098400 12345', 'city' => 'Chennai',
            'website_status' => 'No website found', 'contact_status' => 'Pending', 'save_to_crm' => '1', 'priority' => 'Medium'];

        $this->from(route('businesses.create'))->post(route('businesses.store'), $form)
            ->assertRedirect(route('businesses.create'))->assertSessionHas('duplicate');
        $this->assertSame(0, BusinessLead::count());

        $this->getJson(route('finder.check-duplicate', ['phone' => '9840012345']))->assertOk()->assertJsonPath('is_duplicate', true)
            ->assertJsonPath('lead.id', $existing->id);

        $this->post(route('businesses.store'), $form + ['on_duplicate' => 'link'])->assertRedirect();
        $business = BusinessLead::firstOrFail();
        $this->assertSame($existing->id, $business->lead_id);

        $this->post(route('businesses.verify-contact', $business), ['verification_status' => 'Do not contact'])->assertRedirect();
        $this->assertTrue($existing->refresh()->do_not_contact);
        $this->assertNull($existing->next_followup_at);
    }

    public function test_admin_manages_users(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.users.store'), [
            'name' => 'New Rep', 'email' => 'new@sling.test', 'password' => 'secret123', 'password_confirmation' => 'secret123',
            'role' => 'sales', 'manager_id' => $this->manager->id, 'locale' => 'ta', 'is_active' => '1',
        ])->assertRedirect(route('admin.users.index'));
        $rep = User::where('email', 'new@sling.test')->firstOrFail();
        $this->assertSame($this->manager->id, $rep->manager_id);
        $this->assertTrue($rep->is_active);

        $rep->createToken('phone');
        $this->post(route('admin.users.toggle', $rep))->assertRedirect();
        $this->assertFalse($rep->refresh()->is_active);
        $this->assertSame(0, $rep->tokens()->count());   // signed out of the app

        // Admin cannot demote or disable themselves
        $this->put(route('admin.users.update', $this->admin), [
            'name' => 'A', 'email' => $this->admin->email, 'role' => 'sales', 'locale' => 'en', 'is_active' => '1',
        ])->assertSessionHas('error');
        $this->assertTrue($this->admin->refresh()->isAdmin());
        $this->post(route('admin.users.toggle', $this->admin))->assertSessionHas('error');

        // Turning a manager into sales clears their team
        $this->put(route('admin.users.update', $this->manager), [
            'name' => 'M', 'email' => $this->manager->email, 'role' => 'sales', 'locale' => 'en', 'is_active' => '1',
        ])->assertRedirect();
        $this->assertNull($this->sales->refresh()->manager_id);
    }

    public function test_profile_update_and_password_change(): void
    {
        $this->actingAs($this->sales);
        $this->put(route('profile.update'), ['name' => 'Renamed', 'email' => $this->sales->email, 'locale' => 'ta'])->assertSessionHas('success');
        $this->assertSame('ta', $this->sales->refresh()->locale);

        $this->put(route('profile.update'), ['name' => 'Renamed', 'email' => $this->sales->email, 'locale' => 'en',
            'current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('current_password');
    }
}
