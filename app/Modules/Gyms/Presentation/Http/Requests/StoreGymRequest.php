<?php

namespace App\Modules\Gyms\Presentation\Http\Requests;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGymRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $status = $this->input('status');
        if ($status === null && $this->has('is_active')) {
            $status = $this->boolean('is_active') ? 'active' : 'inactive';
        }

        $this->merge([
            'name' => trim((string) $this->input('name')),
            'slug' => strtolower(str_replace(' ', '-', trim((string) $this->input('slug')))),
            'owner_name' => trim((string) $this->input('owner_name')),
            'owner_email' => strtolower(trim((string) $this->input('owner_email'))),
            'status' => $status,
            'modules' => array_values(array_filter((array) $this->input('modules', []), fn ($value) => $value !== '')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('access-platform') === true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', 'unique:gyms,slug'],
            'owner_name' => ['required', 'string', 'max:255'],
            'owner_email' => ['required', 'email', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'timezone' => ['required', 'timezone'],
            'locale' => ['required', Rule::in(['fa', 'en'])],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(app(ModuleRegistry::class)->all()))],
        ];
    }
}
