<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Lead;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for changing a lead's status.
 */
class UpdateLeadStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof \App\Models\User && $user->canManageLeads();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(array_keys(Lead::STATUSES))],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
