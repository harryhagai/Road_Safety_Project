<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Columns that historically referenced the officers table.
     *
     * @var array<string, string>
     */
    private array $officerReferences = [
        'reports' => 'officer_id',
        'road_segments' => 'created_by',
        'rule_violations' => 'verified_by',
        'contact_messages' => 'officer_id',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('role', 30)->default('passenger')->after('email')->index();
            });
        }

        // Every account stored in users before this migration was registered as a driver.
        DB::table('users')->update(['role' => 'driver']);

        if (Schema::hasTable('officers')) {
            $duplicateEmail = DB::table('officers')
                ->join('users', DB::raw('LOWER(users.email)'), '=', DB::raw('LOWER(officers.email)'))
                ->value('officers.email');

            if ($duplicateEmail !== null) {
                throw new RuntimeException(
                    "Cannot unify accounts because {$duplicateEmail} exists in both users and officers."
                );
            }

            $this->dropOfficerForeignKeys();

            DB::table('officers')
                ->orderBy('id')
                ->get()
                ->each(function (object $officer): void {
                    $userId = DB::table('users')->insertGetId([
                        'name' => $officer->full_name,
                        'email' => strtolower(trim($officer->email)),
                        'role' => $this->normalizeOfficerRole($officer->role),
                        'email_verified_at' => null,
                        'vehicle_name' => null,
                        'plate_number' => null,
                        'organization' => null,
                        'last_login_at' => $officer->last_login_at,
                        'password' => $officer->password,
                        'remember_token' => $officer->remember_token,
                        'created_at' => $officer->created_at,
                        'updated_at' => $officer->updated_at,
                    ]);

                    foreach ($this->officerReferences as $table => $column) {
                        if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                            DB::table($table)
                                ->where($column, $officer->id)
                                ->update([$column => $userId]);
                        }
                    }

                    if (Schema::hasTable('audit_trails')) {
                        DB::table('audit_trails')
                            ->where('actor_type', 'App\\Models\\Officer')
                            ->where('actor_id', $officer->id)
                            ->update([
                                'actor_type' => 'App\\Models\\User',
                                'actor_id' => $userId,
                            ]);

                        DB::table('audit_trails')
                            ->where('subject_type', 'App\\Models\\Officer')
                            ->where('subject_id', $officer->id)
                            ->update([
                                'subject_type' => 'App\\Models\\User',
                                'subject_id' => $userId,
                            ]);
                    }
                });

            $this->addUserForeignKeys();
            Schema::drop('officers');
        }

        if (! Schema::hasColumn('reports', 'submitted_by_user_id')) {
            Schema::table('reports', function (Blueprint $table): void {
                $column = $table->foreignId('submitted_by_user_id')
                    ->nullable()
                    ->after('driver_id');

                if ($this->supportsForeignKeys('reports')) {
                    $column->constrained('users')->nullOnDelete();
                } else {
                    $column->index();
                }
            });

            DB::table('reports')
                ->whereNotNull('driver_id')
                ->update(['submitted_by_user_id' => DB::raw('driver_id')]);
        }

        if (Schema::hasTable('sessions')) {
            DB::table('sessions')->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reports', 'submitted_by_user_id')) {
            $this->dropForeignKeyIfExists('reports', 'submitted_by_user_id');

            Schema::table('reports', function (Blueprint $table): void {
                $table->dropColumn('submitted_by_user_id');
            });
        }

        if (! Schema::hasTable('officers')) {
            $this->dropUserForeignKeys();

            Schema::create('officers', function (Blueprint $table): void {
                $table->id();
                $table->string('full_name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('role', 50)->default('officer');
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });

            DB::table('users')
                ->whereIn('role', ['road_officer', 'admin'])
                ->orderBy('id')
                ->get()
                ->each(function (object $user): void {
                    DB::table('officers')->insert([
                        'id' => $user->id,
                        'full_name' => $user->name,
                        'email' => $user->email,
                        'password' => $user->password,
                        'role' => $user->role === 'admin' ? 'admin' : 'officer',
                        'last_login_at' => $user->last_login_at,
                        'remember_token' => $user->remember_token,
                        'created_at' => $user->created_at,
                        'updated_at' => $user->updated_at,
                    ]);
                });

            $this->addOfficerForeignKeys();

            DB::table('users')
                ->whereIn('role', ['road_officer', 'admin'])
                ->delete();
        }

        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(['role']);
                $table->dropColumn('role');
            });
        }
    }

    private function normalizeOfficerRole(?string $role): string
    {
        return in_array(strtolower((string) $role), ['admin', 'hgadmin'], true)
            ? 'admin'
            : 'road_officer';
    }

    private function dropOfficerForeignKeys(): void
    {
        foreach ($this->officerReferences as $table => $column) {
            $this->dropForeignKeyIfExists($table, $column);
        }
    }

    private function dropUserForeignKeys(): void
    {
        foreach ($this->officerReferences as $table => $column) {
            $this->dropForeignKeyIfExists($table, $column);
        }
    }

    private function addUserForeignKeys(): void
    {
        foreach ($this->officerReferences as $table => $column) {
            $this->addForeignKeyIfSupported($table, $column, 'users');
        }
    }

    private function addOfficerForeignKeys(): void
    {
        foreach ($this->officerReferences as $table => $column) {
            $this->addForeignKeyIfSupported($table, $column, 'officers');
        }
    }

    private function dropForeignKeyIfExists(string $table, string $column): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return;
        }

        if (DB::connection()->getDriverName() === 'sqlite') {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });

            return;
        }

        if (DB::connection()->getDriverName() !== 'mysql') {
            Schema::table($table, function (Blueprint $blueprint) use ($column): void {
                $blueprint->dropForeign([$column]);
            });

            return;
        }

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE CONSTRAINT_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table, $column]
        );

        foreach ($constraints as $constraint) {
            $name = str_replace('`', '``', $constraint->CONSTRAINT_NAME);
            $safeTable = str_replace('`', '``', $table);
            DB::statement("ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$name}`");
        }
    }

    private function addForeignKeyIfSupported(string $table, string $column, string $referencedTable): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! $this->supportsForeignKeys($table)
        ) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($column, $referencedTable): void {
            $blueprint->foreign($column)->references('id')->on($referencedTable)->nullOnDelete();
        });
    }

    private function supportsForeignKeys(string $table): bool
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return true;
        }

        $status = DB::selectOne('SHOW TABLE STATUS WHERE Name = ?', [$table]);

        return strcasecmp((string) ($status->Engine ?? ''), 'InnoDB') === 0;
    }
};
