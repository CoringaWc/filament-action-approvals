<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use CoringaWc\FilamentActionApprovals\Support\UserModelKey;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_delegations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('approval_step_instance_id')->constrained()->cascadeOnDelete();
            UserModelKey::addColumn($table, 'from_user_id');
            UserModelKey::addColumn($table, 'to_user_id');
            $table->text('reason')->nullable();
            $table->timestamp('delegated_at');
            $table->timestamps();

            $table->unique(['approval_step_instance_id', 'from_user_id'], 'delegation_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_delegations');
    }
};
