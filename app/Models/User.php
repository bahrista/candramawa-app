<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\DB;

// role, is_active, must_change_password and created_by are intentionally NOT
// mass assignable. They must be set explicitly by authorised code.
#[Fillable(['name', 'username', 'email', 'password'])]
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
