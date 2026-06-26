<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Связи
    public function templates()
    {
        return $this->hasMany(Template::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    // Проверка ролей
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isMethodologist(): bool
    {
        return $this->role === 'methodologist';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }
}