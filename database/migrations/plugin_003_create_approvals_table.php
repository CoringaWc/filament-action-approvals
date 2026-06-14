<?php

use CoringaWc\FilamentActionApprovals\Support\UserModelKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approvals')) {
            return;
        }

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');
            $table->string('status')->default('pending');
            UserModelKey::addColumn($table, 'submitted_by', nullable: true);
            $table->string('submitted_by_type')->nullable();
            UserModelKey::addColumn($table, 'submitted_by_id', nullable: true);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status']);
            $table->index(['submitted_by']);
            $table->index(['submitted_by_type', 'submitted_by_id'], 'approvals_submitter_morph_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
