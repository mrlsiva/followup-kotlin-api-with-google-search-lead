<?php

namespace Tests\Feature;

use App\Models\BusinessLead;
use App\Models\FinderCategory;
use App\Models\User;
use App\Services\BusinessFinder\BusinessLeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CategoryAdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create();
        $this->admin->forceFill(['role' => 'admin'])->save();
    }

    public function test_existing_categories_are_seeded(): void
    {
        $this->assertSame(17, FinderCategory::count());
        $this->assertTrue(FinderCategory::where('name', 'Real estate')->value('search_term') === 'Real estate companies');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/business-finder/categories')->assertOk()
            ->assertJsonFragment(['value' => 'Hospitals', 'label_ta' => 'மருத்துவமனைகள்', 'assignable' => true])
            ->assertJsonFragment(['value' => 'All businesses', 'assignable' => false]);
    }

    public function test_admin_adds_a_category_used_by_web_and_api(): void
    {
        $this->actingAs($this->admin)->post(route('admin.categories.store'), [
            'name' => '  Pharmacies ', 'name_ta' => 'மருந்தகங்கள்', 'search_term' => 'Medical shops', 'sort_order' => 155, 'is_active' => '1',
        ])->assertRedirect(route('admin.categories.index'));
        $this->assertDatabaseHas('business_categories', ['name' => 'Pharmacies', 'is_active' => true]);

        // Shows in the web dropdowns (Tamil label) and is accepted everywhere
        $this->admin->forceFill(['locale' => 'ta'])->save();
        $this->get(route('finder.search'))->assertOk()->assertSee('மருந்தகங்கள்');

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/business-finder/search-links?category=Pharmacies&city=Madurai&save_history=0')
            ->assertOk()->assertJsonPath('data.query', 'Medical shops in Madurai');
        $this->postJson('/api/business-finder/leads', ['business_name' => 'Apollo Pharmacy', 'category' => 'Pharmacies', 'save_to_crm' => false])
            ->assertCreated()->assertJsonPath('data.category', 'Pharmacies');
        $this->getJson('/api/business-finder/options')->assertJsonFragment(['Pharmacies']);
    }

    public function test_turned_off_category_is_hidden_but_existing_businesses_keep_it(): void
    {
        $b = app(BusinessLeadService::class)->create($this->admin, ['business_name' => 'Silk House', 'category' => 'Textile companies'], ['save_to_crm' => false]);
        $cat = FinderCategory::where('name', 'Textile companies')->first();

        $this->actingAs($this->admin)->post(route('admin.categories.toggle', $cat))->assertRedirect();
        $this->assertFalse($cat->refresh()->is_active);

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/business-finder/categories')->assertJsonMissing(['value' => 'Textile companies']);
        $this->getJson('/api/business-finder/search-links?category=Textile%20companies')->assertUnprocessable();
        $this->postJson('/api/business-finder/leads', ['business_name' => 'New Silk', 'category' => 'Textile companies'])->assertUnprocessable();
        // The existing business can still be edited without changing its category
        $this->putJson("/api/business-finder/leads/{$b->id}", ['category' => 'Textile companies', 'notes' => 'ok'])->assertOk();

        // In use → cannot be deleted
        $this->actingAs($this->admin)->delete(route('admin.categories.destroy', $cat))->assertSessionHas('error');
        $this->assertNotNull($cat->fresh());
    }

    public function test_rename_updates_businesses_and_history(): void
    {
        $b = app(BusinessLeadService::class)->create($this->admin, ['business_name' => 'Hotel Tamil Nadu', 'category' => 'Hotels'], ['save_to_crm' => false]);
        $this->admin->businessSearches()->create(['category' => 'Hotels', 'query' => 'Hotels in Ooty']);
        $cat = FinderCategory::where('name', 'Hotels')->first();

        $this->actingAs($this->admin)->put(route('admin.categories.update', $cat), [
            'name' => 'Hotels & Resorts', 'name_ta' => 'ஹோட்டல்கள்', 'sort_order' => 130, 'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('Hotels & Resorts', $b->refresh()->category);
        $this->assertSame('Hotels & Resorts', $this->admin->businessSearches()->first()->category);
    }

    public function test_built_in_categories_are_protected_and_unused_ones_can_be_deleted(): void
    {
        $all = FinderCategory::where('system', 'all')->first();
        $this->actingAs($this->admin);

        $this->put(route('admin.categories.update', $all), ['name' => 'Everything', 'name_ta' => 'எல்லாம்', 'sort_order' => 1])->assertRedirect();
        $this->assertSame('All businesses', $all->refresh()->name);       // name kept
        $this->assertSame('எல்லாம்', $all->name_ta);                        // label changed
        $this->post(route('admin.categories.toggle', $all))->assertSessionHas('error');
        $this->delete(route('admin.categories.destroy', $all))->assertSessionHas('error');

        $this->post(route('admin.categories.store'), ['name' => 'Hospitals', 'sort_order' => 1])->assertSessionHasErrors('name');   // duplicate

        $unused = FinderCategory::where('name', 'Colleges')->first();
        $this->delete(route('admin.categories.destroy', $unused))->assertSessionHas('success');
        $this->assertNull($unused->fresh());
        $this->assertSame(0, BusinessLead::where('category', 'Colleges')->count());
    }

    public function test_only_admins_manage_categories(): void
    {
        $sales = User::factory()->create();
        $this->actingAs($sales)->get(route('admin.categories.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.categories.index'))->assertOk()->assertSee('Real estate');
        $this->get(route('admin.categories.create'))->assertOk();
        $this->get(route('admin.categories.edit', FinderCategory::first()))->assertOk();
    }
}
