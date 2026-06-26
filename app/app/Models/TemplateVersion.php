<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TemplateVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'version_number',
        'file_path',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    // Связи
    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    // Помощники
    public function getFullPathAttribute(): string
    {
        return storage_path("app/public/{$this->file_path}");
    }
}