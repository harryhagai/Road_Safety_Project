<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('segment_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('road_segments', function (Blueprint $table) {
            $table->foreignId('segment_type_id')
                ->nullable()
                ->after('segment_type')
                ->constrained('segment_types')
                ->nullOnDelete();
        });

        $timestamp = now();
        $defaults = [
            ['name' => 'Urban road', 'description' => 'Segments inside built-up urban areas.'],
            ['name' => 'Highway', 'description' => 'Major highways and trunk roads.'],
            ['name' => 'Junction', 'description' => 'Intersections, roundabouts, and connection nodes.'],
            ['name' => 'School zone', 'description' => 'Segments around schools and learning institutions.'],
            ['name' => 'Market area', 'description' => 'Segments around busy commercial or market areas.'],
        ];

        foreach ($defaults as $item) {
            DB::table('segment_types')->updateOrInsert(
                ['name' => $item['name']],
                [
                    'slug' => Str::slug($item['name']),
                    'description' => $item['description'],
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }

        $legacyTypes = DB::table('road_segments')
            ->whereNotNull('segment_type')
            ->select('segment_type')
            ->distinct()
            ->pluck('segment_type')
            ->filter();

        foreach ($legacyTypes as $legacyType) {
            $name = Str::of((string) $legacyType)->replace('_', ' ')->title()->toString();
            $baseSlug = Str::slug($name);
            $slug = $baseSlug !== '' ? $baseSlug : 'segment-type';
            $suffix = 2;

            while (DB::table('segment_types')->where('slug', $slug)->exists()) {
                $slug = sprintf('%s-%d', $baseSlug !== '' ? $baseSlug : 'segment-type', $suffix);
                $suffix++;
            }

            DB::table('segment_types')->updateOrInsert(
                ['name' => $name],
                [
                    'slug' => $slug,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]
            );
        }

        $segmentTypes = DB::table('segment_types')->pluck('id', 'name');

        DB::table('road_segments')
            ->orderBy('id')
            ->get(['id', 'segment_type'])
            ->each(function ($segment) use ($segmentTypes) {
                $name = Str::of((string) $segment->segment_type)->replace('_', ' ')->title()->toString();

                if ($name !== '' && $segmentTypes->has($name)) {
                    DB::table('road_segments')
                        ->where('id', $segment->id)
                        ->update(['segment_type_id' => $segmentTypes[$name]]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('road_segments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('segment_type_id');
        });

        Schema::dropIfExists('segment_types');
    }
};
