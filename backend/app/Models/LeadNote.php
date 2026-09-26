<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\LeadNoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A timestamped entry in a lead's history.
 *
 * Mirrors the WordPress `safari_lead_notes` table.
 *
 * @property int $id
 * @property int $lead_id
 * @property int $user_id
 * @property string $type
 * @property string $content
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class LeadNote extends Model
{
    /** @use HasFactory<LeadNoteFactory> */
    use HasFactory;

    public const TYPE_NOTE = 'note';

    public const TYPE_STATUS_CHANGE = 'status_change';

    public const TYPE_EMAIL_SENT = 'email_sent';

    public const TYPE_SYSTEM = 'system';

    /** @var list<string> */
    protected $fillable = [
        'lead_id',
        'user_id',
        'type',
        'content',
    ];

    /**
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
