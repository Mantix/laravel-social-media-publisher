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
        Schema::create('social_media_connections', function (Blueprint $table) {
            $table->id();
            $table->morphs('owner'); // Creates owner_id and owner_type columns for polymorphic relationship
            $table->string('platform'); // facebook, twitter, linkedin, etc.
            $table->string('connection_type')->default('profile'); // profile, page, company, etc.
            $table->string('platform_user_id')->nullable(); // User ID on the platform
            $table->string('platform_username')->nullable(); // Username on the platform
            $table->text('access_token')->nullable(); // Encrypted access token
            $table->text('refresh_token')->nullable(); // Encrypted refresh token
            $table->text('token_secret')->nullable(); // For OAuth 1.0 (Twitter)
            $table->timestamp('expires_at')->nullable(); // Token expiration
            $table->json('metadata')->nullable(); // Additional platform-specific data (page_id, organization_urn, etc.)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes
            $table->index(['owner_id', 'owner_type', 'platform']);
            $table->index(['owner_id', 'owner_type', 'platform', 'connection_type']);
            $table->unique(['owner_id', 'owner_type', 'platform', 'connection_type', 'platform_user_id'], 'unique_connection');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_media_connections');
    }
};

