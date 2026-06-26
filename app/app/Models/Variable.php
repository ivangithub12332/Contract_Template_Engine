<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Variable extends Model
{
    use HasFactory;

    protected $fillable = [
        'template_id',
        'key',          // {{variable_key}}
        'label',        // Отображаемое название в форме
        'type',         // text, textarea, number, currency, date, select, boolean, table
        'required',
        'options',      // Для select и table (JSON)
        'default_value',
        'hint',         // Подсказка для пользователя
    ];

    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
    ];

    // Связи
    public function template()
    {
        return $this->belongsTo(Template::class);
    }

    public function documentValues()
    {
        return $this->hasMany(DocumentValue::class);
    }

    // Скоупы
    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }

    public function scopeOptional($query)
    {
        return $query->where('required', false);
    }

    // Помощники
    public function isText(): bool
    {
        return in_array($this->type, ['text', 'textarea']);
    }

    public function isNumeric(): bool
    {
        return in_array($this->type, ['number', 'currency']);
    }

    public function isSelect(): bool
    {
        return $this->type === 'select';
    }

    public function isBoolean(): bool
    {
        return $this->type === 'boolean';
    }

    public function isTable(): bool
    {
        return $this->type === 'table';
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function getPlaceholder(): string
    {
        return "{{{$this->key}}}";
    }
}