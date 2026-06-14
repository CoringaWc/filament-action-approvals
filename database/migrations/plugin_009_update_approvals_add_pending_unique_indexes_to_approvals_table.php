<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approvals') || ! Schema::hasColumn('approvals', 'action_key')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("create unique index if not exists approvals_pending_action_key_unique on approvals (approvable_type, approvable_id, action_key) where status = 'pending' and action_key is not null");
            DB::statement("create unique index if not exists approvals_pending_generic_unique on approvals (approvable_type, approvable_id) where status = 'pending' and action_key is null");

            return;
        }

        Schema::table('approvals', function (Blueprint $table): void {
            $table->index(['approvable_type', 'approvable_id', 'action_key'], 'approvals_pending_action_key_unique');
            $table->index(['approvable_type', 'approvable_id'], 'approvals_pending_generic_unique');
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('drop index if exists approvals_pending_action_key_unique');
            DB::statement('drop index if exists approvals_pending_generic_unique');

            return;
        }

        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropIndex('approvals_pending_action_key_unique');
            $table->dropIndex('approvals_pending_generic_unique');
        });
    }
};
