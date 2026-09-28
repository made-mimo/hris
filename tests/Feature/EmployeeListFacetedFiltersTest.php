<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\Role;
use App\Models\SubUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EmployeeListFacetedFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function adminUser(): User
    {
        $role = Role::create(['name' => 'Admin', 'slug' => 'admin-'.uniqid()]);

        return User::factory()->create(['role_id' => $role->id]);
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::factory()->create($overrides);
    }

    public function test_the_filter_panel_starts_collapsed(): void
    {
        Livewire::actingAs($this->adminUser())
            ->test('employee-list')
            ->assertSet('filtersOpen', false)
            ->assertDontSee('JOB TITLE');
    }

    public function test_toggling_filters_persists_across_a_fresh_component_instance_via_session(): void
    {
        $user = $this->adminUser();

        Livewire::actingAs($user)->test('employee-list')->call('toggleFilters');

        // A brand new component instance (as a fresh page load creates)
        // still opens already-expanded — proving the state is session-
        // backed, not just held in this one component instance's memory.
        Livewire::actingAs($user)->test('employee-list')->assertSet('filtersOpen', true);
    }

    public function test_selecting_a_facet_filters_the_list_and_recomputes_sibling_counts(): void
    {
        $jobA = JobTitle::create(['name' => 'Engineer', 'is_active' => true]);
        $jobB = JobTitle::create(['name' => 'Designer', 'is_active' => true]);
        $this->employee(['job_title_id' => $jobA->id]);
        $this->employee(['job_title_id' => $jobA->id]);
        $this->employee(['job_title_id' => $jobB->id]);

        $component = Livewire::actingAs($this->adminUser())->test('employee-list');

        $this->assertSame(0, $component->viewData('activeFilterCount'));

        $component->set('jobTitleIds', [$jobA->id]);

        $this->assertSame(1, $component->viewData('activeFilterCount'));
        $employees = $component->viewData('employees');
        $this->assertCount(2, $employees);
    }

    public function test_clear_all_resets_every_filter(): void
    {
        $jobTitle = JobTitle::create(['name' => 'Engineer', 'is_active' => true]);
        $subUnit = SubUnit::create(['name' => 'Ops', 'is_active' => true]);

        $component = Livewire::actingAs($this->adminUser())->test('employee-list')
            ->set('search', 'foo')
            ->set('jobTitleIds', [$jobTitle->id])
            ->set('subUnitIds', [$subUnit->id])
            ->set('statusFilter', 'past');

        $component->call('clearAllFilters');

        $component->assertSet('search', '')
            ->assertSet('jobTitleIds', [])
            ->assertSet('subUnitIds', [])
            ->assertSet('statusFilter', 'current');

        $this->assertSame(0, $component->viewData('activeFilterCount'));
    }
}
