<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analysis_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('mode', 20);
            $table->boolean('has_image')->default(false);
            $table->unsignedBigInteger('image_bytes')->nullable();
            $table->string('image_mime', 120)->nullable();
            $table->string('image_client_name', 255)->nullable();
            $table->unsignedInteger('text_length')->nullable();
            $table->boolean('has_url')->default(false);
            $table->string('status', 20);
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analysis_logs');
    }
};
