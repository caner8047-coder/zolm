<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencer_campaign_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignId('influencer_profile_id')->constrained('influencer_profiles')->cascadeOnDelete();
            $table->string('campaign_role', 50)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'influencer_profile_id'], 'inf_camp_member_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('influencer_campaign_members');
    }
};
