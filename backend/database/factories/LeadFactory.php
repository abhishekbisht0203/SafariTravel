<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * @return class-string<Lead>
     */
    protected $model = Lead::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => Lead::STATUS_NEW,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('+1-###-###-####'),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'destination_id' => null,
            'tour_id' => null,
            'destination_text' => fake()->randomElement(['Kenya', 'Tanzania', 'Botswana', 'South Africa']),
            'travel_style' => fake()->randomElement(['luxury', 'mid-range', 'budget']),
            'date_from' => now()->addMonths(3)->toDateString(),
            'date_to' => now()->addMonths(3)->addDays(10)->toDateString(),
            'dates_flexible' => false,
            'adults' => fake()->numberBetween(1, 4),
            'children' => fake()->numberBetween(0, 3),
            'budget_range' => fake()->randomElement(['2000_5000', '5000_10000', '10000_20000']),
            'source_form' => fake()->randomElement(['contact', 'plan_my_safari', 'tour_page']),
            'source_url' => 'https://'.fake()->domainName().'/contact/',
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'referrer' => null,
            'consent_privacy' => true,
            'consent_marketing' => false,
            'ip_hash' => hash('sha256', Str::random(16)),
        ];
    }

    public function spam(): static
    {
        return $this->state(fn (): array => ['status' => Lead::STATUS_SPAM]);
    }

    public function closed(): static
    {
        return $this->state(fn (): array => ['status' => Lead::STATUS_CLOSED_WON]);
    }

    public function old(int $days): static
    {
        return $this->state(fn (): array => [
            'created_at' => now()->subDays($days),
            'updated_at' => now()->subDays($days),
        ]);
    }
}
