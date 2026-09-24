<?php

namespace App\Modules\Gyms\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGymRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-platform') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('gyms', 'slug')->ignore($this->route('gym'))],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(['fa', 'en'])],
        ];
    }
}
