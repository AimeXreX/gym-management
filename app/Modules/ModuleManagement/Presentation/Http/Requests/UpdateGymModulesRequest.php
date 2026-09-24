<?php

namespace App\Modules\ModuleManagement\Presentation\Http\Requests;

use App\Core\Modules\ModuleRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGymModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('access-platform') === true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['present', 'array'],
            'modules.*' => ['string', Rule::in(array_keys(app(ModuleRegistry::class)->all()))],
        ];
    }
}
