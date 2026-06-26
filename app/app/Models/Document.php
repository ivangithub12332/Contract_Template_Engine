<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_version_id',
        'author_id',
        'file_path',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Связи
    public function templateVersion()
    {
        return $this->belongsTo(TemplateVersion::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function values()
    {
        return $this->hasMany(DocumentValue::class);
    }

    // Доступ к шаблону через версию
    public function template()
    {
        return $this->hasOneThrough(
            Template::class,
            TemplateVersion::class,
            'id',           // Foreign key on template_versions table
            'id',           // Foreign key on templates table
            'template_version_id', // Local key on documents table
            'template_id'   // Local key on template_versions table
        );
    }

    // Помощники
    public function getFullPathAttribute(): string
    {
        return storage_path("app/public/{$this->file_path}");
    }

    public function getDownloadNameAttribute(): string
    {
        return "document_{$this->id}." . pathinfo($this->file_path, PATHINFO_EXTENSION);
    }

    public function getMetadataValue(string $key, $default = null)
    {
        return $this->metadata[$key] ?? $default;
    }
}