<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'category',
        'format',
        'status',
        'file_path',
        'tags',
        'created_by'
    ];

    protected $casts = [
        'tags' => 'array',
    ];

    // Связи
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions()
    {
        return $this->hasMany(TemplateVersion::class);
    }

    public function variables()
    {
        return $this->hasMany(Variable::class);
    }

    public function currentVersion()
    {
        return $this->hasOne(TemplateVersion::class)->latest();
    }
}