<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('variables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('templates')->onDelete('cascade');
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['text', 'textarea', 'number', 'currency', 'date', 'select', 'boolean', 'table']);
            $table->boolean('required')->default(false);
            $table->json('options')->nullable();
            $table->string('default_value')->nullable();
            $table->string('hint')->nullable();
            $table->timestamps();

            $table->unique(['template_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variables');
    }
};
