<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public representation of a lead.
 *
 * `ip_hash` is deliberately never exposed — it is an abuse-investigation aid,
 * not data any client needs.
 *
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'status' => (string) $this->status,
            'status_label' => $this->statusLabel(),
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'phone' => $this->phone,
            'subject' => $this->subject,
            'message' => $this->message,

            'destination_id' => $this->destination_id,
            'tour_id' => $this->tour_id,
            'destination_text' => $this->destination_text,
            'travel_style' => $this->travel_style,

            'date_from' => $this->date_from?->toDateString(),
            'date_to' => $this->date_to?->toDateString(),
            'dates_flexible' => (bool) $this->dates_flexible,
            'adults' => $this->adults,
            'children' => $this->children,
            'budget_range' => $this->budget_range,

            'source_form' => (string) $this->source_form,
            'source_url' => $this->source_url,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'referrer' => $this->referrer,

            'consent_privacy' => (bool) $this->consent_privacy,
            'consent_marketing' => (bool) $this->consent_marketing,

            'assigned_to' => $this->assigned_to,
            'wordpress_lead_id' => $this->wordpress_lead_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
