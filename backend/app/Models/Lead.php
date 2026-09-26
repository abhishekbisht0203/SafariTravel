<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A lead (website inquiry) captured by the API.
 *
 * The column set deliberately mirrors the WordPress `safari_leads` table so
 * that moving a submission from one intake path to the other is a mechanical
 * mapping, and so the eventual consolidation of the two stores is predictable.
 *
 * @property int $id
 * @property string $status
 * @property string $name
 * @property string $email
 * @property string|null $phone
 * @property int|null $destination_id
 * @property int|null $tour_id
 * @property string|null $destination_text
 * @property string|null $travel_style
 * @property string|null $date_from
 * @property string|null $date_to
 * @property bool $dates_flexible
 * @property int|null $adults
 * @property int|null $children
 * @property string|null $budget_range
 * @property string|null $subject
 * @property string|null $message
 * @property string $source_form
 * @property string|null $source_url
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $referrer
 * @property bool $consent_privacy
 * @property bool $consent_marketing
 * @property string|null $ip_hash
 * @property int|null $assigned_to
 * @property int|null $wordpress_lead_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class Lead extends Model
{
    /** @use HasFactory<\Database\Factories\LeadFactory> */
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_CLOSED_WON = 'closed_won';

    public const STATUS_CLOSED_LOST = 'closed_lost';

    public const STATUS_SPAM = 'spam';

    /**
     * Every status a lead may hold. Mirrors Safari_Lead_DB::STATUSES.
     *
     * @var array<string, string>
     */
    public const STATUSES = [
        self::STATUS_NEW => 'New',
        self::STATUS_IN_PROGRESS => 'In progress',
        self::STATUS_CONTACTED => 'Contacted',
        self::STATUS_CLOSED_WON => 'Closed won',
        self::STATUS_CLOSED_LOST => 'Closed lost',
        self::STATUS_SPAM => 'Spam',
    ];

    /**
     * Statuses that are finished, and therefore eligible for retention.
     *
     * @var string[]
     */
    public const CLOSED_STATUSES = [
        self::STATUS_CLOSED_WON,
        self::STATUS_CLOSED_LOST,
        self::STATUS_SPAM,
    ];

    /** @var list<string> */
    protected $fillable = [
        'status',
        'name',
        'email',
        'phone',
        'destination_id',
        'tour_id',
        'destination_text',
        'travel_style',
        'date_from',
        'date_to',
        'dates_flexible',
        'adults',
        'children',
        'budget_range',
        'subject',
        'message',
        'source_form',
        'source_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'referrer',
        'consent_privacy',
        'consent_marketing',
        'ip_hash',
        'assigned_to',
        'wordpress_lead_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dates_flexible' => 'boolean',
            'consent_privacy' => 'boolean',
            'consent_marketing' => 'boolean',
            'destination_id' => 'integer',
            'tour_id' => 'integer',
            'adults' => 'integer',
            'children' => 'integer',
            'assigned_to' => 'integer',
            'wordpress_lead_id' => 'integer',
            'date_from' => 'date',
            'date_to' => 'date',
        ];
    }

    /**
     * @return HasMany<LeadNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(LeadNote::class)->latest();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Whether the lead has been closed.
     */
    public function isClosed(): bool
    {
        return in_array($this->status, self::CLOSED_STATUSES, true);
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeStatus(Builder $query, ?string $status): void
    {
        if (null !== $status && '' !== $status) {
            $query->where('status', $status);
        }
    }

    /**
     * Exclude spam unless the caller explicitly asks for it.
     *
     * @param  Builder<Lead>  $query
     */
    public function scopeReal(Builder $query, bool $withSpam = false): void
    {
        if (! $withSpam) {
            $query->where('status', '!=', self::STATUS_SPAM);
        }
    }

    /**
     * @param  Builder<Lead>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ('' === $term) {
            return;
        }

        $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $term) . '%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('destination_text', 'like', $like)
                ->orWhere('subject', 'like', $like);
        });
    }

    /**
     * Human label for the current status.
     */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
