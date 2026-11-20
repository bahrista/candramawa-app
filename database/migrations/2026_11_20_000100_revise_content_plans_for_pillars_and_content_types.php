<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. Content pillar becomes a choice from the brand's own list.
        Schema::table('content_plans', function (Blueprint $table) {
            $table->foreignId('content_pillar_id')->nullable()->after('title')
                ->constrained('brand_content_pillars')->restrictOnDelete();
        });

        // Pillars people had typed so far become entries in their brand's list.
        DB::table('content_plans')->whereNotNull('content_pillar')->where('content_pillar', '!=', '')->orderBy('id')
            ->each(function ($plan) use ($now) {
                $name = trim($plan->content_pillar);

                $existing = DB::table('brand_content_pillars')->where('brand_id', $plan->brand_id)
                    ->whereRaw('lower(name) = ?', [mb_strtolower($name)])->first();

                $pillarId = $existing->id ?? DB::table('brand_content_pillars')->insertGetId([
                    'brand_id' => $plan->brand_id, 'name' => $name, 'created_at' => $now, 'updated_at' => $now,
                ]);

                DB::table('content_plans')->where('id', $plan->id)->update(['content_pillar_id' => $pillarId]);
            });

        // 2. "Format" is dropped: reports use the content type instead.
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropColumn(['content_pillar', 'format']);
        });

        // 3. References become a list of {kind: link|knowledge|product, ...}.
        Schema::table('content_plans', function (Blueprint $table) {
            $table->renameColumn('reference_links', 'brief_references');
        });

        DB::table('content_plans')->whereNotNull('brief_references')->orderBy('id')->each(function ($plan) {
            $items = json_decode($plan->brief_references, true) ?: [];
            $items = array_values(array_map(fn ($item) => is_string($item) ? ['kind' => 'link', 'url' => $item] : $item, $items));

            DB::table('content_plans')->where('id', $plan->id)->update(['brief_references' => json_encode($items)]);
        });

        // 4. "Post type" becomes "content type"; Instagram "Feed" is now "Single Post".
        Schema::table('content_plan_posts', function (Blueprint $table) {
            // MySQL needs an index on the foreign key before the composite unique one can go.
            $table->index('content_plan_id');
        });
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->dropUnique(['content_plan_id', 'post_type']);
        });
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->renameColumn('post_type', 'content_type');
        });
        DB::table('content_plan_posts')->where('content_type', 'instagram_feed')->update(['content_type' => 'instagram_single']);
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->unique(['content_plan_id', 'content_type']);
        });
    }

    public function down(): void
    {
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->dropUnique(['content_plan_id', 'content_type']);
        });
        DB::table('content_plan_posts')->where('content_type', 'instagram_single')->update(['content_type' => 'instagram_feed']);
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->renameColumn('content_type', 'post_type');
        });
        Schema::table('content_plan_posts', function (Blueprint $table) {
            $table->unique(['content_plan_id', 'post_type']);
            $table->dropIndex(['content_plan_id']);
        });

        Schema::table('content_plans', function (Blueprint $table) {
            $table->renameColumn('brief_references', 'reference_links');
        });

        Schema::table('content_plans', function (Blueprint $table) {
            $table->string('content_pillar', 100)->nullable()->after('title');
            $table->string('format', 100)->nullable()->after('content_pillar');
        });
        DB::table('content_plans')->whereNotNull('content_pillar_id')->orderBy('id')->each(function ($plan) {
            $name = DB::table('brand_content_pillars')->where('id', $plan->content_pillar_id)->value('name');
            DB::table('content_plans')->where('id', $plan->id)->update(['content_pillar' => $name]);
        });
        Schema::table('content_plans', function (Blueprint $table) {
            $table->dropConstrainedForeignId('content_pillar_id');
        });
    }
};
