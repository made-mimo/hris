<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\EmployeeDirectoryResource;
use App\Models\Employee;
use Illuminate\Http\Request;

/** Spec F6: "corporate directory." Mirrors ⚡directory-search.blade.php's query exactly — see that component's own doc comment for the termination/purge visibility rules. */
class DirectoryController extends ApiController
{
    public function index(Request $request)
    {
        $search = $request->query('search');

        $query = Employee::query()
            ->with(['jobTitle', 'subUnit', 'location'])
            ->where('is_gdpr_purged', false)
            ->when($search, fn ($q) => $q->where(fn ($q2) => $q2
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_id', 'like', "%{$search}%")
            ))
            ->when(! $search, fn ($q) => $q->whereDoesntHave('terminations'))
            ->when($request->query('job_title_id'), fn ($q) => $q->where('job_title_id', $request->query('job_title_id')))
            ->when($request->query('location_id'), fn ($q) => $q->where('location_id', $request->query('location_id')))
            ->orderBy('last_name');

        $paginated = $query->paginate(min((int) $request->query('per_page', 20), 100));

        return $this->success(
            EmployeeDirectoryResource::collection($paginated->items()),
            ['page' => $paginated->currentPage(), 'per_page' => $paginated->perPage(), 'total' => $paginated->total()]
        );
    }

    public function show(Request $request, Employee $employee)
    {
        abort_if($employee->is_gdpr_purged, 404, 'The requested resource was not found.');

        return $this->success(new EmployeeDirectoryResource($employee->load(['jobTitle', 'subUnit', 'location'])));
    }
}
