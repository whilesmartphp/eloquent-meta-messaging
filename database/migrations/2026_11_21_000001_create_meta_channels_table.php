<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('meta-messaging.channels_table'), function (Blueprint $table) {
            $table->id();
            $table->morphs('owner');
            $table->string('platform');
            $table->string('external_id');
            $table->string('business_account_id')->nullable()->index();
            $table->string('name');
            $table->string('status')->default('connected');
            $table->text('access_token');
            $table->timestamp('token_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('meta-messaging.channels_table'));
    }
};
