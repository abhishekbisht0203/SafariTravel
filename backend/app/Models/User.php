<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * An API operator.
 *
 * Operators are separate from WordPress users on purpose: the backend never
 * authenticates against `wp_users`, so a WordPress password leak cannot
 * produce a valid API token and a leaked API token cannot log into wp-admin.
 * The two are correlated by email (and optionally by `wordpress_user_id`).
 */
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_AGENT = 'agent';

    public const ROLE_VIEWER = 'viewer';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'wordpress_user_id',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'wordpress_user_id' => 'integer',
        ];
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeRole(Builder $query, string ...$roles): void
    {
        $query->whereIn('role', $roles);
    }

    /**
     * May issue and revoke API tokens, and create operators.
     */
    public function isAdmin(): bool
    {
        return self::ROLE_ADMIN === $this->role;
    }

    /**
     * May read and update leads.
     */
    public function canManageLeads(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_AGENT], true);
    }

    /**
     * May read leads.
     */
    public function canViewLeads(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_AGENT, self::ROLE_VIEWER], true);
    }
}
