<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Spec F3: "a lean listing model (name, title, sub-unit, location) and a detailed model adding work contact info." */
class EmployeeDirectoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'name' => $this->fullName(),
            'job_title' => $this->jobTitleName(),
            'sub_unit' => $this->departmentName(),
            'location' => $this->locationName(),
        ];

        if ($request->query('model') === 'detailed') {
            $data['work_email'] = $this->work_email;
            $data['phone_mobile'] = $this->phone_mobile;
        }

        return $data;
    }
}
