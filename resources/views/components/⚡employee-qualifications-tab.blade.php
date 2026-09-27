<?php

use App\Models\Employee;
use App\Models\EmployeeEducation;
use App\Models\EmployeeLanguage;
use App\Models\EmployeeLicense;
use App\Models\EmployeeMembership;
use App\Models\EmployeeSkill;
use App\Models\EmployeeWorkExperience;
use App\Models\MasterListItem;
use Livewire\Component;

new class extends Component
{
    public Employee $employee;

    // Education
    public string $eduInstitution = '';
    public string $eduQualification = '';
    public ?int $eduLevelId = null;
    public string $eduField = '';
    public string $eduStart = '';
    public string $eduEnd = '';

    // Skills
    public ?int $skillId = null;
    public string $skillYears = '';

    // Languages
    public ?int $languageId = null;
    public string $readingLevel = 'basic';
    public string $writingLevel = 'basic';
    public string $speakingLevel = 'basic';

    // Licenses
    public ?int $licenseTypeId = null;
    public string $licenseNumber = '';
    public string $licenseIssue = '';
    public string $licenseExpiry = '';

    // Memberships
    public ?int $membershipBodyId = null;
    public string $membershipFee = '';
    public string $membershipRenewal = '';

    // Work experience
    public string $weEmployer = '';
    public string $weJobTitle = '';
    public string $weStart = '';
    public string $weEnd = '';
    public string $weDescription = '';

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function addEducation(): void
    {
        $this->validate([
            'eduInstitution' => ['required', 'string', 'max:150'],
            'eduQualification' => ['required', 'string', 'max:150'],
            'eduLevelId' => ['nullable', 'exists:master_list_items,id'],
        ]);

        $this->employee->education()->create([
            'institution' => $this->eduInstitution,
            'qualification' => $this->eduQualification,
            'education_level_id' => $this->eduLevelId,
            'field_of_study' => $this->eduField ?: null,
            'start_date' => $this->eduStart ?: null,
            'end_date' => $this->eduEnd ?: null,
        ]);
        $this->reset('eduInstitution', 'eduQualification', 'eduLevelId', 'eduField', 'eduStart', 'eduEnd');
        session()->flash('status', 'Education record added.');
    }

    public function deleteEducation(int $id): void
    {
        EmployeeEducation::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function addSkill(): void
    {
        $this->validate([
            'skillId' => ['required', 'exists:master_list_items,id'],
            'skillYears' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);

        $this->employee->skills()->updateOrCreate(
            ['skill_id' => $this->skillId],
            ['years_experience' => $this->skillYears ?: 0]
        );
        $this->reset('skillId', 'skillYears');
        session()->flash('status', 'Skill added.');
    }

    public function deleteSkill(int $id): void
    {
        EmployeeSkill::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function addLanguage(): void
    {
        $this->validate(['languageId' => ['required', 'exists:master_list_items,id']]);

        $this->employee->languages()->updateOrCreate(
            ['language_id' => $this->languageId],
            ['reading_level' => $this->readingLevel, 'writing_level' => $this->writingLevel, 'speaking_level' => $this->speakingLevel]
        );
        $this->reset('languageId');
        $this->readingLevel = $this->writingLevel = $this->speakingLevel = 'basic';
        session()->flash('status', 'Language added.');
    }

    public function deleteLanguage(int $id): void
    {
        EmployeeLanguage::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function addLicense(): void
    {
        $this->validate(['licenseTypeId' => ['required', 'exists:master_list_items,id']]);

        $this->employee->licenses()->create([
            'license_type_id' => $this->licenseTypeId,
            'license_number' => $this->licenseNumber ?: null,
            'issue_date' => $this->licenseIssue ?: null,
            'expiry_date' => $this->licenseExpiry ?: null,
        ]);
        $this->reset('licenseTypeId', 'licenseNumber', 'licenseIssue', 'licenseExpiry');
        session()->flash('status', 'License added.');
    }

    public function deleteLicense(int $id): void
    {
        EmployeeLicense::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function addMembership(): void
    {
        $this->validate(['membershipBodyId' => ['required', 'exists:master_list_items,id']]);

        $this->employee->memberships()->create([
            'membership_body_id' => $this->membershipBodyId,
            'subscription_fee' => $this->membershipFee ?: null,
            'renewal_date' => $this->membershipRenewal ?: null,
        ]);
        $this->reset('membershipBodyId', 'membershipFee', 'membershipRenewal');
        session()->flash('status', 'Membership added.');
    }

    public function deleteMembership(int $id): void
    {
        EmployeeMembership::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function addWorkExperience(): void
    {
        $this->validate([
            'weEmployer' => ['required', 'string', 'max:150'],
            'weJobTitle' => ['required', 'string', 'max:150'],
        ]);

        $this->employee->workExperience()->create([
            'employer' => $this->weEmployer,
            'job_title' => $this->weJobTitle,
            'start_date' => $this->weStart ?: null,
            'end_date' => $this->weEnd ?: null,
            'description' => $this->weDescription ?: null,
        ]);
        $this->reset('weEmployer', 'weJobTitle', 'weStart', 'weEnd', 'weDescription');
        session()->flash('status', 'Work experience added.');
    }

    public function deleteWorkExperience(int $id): void
    {
        EmployeeWorkExperience::where('employee_id', $this->employee->id)->findOrFail($id)->delete();
    }

    public function with(): array
    {
        return [
            'education' => $this->employee->education()->with('educationLevel')->orderByDesc('end_date')->get(),
            'educationLevels' => MasterListItem::ofType(MasterListItem::TYPE_EDUCATION_LEVEL)->where('is_active', true)->orderBy('sort_order')->get(),
            'employeeSkills' => $this->employee->skills()->with('skill')->get(),
            'skillOptions' => MasterListItem::ofType(MasterListItem::TYPE_SKILL)->where('is_active', true)->orderBy('name')->get(),
            'employeeLanguages' => $this->employee->languages()->with('language')->get(),
            'languageOptions' => MasterListItem::ofType(MasterListItem::TYPE_LANGUAGE)->where('is_active', true)->orderBy('name')->get(),
            'licenses' => $this->employee->licenses()->with('licenseType')->orderByDesc('expiry_date')->get(),
            'licenseTypes' => MasterListItem::ofType(MasterListItem::TYPE_LICENSE_TYPE)->where('is_active', true)->orderBy('name')->get(),
            'memberships' => $this->employee->memberships()->with('membershipBody')->orderByDesc('renewal_date')->get(),
            'membershipBodies' => MasterListItem::ofType(MasterListItem::TYPE_MEMBERSHIP_BODY)->where('is_active', true)->orderBy('name')->get(),
            'workExperience' => $this->employee->workExperience()->orderByDesc('end_date')->get(),
        ];
    }
};
?>

<div class="flex flex-col gap-4">
    @if(session('status'))
        <div class="inline-flex items-center gap-2 self-start rounded-pill bg-accent-light px-3.5 py-2.5 text-xs font-semibold text-accent">{{ session('status') }}</div>
    @endif

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Education</h2>
        <form wire:submit="addEducation" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-6">
            <input type="text" wire:model="eduInstitution" placeholder="Institution" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="eduQualification" placeholder="Qualification" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <select wire:model="eduLevelId" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— level —</option>
                @foreach($educationLevels as $lvl)<option value="{{ $lvl->id }}">{{ $lvl->name }}</option>@endforeach
            </select>
            <input type="text" wire:model="eduField" placeholder="Field of study" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="eduStart" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <div class="flex gap-2">
                <input type="date" wire:model="eduEnd" class="w-full rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <button type="submit" class="shrink-0 rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
            </div>
        </form>
        <div class="divide-y divide-border">
            @foreach($education as $e)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $e->institution }} — {{ $e->qualification }} @if($e->educationLevel)<span class="text-text-muted">({{ $e->educationLevel->name }})</span>@endif</span>
                    <button wire:click="deleteEducation({{ $e->id }})" wire:confirm="Remove?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($education->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No education records yet.</div>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Skills</h2>
        <form wire:submit="addSkill" class="mb-3 flex gap-2">
            <select wire:model="skillId" class="flex-1 rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— skill —</option>
                @foreach($skillOptions as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
            </select>
            <input type="number" step="0.5" wire:model="skillYears" placeholder="Years" class="w-28 rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        <div class="flex flex-wrap gap-2">
            @foreach($employeeSkills as $es)
                <span class="inline-flex items-center gap-2 rounded-pill bg-bg px-3 py-1.5 text-xs text-text">
                    {{ $es->skill->name }} · {{ $es->years_experience }}y
                    <button wire:click="deleteSkill({{ $es->id }})" class="font-bold text-danger">×</button>
                </span>
            @endforeach
            @if($employeeSkills->isEmpty())<span class="text-sm text-text-muted">No skills yet.</span>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Languages</h2>
        <form wire:submit="addLanguage" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-5 md:items-end">
            <select wire:model="languageId" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— language —</option>
                @foreach($languageOptions as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
            </select>
            <select wire:model="readingLevel" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                @foreach(\App\Models\EmployeeLanguage::LEVELS as $lv)<option value="{{ $lv }}">Reading: {{ ucfirst($lv) }}</option>@endforeach
            </select>
            <select wire:model="writingLevel" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                @foreach(\App\Models\EmployeeLanguage::LEVELS as $lv)<option value="{{ $lv }}">Writing: {{ ucfirst($lv) }}</option>@endforeach
            </select>
            <select wire:model="speakingLevel" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                @foreach(\App\Models\EmployeeLanguage::LEVELS as $lv)<option value="{{ $lv }}">Speaking: {{ ucfirst($lv) }}</option>@endforeach
            </select>
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        <div class="divide-y divide-border">
            @foreach($employeeLanguages as $el)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $el->language->name }} — R:{{ $el->reading_level }} W:{{ $el->writing_level }} S:{{ $el->speaking_level }}</span>
                    <button wire:click="deleteLanguage({{ $el->id }})" wire:confirm="Remove?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($employeeLanguages->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No languages yet.</div>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Licenses</h2>
        <form wire:submit="addLicense" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-5 md:items-end">
            <select wire:model="licenseTypeId" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— license type —</option>
                @foreach($licenseTypes as $lt)<option value="{{ $lt->id }}">{{ $lt->name }}</option>@endforeach
            </select>
            <input type="text" wire:model="licenseNumber" placeholder="License number" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="licenseIssue" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="licenseExpiry" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        <div class="divide-y divide-border">
            @foreach($licenses as $lic)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $lic->licenseType->name }} @if($lic->license_number)<span class="font-mono text-text-muted">{{ $lic->license_number }}</span>@endif @if($lic->expiry_date)<span class="text-text-muted">· expires {{ $lic->expiry_date->format('j M Y') }}</span>@endif</span>
                    <button wire:click="deleteLicense({{ $lic->id }})" wire:confirm="Remove?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($licenses->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No licenses yet.</div>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Professional memberships</h2>
        <form wire:submit="addMembership" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-4 md:items-end">
            <select wire:model="membershipBodyId" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
                <option value="">— body —</option>
                @foreach($membershipBodies as $mb)<option value="{{ $mb->id }}">{{ $mb->name }}</option>@endforeach
            </select>
            <input type="number" step="0.01" wire:model="membershipFee" placeholder="Subscription fee" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="membershipRenewal" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        <div class="divide-y divide-border">
            @foreach($memberships as $m)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $m->membershipBody->name }} @if($m->renewal_date)<span class="text-text-muted">· renews {{ $m->renewal_date->format('j M Y') }}</span>@endif</span>
                    <button wire:click="deleteMembership({{ $m->id }})" wire:confirm="Remove?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($memberships->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No memberships yet.</div>@endif
        </div>
    </section>

    <section class="rounded-md border border-border bg-surface p-5 shadow-sm">
        <h2 class="mb-3.5 font-display text-base font-bold text-text">Work experience</h2>
        <form wire:submit="addWorkExperience" class="mb-3 grid grid-cols-2 gap-2 md:grid-cols-5 md:items-end">
            <input type="text" wire:model="weEmployer" placeholder="Employer" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="text" wire:model="weJobTitle" placeholder="Job title" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="weStart" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <input type="date" wire:model="weEnd" class="rounded-sm border border-border bg-surface px-2.5 py-2 text-sm text-text outline-none focus:border-primary">
            <button type="submit" class="rounded-sm bg-primary px-3 py-2 text-xs font-semibold text-white hover:bg-primary-dark">Add</button>
        </form>
        @error('weEmployer') <div class="mb-2 text-xs text-danger">{{ $message }}</div> @enderror
        <div class="divide-y divide-border">
            @foreach($workExperience as $we)
                <div class="flex items-center justify-between py-2 text-sm">
                    <span>{{ $we->employer }} — {{ $we->job_title }} @if($we->start_date)<span class="text-text-muted">({{ $we->start_date->format('Y') }}–{{ $we->end_date?->format('Y') ?? 'present' }})</span>@endif</span>
                    <button wire:click="deleteWorkExperience({{ $we->id }})" wire:confirm="Remove?" class="text-xs font-semibold text-danger">Delete</button>
                </div>
            @endforeach
            @if($workExperience->isEmpty())<div class="py-3 text-center text-sm text-text-muted">No work experience yet.</div>@endif
        </div>
    </section>
</div>
