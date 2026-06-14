<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approvals') || Schema::hasColumn('approvals', 'action_key')) {
            return;
        }

        Schema::table('approvals', function (Blueprint $table): void {
            $table->string('action_key')->nullable()->index('approvals_action_key_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('approvals') || ! Schema::hasColumn('approvals', 'action_key')) {
            return;
        }

        Schema::table('approvals', function (Blueprint $table): void {
            $table->dropIndex('approvals_action_key_index');
            $table->dropColumn('action_key');
        });
    }
};
