<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = (bool) config('permission.teams', false);
        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names', []);
        /** @var array<string, string> $columnNames */
        $columnNames = config('permission.column_names', []);
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        if ($tableNames === []) {
            throw new RuntimeException('Permission table names are not configured.');
        }

        Schema::create($tableNames['permissions'], function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name'], 'permissions_name_guard_name_unique');
        });

        Schema::create($tableNames['roles'], function (Blueprint $table) use ($teams, $columnNames): void {
            $table->bigIncrements('id');

            if ($teams) {
                $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

                $table->unsignedBigInteger($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            }

            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            if ($teams) {
                $table->unique(
                    [$columnNames['team_foreign_key'] ?? 'team_id', 'name', 'guard_name'],
                    'roles_team_name_guard_unique',
                );

                return;
            }

            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });

        Schema::create($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames, $pivotPermission, $tableNames, $teams): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key'] ?? 'model_id');
            $table->index(
                [$columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_permissions_model_id_model_type_index',
            );

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            if ($teams) {
                $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');
                $table->primary(
                    [$teamForeignKey, $pivotPermission, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                    'model_has_permissions_permission_model_type_primary',
                );

                return;
            }

            $table->primary(
                [$pivotPermission, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_permissions_permission_model_type_primary',
            );
        });

        Schema::create($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames, $pivotRole, $tableNames, $teams): void {
            $table->unsignedBigInteger($pivotRole);
            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key'] ?? 'model_id');
            $table->index(
                [$columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_roles_model_id_model_type_index',
            );

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            if ($teams) {
                $teamForeignKey = $columnNames['team_foreign_key'] ?? 'team_id';

                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');
                $table->primary(
                    [$teamForeignKey, $pivotRole, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                    'model_has_roles_role_model_type_primary',
                );

                return;
            }

            $table->primary(
                [$pivotRole, $columnNames['model_morph_key'] ?? 'model_id', 'model_type'],
                'model_has_roles_role_model_type_primary',
            );
        });

        Schema::create($tableNames['role_has_permissions'], function (Blueprint $table) use ($pivotPermission, $pivotRole, $tableNames): void {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });
    }

    public function down(): void
    {
        /** @var array<string, string> $tableNames */
        $tableNames = config('permission.table_names', []);

        Schema::dropIfExists($tableNames['role_has_permissions'] ?? 'role_has_permissions');
        Schema::dropIfExists($tableNames['model_has_roles'] ?? 'model_has_roles');
        Schema::dropIfExists($tableNames['model_has_permissions'] ?? 'model_has_permissions');
        Schema::dropIfExists($tableNames['roles'] ?? 'roles');
        Schema::dropIfExists($tableNames['permissions'] ?? 'permissions');
    }
};
