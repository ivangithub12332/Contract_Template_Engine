<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentValue extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'variable_id',
        'value',
    ];

    protected $casts = [
        'value' => 'json', // Поддерживаем хранение массивов (для table)
    ];

    // Связи
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function variable()
    {
        return $this->belongsTo(Variable::class);
    }

    // Помощники
    public function getValueAsString(): string
    {
        if (is_array($this->value)) {
            return json_encode($this->value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $this->value;
    }

    public function getValueAsArray(): array
    {
        return is_array($this->value) ? $this->value : json_decode($this->value, true) ?? [];
    }

    public function isValueEmpty(): bool
    {
        return empty($this->value) || 
               (is_array($this->value) && empty(array_filter($this->value)));
    }
}