<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('content_pillar', 100)->nullable();
            $table->string('format', 100)->nullable();
            $table->dateTime('publish_at')->nullable();
            $table->string('deadline_mode', 10)->default('auto');
            $table->dateTime('deadline_at')->nullable();
            $table->foreignId('content_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('multimedia_creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('copywriter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('publisher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('brief')->nullable();
            $table->json('reference_links')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('production_started_at')->nullable();
            $table->timestamp('review_submitted_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('publish_at');
            $table->index('deadline_at');
        });

        Schema::create('content_plan_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_plan_id')->constrained()->cascadeOnDelete();
            $table->string('post_type', 30);
            $table->json('media_links')->nullable();
            $table->text('caption')->nullable();
            $table->json('tags')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['content_plan_id', 'post_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_plan_posts');
        Schema::dropIfExists('content_plans');
    }
};
