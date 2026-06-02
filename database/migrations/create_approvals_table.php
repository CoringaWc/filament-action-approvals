<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userTable = config('filament-action-approvals.user_table', 'users');

        Schema::create('approvals', function (Blueprint $table) use ($userTable) {
            $table->id();
            $table->foreignId('approval_flow_id')->constrained()->cascadeOnDelete();
            $table->morphs('approvable');
            $table->string('status')->default('pending');
            $table->foreignId('submitted_by')->nullable()->constrained($userTable)->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
