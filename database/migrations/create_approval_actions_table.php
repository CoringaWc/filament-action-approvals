<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use CoringaWc\FilamentActionApprovals\Support\UserModelKey;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->foreignId('approval_step_instance_id')->nullable()->constrained()->cascadeOnDelete();
            UserModelKey::addColumn($table, 'user_id', nullable: true);
            $table->string('type');
            $table->text('comment')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['approval_id']);
            $table->index(['approval_step_instance_id']);
            $table->index(['user_id']);
            $table->index(['approval_step_instance_id', 'user_id', 'type'], 'approval_actions_step_user_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
    }
};
