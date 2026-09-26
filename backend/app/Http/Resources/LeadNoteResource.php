<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\LeadNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin LeadNote
 */
class LeadNoteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'lead_id' => (int) $this->lead_id,
            'user_id' => (int) $this->user_id,
            'type' => (string) $this->type,
            'content' => (string) $this->content,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
