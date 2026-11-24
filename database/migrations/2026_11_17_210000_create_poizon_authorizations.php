<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poizon_authorizations', function (Blueprint $table) {
            $table->id();
            $table->string('open_id')->unique();
            $table->text('access_token');
            $table->text('refresh_token');
            $table->timestamp('access_token_expires_at');
            $table->timestamp('refresh_token_expires_at');
            $table->text('scope');
            $table->boolean('refresh_pending')->default(false);
            $table->uuid('refresh_attempt')->nullable();
            $table->string('status')->default('active');
            $table->string('problem_reason')->nullable();
            $table->timestamp('problem_notified_at')->nullable();
            $table->string('problem_notification_status')->nullable();
            $table->timestamps();
        });
        Schema::create('poizon_connections', function (Blueprint $table) {
            $table->id();
            $table->string('direction')->unique();
            $table->foreignId('poizon_authorization_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poizon_connections');
        Schema::dropIfExists('poizon_authorizations');
    }
};
