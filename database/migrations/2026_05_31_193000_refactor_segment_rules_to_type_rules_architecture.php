<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('road_segments', 'segment_type_id')) {
            Schema::table('road_segments', function (Blueprint $table): void {
                $table->foreignId('segment_type_id')->nullable()->constrained('segment_types')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('segment_rule_overrides')) {
            Schema::create('segment_rule_overrides', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('segment_id')->constrained('road_segments')->cascadeOnDelete();
                $table->foreignId('segment_type_rule_id')->constrained('segment_type_rules')->cascadeOnDelete();
                $table->string('rule_value')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->nullable();
                $table->dateTime('effective_from')->nullable();
                $table->dateTime('effective_to')->nullable();
                $table->timestamps();

                $table->unique(['segment_id', 'segment_type_rule_id'], 'segment_rule_override_unique');
                $table->index(['segment_id', 'effective_from', 'effective_to'], 'segment_rule_override_window_idx');
            });
        }

        if (! $this->hasIndex('segment_type_rules', 'segment_type_rules_unique_key')) {
            DB::statement('CREATE UNIQUE INDEX segment_type_rules_unique_key ON segment_type_rules (segment_type_id, rule_type(100), rule_name(100))');
        }

        if (! Schema::hasColumn('rule_violations', 'segment_id')) {
            Schema::table('rule_violations', function (Blueprint $table): void {
                $table->foreignId('segment_id')->nullable()->after('report_id')->constrained('road_segments')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('rule_violations', 'segment_type_rule_id')) {
            Schema::table('rule_violations', function (Blueprint $table): void {
                $table->foreignId('segment_type_rule_id')->nullable()->after('segment_id')->constrained('segment_type_rules')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('rule_violations', 'rule_name_snapshot')) {
            Schema::table('rule_violations', function (Blueprint $table): void {
                $table->string('rule_name_snapshot')->nullable()->after('segment_type_rule_id');
                $table->string('rule_type_snapshot', 100)->nullable()->after('rule_name_snapshot');
                $table->string('rule_value_snapshot')->nullable()->after('rule_type_snapshot');
                $table->text('rule_description_snapshot')->nullable()->after('rule_value_snapshot');
            });
        }
        if (! $this->hasIndex('rule_violations', 'rule_violations_segment_rule_idx')) {
            Schema::table('rule_violations', function (Blueprint $table): void {
                $table->index(['segment_id', 'segment_type_rule_id'], 'rule_violations_segment_rule_idx');
            });
        }

        if (! Schema::hasColumn('hotspots', 'segment_id')) {
            Schema::table('hotspots', function (Blueprint $table): void {
                $table->foreignId('segment_id')->nullable()->after('severity')->constrained('road_segments')->nullOnDelete();
            });
        }
        if (! Schema::hasColumn('hotspots', 'segment_type_rule_id')) {
            Schema::table('hotspots', function (Blueprint $table): void {
                $table->foreignId('segment_type_rule_id')->nullable()->after('segment_id')->constrained('segment_type_rules')->nullOnDelete();
            });
        }

        DB::statement(<<<'SQL'
            UPDATE rule_violations rv
            JOIN segment_rules sr ON sr.id = rv.rule_id
            LEFT JOIN road_segments rs ON rs.id = sr.segment_id
            SET
                rv.segment_id = sr.segment_id,
                rv.segment_type_rule_id = (
                    SELECT MIN(tr.id)
                    FROM segment_type_rules tr
                    WHERE tr.segment_type_id = rs.segment_type_id
                      AND tr.rule_type = sr.rule_type
                      AND tr.rule_name = sr.rule_name
                ),
                rv.rule_name_snapshot = sr.rule_name,
                rv.rule_type_snapshot = sr.rule_type,
                rv.rule_value_snapshot = sr.rule_value,
                rv.rule_description_snapshot = sr.description
        SQL);

        DB::statement(<<<'SQL'
            INSERT INTO segment_rule_overrides (
                segment_id,
                segment_type_rule_id,
                rule_value,
                description,
                is_active,
                effective_from,
                effective_to,
                created_at,
                updated_at
            )
            SELECT
                sr.segment_id,
                tr.id,
                sr.rule_value,
                sr.description,
                sr.is_active,
                sr.effective_from,
                sr.effective_to,
                NOW(),
                NOW()
            FROM segment_rules sr
            JOIN road_segments rs ON rs.id = sr.segment_id
            JOIN segment_type_rules tr
                ON tr.segment_type_id = rs.segment_type_id
               AND tr.rule_type = sr.rule_type
               AND tr.rule_name = sr.rule_name
            WHERE sr.segment_id IS NOT NULL
              AND (
                    COALESCE(sr.rule_value, '') <> COALESCE(tr.rule_value, '')
                 OR COALESCE(sr.description, '') <> COALESCE(tr.description, '')
                 OR COALESCE(sr.is_active, 1) <> COALESCE(tr.is_active, 1)
                 OR sr.effective_from IS NOT NULL
                 OR sr.effective_to IS NOT NULL
              )
        SQL);

        DB::statement(<<<'SQL'
            UPDATE hotspots h
            JOIN segment_rules sr ON sr.id = h.rule_id
            LEFT JOIN road_segments rs ON rs.id = sr.segment_id
            SET
                h.segment_id = sr.segment_id,
                h.segment_type_rule_id = (
                    SELECT MIN(tr.id)
                    FROM segment_type_rules tr
                    WHERE tr.segment_type_id = rs.segment_type_id
                      AND tr.rule_type = sr.rule_type
                      AND tr.rule_name = sr.rule_name
                )
        SQL);

        if (Schema::hasColumn('rule_violations', 'rule_id')) {
            Schema::table('rule_violations', function (Blueprint $table): void {
                $table->dropUnique('rule_violations_report_id_rule_id_unique');
                $table->dropForeign(['rule_id']);
                $table->dropColumn('rule_id');
            });
        }

        if (Schema::hasColumn('hotspots', 'rule_id')) {
            Schema::table('hotspots', function (Blueprint $table): void {
                $table->dropForeign(['rule_id']);
                $table->dropColumn('rule_id');
            });
        }

        if (Schema::hasTable('segment_rules')) {
            Schema::drop('segment_rules');
        }
    }

    public function down(): void
    {
        Schema::create('segment_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('rule_name');
            $table->string('rule_type', 100);
            $table->decimal('latitude_start', 10, 7)->nullable();
            $table->decimal('longitude_start', 10, 7)->nullable();
            $table->decimal('latitude_end', 10, 7)->nullable();
            $table->decimal('longitude_end', 10, 7)->nullable();
            $table->string('location_name')->nullable();
            $table->string('rule_value')->nullable();
            $table->text('description')->nullable();
            $table->dateTime('effective_from')->nullable();
            $table->dateTime('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('segment_id')->nullable()->constrained('road_segments')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('officers')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('hotspots', function (Blueprint $table): void {
            $table->foreignId('rule_id')->nullable()->after('severity')->constrained('segment_rules')->nullOnDelete();
        });

        Schema::table('rule_violations', function (Blueprint $table): void {
            $table->foreignId('rule_id')->nullable()->after('report_id')->constrained('segment_rules')->cascadeOnDelete();
        });

        DB::statement(<<<'SQL'
            UPDATE rule_violations
            SET rule_id = NULL
        SQL);

        Schema::table('rule_violations', function (Blueprint $table): void {
            $table->dropIndex('rule_violations_segment_rule_idx');
            $table->dropForeign(['segment_type_rule_id']);
            $table->dropForeign(['segment_id']);
            $table->dropColumn([
                'segment_id',
                'segment_type_rule_id',
                'rule_name_snapshot',
                'rule_type_snapshot',
                'rule_value_snapshot',
                'rule_description_snapshot',
            ]);
            $table->unique(['report_id', 'rule_id']);
        });

        Schema::table('hotspots', function (Blueprint $table): void {
            $table->dropForeign(['segment_type_rule_id']);
            $table->dropForeign(['segment_id']);
            $table->dropColumn(['segment_id', 'segment_type_rule_id']);
        });

        Schema::table('segment_type_rules', function (Blueprint $table): void {
            $table->dropUnique('segment_type_rules_unique_key');
        });

        Schema::dropIfExists('segment_rule_overrides');
    }

    private function hasIndex(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne(
            'SELECT COUNT(1) AS aggregate FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        );

        return (int) ($result->aggregate ?? 0) > 0;
    }
};
