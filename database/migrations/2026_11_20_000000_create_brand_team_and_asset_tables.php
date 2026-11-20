<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who works on a brand, and in which role (one row per person per role).
        Schema::create('brand_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 30);
            $table->timestamps();

            $table->unique(['brand_id', 'user_id', 'role']);
            $table->index('user_id');
        });

        Schema::create('brand_content_pillars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['brand_id', 'name']);
        });

        Schema::create('brand_knowledge_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('category', 100)->nullable();
            $table->mediumText('content');
            $table->json('links')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('brand_id');
        });

        Schema::create('brand_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('brand_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->default('product');
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('key_benefits')->nullable();
            $table->string('price', 100)->nullable();
            $table->json('links')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('brand_id');
        });

        // Every existing brand starts with its creator as Brand Owner.
        $now = now();
        DB::table('brands')->whereNotNull('created_by')->orderBy('id')->each(function ($brand) use ($now) {
            DB::table('brand_user')->insert([
                'brand_id' => $brand->id,
                'user_id' => $brand->created_by,
                'role' => 'brand_owner',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_products');
        Schema::dropIfExists('brand_knowledge_items');
        Schema::dropIfExists('brand_content_pillars');
        Schema::dropIfExists('brand_user');
    }
};
