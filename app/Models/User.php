<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // Define role constants
    const ROLE_SUPERADMIN = 0;
    const ROLE_ADMIN = 1;
    const ROLE_USER = 2;
    
    // Define role names mapping
    public static $roles = [
        self::ROLE_SUPERADMIN => 'superadmin',
        self::ROLE_ADMIN => 'admin',
        self::ROLE_USER => 'user',
    ];

    protected $primaryKey = 'id_user';
    
    protected $fillable = [
        'username',
        'name',
        'email',
        'password',
        'no_hp',
        'foto_profil',
        'tanggal_lahir',
        'role',
        'is_verified',
        'is_active',
        'is_deleted',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'tanggal_lahir' => 'date',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'role' => 'integer'
    ];

    protected $appends = ['role_name'];

    /**
     * Get the role name attribute
     */
    public function getRoleNameAttribute()
    {
        return self::$roles[$this->role] ?? 'unknown';
    }
    
    public function toArray()
    {
        $array = parent::toArray();
        $array['role_name'] = $this->role_name;
        return $array;
    }

    /**
     * Define a method to check if user has a specific role
     */
    public function hasRole($role)
    {
        // If role is a string (name), convert to role ID
        if (!is_numeric($role)) {
            $roleMap = array_flip(self::$roles);
            $role = $roleMap[strtolower($role)] ?? -1;
        }
        
        return $this->role === (int)$role;
    }
    
    /**
     * Check if user is admin or superadmin
     */
    public function isAdminOrHigher()
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERADMIN]);
    }
    
    // Relationship with complaints
    public function komplain()
    {
        return $this->hasMany(Komplain::class, 'id_user', 'id_user');
    }
}