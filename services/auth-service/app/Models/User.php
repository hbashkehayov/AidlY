<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Access\Authorizable as AuthorizableContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Lumen\Auth\Authorizable;

class User extends Model implements AuthenticatableContract, AuthorizableContract
{
    use Authenticatable, Authorizable, HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'email',
        'password_hash',
        'role',
        'department_id',
        'avatar_url',
        'email_verified_at',
        'two_factor_secret',
        'two_factor_enabled',
        'is_active',
        'last_login_at',
        'last_login_ip',
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var string[]
     */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'two_factor_secret',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'is_active' => 'boolean',
        'id' => 'string',
        'department_id' => 'string',
    ];

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the primary key ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    /**
     * Set the password for the user.
     *
     * @param string $password
     * @return void
     */
    public function setPasswordAttribute($password)
    {
        $this->attributes['password_hash'] = app('hash')->make($password);
    }

    /**
     * Check if user has a specific role
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return $this->role === $role;
    }

    /**
     * Check if user has any of the given roles
     *
     * @param array $roles
     * @return bool
     */
    public function hasAnyRole(array $roles)
    {
        return in_array($this->role, $roles);
    }

    /**
     * Get the department that the user belongs to.
     */
    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Get the permissions for the user's role.
     */
    public function permissions()
    {
        return $this->hasManyThrough(
            Permission::class,
            RolePermission::class,
            'role',
            'id',
            'role',
            'permission_id'
        );
    }

    /**
     * Check if user has a specific permission
     *
     * @param string $resource
     * @param string $action
     * @return bool
     */
    public function hasPermission($resource, $action)
    {
        return $this->permissions()
            ->where('resource', $resource)
            ->where('action', $action)
            ->exists();
    }

    /**
     * Sessions relationship
     */
    public function sessions()
    {
        return $this->hasMany(Session::class, 'user_id');
    }

    /**
     * Password resets relationship
     */
    public function passwordResets()
    {
        return $this->hasMany(PasswordReset::class, 'email', 'email');
    }

    /**
     * Update last login information
     *
     * @param string|null $ip
     * @return void
     */
    public function updateLastLogin($ip = null)
    {
        $this->last_login_at = now();
        $this->last_login_ip = $ip;
        $this->login_attempts = 0;
        $this->save();
    }

    /**
     * Increment login attempts
     *
     * @return void
     */
    public function incrementLoginAttempts()
    {
        $this->login_attempts++;

        // Lock account after 5 failed attempts for 30 minutes
        if ($this->login_attempts >= 5) {
            $this->locked_until = now()->addMinutes(30);
        }

        $this->save();
    }

    /**
     * Check if account is locked
     *
     * @return bool
     */
    public function isLocked()
    {
        if ($this->locked_until === null) {
            return false;
        }

        if (now()->gt($this->locked_until)) {
            $this->locked_until = null;
            $this->login_attempts = 0;
            $this->save();
            return false;
        }

        return true;
    }

    /**
     * Reset login attempts
     *
     * @return void
     */
    public function resetLoginAttempts()
    {
        $this->login_attempts = 0;
        $this->locked_until = null;
        $this->save();
    }
}