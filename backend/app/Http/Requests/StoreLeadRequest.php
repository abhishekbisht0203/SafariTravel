<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for the public lead intake endpoint.
 *
 * The field names, types and allow-lists are identical to the WordPress
 * endpoint (Safari_Lead_REST::get_endpoint_args + Safari_Lead_Save::validate)
 * so a form works unchanged against either, and a response from either is
 * interpreted the same way by the theme's JavaScript.
 */
class StoreLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $budgets = array_merge((array) config('safari.leads.budget_ranges', []), ['']);

        return [
            'name' => ['required', 'string', 'max:190'],
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:10000'],
            'subject' => ['nullable', 'string', 'max:190'],

            'destination_id' => ['nullable', 'integer', 'min:0'],
            'tour_id' => ['nullable', 'integer', 'min:0'],
            'destination_text' => ['nullable', 'string', 'max:190'],
            'travel_style' => ['nullable', 'string', Rule::in(array_merge(
                (array) config('safari.leads.travel_styles', []),
                ['']
            ))],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'dates_flexible' => ['nullable', 'boolean'],
            'adults' => ['nullable', 'integer', 'min:0', 'max:999'],
            'children' => ['nullable', 'integer', 'min:0', 'max:999'],
            'budget_range' => ['nullable', 'string', Rule::in($budgets)],

            'source_form' => ['nullable', 'string', Rule::in(
                (array) config('safari.leads.source_forms', ['contact'])
            )],
            'source_url' => ['nullable', 'string', 'url:http,https', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:100'],
            'referrer' => ['nullable', 'string', 'url:http,https', 'max:500'],

            'consent_privacy' => ['required', 'accepted'],
            'consent_marketing' => ['nullable', 'boolean'],

            // Spam signals. `website` is the hidden honeypot.
            '_timestamp' => ['nullable', 'integer', 'min:0'],
            'website' => ['nullable', 'string', 'max:255'],
            'cf_turnstile_token' => ['nullable', 'string', 'max:2048'],
            'cf-turnstile-response' => ['nullable', 'string', 'max:2048'],
            '_wpnonce' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Please enter your name.',
            'name.max' => 'Name is too long (max 190 characters).',
            'email.required' => 'Please enter a valid email address.',
            'email.email' => 'Please enter a valid email address.',
            'consent_privacy.required' => 'You must agree to the Privacy Policy.',
            'consent_privacy.accepted' => 'You must agree to the Privacy Policy.',
        ];
    }

    /**
     * Attributes for the validation messages, so field names read as words.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date_from' => 'arrival date',
            'date_to' => 'departure date',
            'adults' => 'adults',
            'children' => 'children',
            'utm_source' => 'UTM source',
            'utm_medium' => 'UTM medium',
            'utm_campaign' => 'UTM campaign',
        ];
    }

    /**
     * Persistable payload: validated values, cast to the model's types, with
     * the spam-only fields removed.
     *
     * @return array<string, mixed>
     */
    public function toLeadPayload(): array
    {
        $data = $this->safe()->only([
            'name',
            'email',
            'phone',
            'message',
            'subject',
            'destination_id',
            'tour_id',
            'destination_text',
            'travel_style',
            'date_from',
            'date_to',
            'adults',
            'children',
            'budget_range',
            'source_url',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'referrer',
        ]);

        $data['source_form'] = (string) ($this->input('source_form') ?: 'contact');
        $data['dates_flexible'] = $this->boolean('dates_flexible');
        $data['consent_privacy'] = true;
        $data['consent_marketing'] = $this->boolean('consent_marketing');

        foreach (['travel_style', 'budget_range', 'phone', 'message', 'subject', 'destination_text'] as $nullable) {
            if ('' === ($data[$nullable] ?? '')) {
                $data[$nullable] = null;
            }
        }

        foreach (['destination_id', 'tour_id', 'adults', 'children'] as $integer) {
            $value = $data[$integer] ?? null;

            $data[$integer] = (null === $value || '' === $value) ? null : (int) $value;
        }

        if (0 === ($data['destination_id'] ?? null)) {
            $data['destination_id'] = null;
        }

        if (0 === ($data['tour_id'] ?? null)) {
            $data['tour_id'] = null;
        }

        return $data;
    }

    /**
     * Raw payload, used only for the spam heuristics.
     *
     * @return array<string, mixed>
     */
    public function spamSignals(): array
    {
        return $this->all();
    }
}
