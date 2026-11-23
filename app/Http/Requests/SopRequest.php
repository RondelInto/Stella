<?php

namespace App\Http\Requests;

use App\Models\SopRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating/editing SOPs (Management only).
 */
class SopRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manage-sops');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sop_code' => strtoupper(trim((string) $this->input('sop_code'))),
            'category' => trim((string) $this->input('category')),
        ]);
    }

    public function rules(): array
    {
        $sop = $this->route('sop');

        return [
            'sop_code' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9._-]+$/',
                Rule::unique('sop_records', 'sop_code')->ignore($sop?->id)],
            'title' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:60000'],
            'effective_date' => ['nullable', 'date'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::exists('roles', 'id')],
            // Status is chosen only when creating; afterwards it changes with the status buttons.
            'status' => [$sop ? 'prohibited' : 'required', Rule::in([SopRecord::STATUS_DRAFT, SopRecord::STATUS_ACTIVE])],
        ];
    }

    public function messages(): array
    {
        return [
            'sop_code.regex' => 'The SOP code may only contain letters, numbers, dots, underscores, and dashes (no spaces).',
            'roles.required' => 'Assign the SOP to at least one role.',
            'roles.min' => 'Assign the SOP to at least one role.',
            'content.required' => 'Write the procedure.',
        ];
    }

    public function attributes(): array
    {
        return ['sop_code' => 'SOP code', 'content' => 'procedure'];
    }

    /** The SOP fields (without roles). */
    public function sopData(): array
    {
        return $this->safe()->except(['roles']);
    }
}
