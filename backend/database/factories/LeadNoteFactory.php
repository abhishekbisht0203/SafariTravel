<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\LeadNote;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeadNote>
 */
class LeadNoteFactory extends Factory
{
    /**
     * @return class-string<LeadNote>
     */
    protected $model = LeadNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lead_id' => \App\Models\Lead::factory(),
            'user_id' => 0,
            'type' => LeadNote::TYPE_NOTE,
            'content' => fake()->sentence(),
        ];
    }
}
