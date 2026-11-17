<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

// role, is_active, must_change_password, created_by and avatar_path are intentionally NOT
// mass assignable. They must be set explicitly by authorised code.
#[Fillable(['name', 'username', 'email', 'password', 'job_title', 'whatsapp'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    public const ROLE_SUPERADMIN = 'superadmin';

    public const ROLE_USER = 'user';

    public const ROLES = [self::ROLE_SUPERADMIN, self::ROLE_USER];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by');
    }

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /** Usernames are always stored lowercase. */
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = mb_strtolower(trim($value));
    }

    /** Empty email is stored as NULL so the unique index allows many blanks. */
    public function setEmailAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);
        $this->attributes['email'] = $value === '' ? null : $value;
    }

    /** Empty job title is stored as NULL. */
    public function setJobTitleAttribute(?string $value): void
    {
        $value = $value === null ? null : trim($value);
        $this->attributes['job_title'] = $value === '' ? null : $value;
    }

    /** URL of the avatar image (served by an authenticated route), or null. */
    public function avatarUrl(): ?string
    {
        if (! $this->avatar_path) {
            return null;
        }

        return route('avatars.show', $this).'?v='.($this->updated_at?->timestamp ?? 0);
    }

    /**
     * Normalise a WhatsApp number to international format (+62...).
     * "0812-3456-789" and "62812..." both become "+62812...". Returns null for blank.
     */
    public static function normaliseWhatsapp(?string $value): ?string
    {
        $value = preg_replace('/[\s\-().]/', '', (string) $value);

        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, '+')) {
            return $value;
        }
        if (str_starts_with($value, '00')) {
            return '+'.substr($value, 2);
        }
        if (str_starts_with($value, '0')) {
            return '+62'.substr($value, 1);
        }
        if (str_starts_with($value, '62')) {
            return '+'.$value;
        }

        return $value;
    }

    /** Log this user out everywhere by deleting all their sessions. */
    public function endAllSessions(): void
    {
        DB::table('sessions')->where('user_id', $this->id)->delete();
    }

    public static function activeSuperadminCount(): int
    {
        return static::where('role', self::ROLE_SUPERADMIN)->where('is_active', true)->count();
    }
}
