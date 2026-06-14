<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('approvals') || ! Schema::hasColumn('approvals', 'action_key')) {
            return;
        }

        DB::table('approvals')
            ->select(['id', 'approval_flow_id', 'metadata'])
            ->whereNull('action_key')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $approval): void {
                $actionKey = $this->metadataActionKey($approval->metadata)
                    ?? $this->submittedActionKey((int) $approval->id)
                    ?? $this->flowActionKey((int) $approval->approval_flow_id);

                if ($actionKey === null) {
                    return;
                }

                DB::table('approvals')
                    ->where('id', $approval->id)
                    ->update(['action_key' => $actionKey]);
            });
    }

    public function down(): void
    {
        // Backfilled values are intentionally preserved; the column migration owns removal.
    }

    private function submittedActionKey(int $approvalId): ?string
    {
        $metadata = DB::table('approval_actions')
            ->where('approval_id', $approvalId)
            ->where('type', 'submitted')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('metadata');

        return $this->metadataActionKey($metadata);
    }

    private function flowActionKey(int $approvalFlowId): ?string
    {
        $actionKey = DB::table('approval_flows')
            ->where('id', $approvalFlowId)
            ->value('action_key');

        return is_string($actionKey) && filled($actionKey) ? $actionKey : null;
    }

    private function metadataActionKey(mixed $metadata): ?string
    {
        if (is_string($metadata)) {
            $metadata = json_decode($metadata, true);
        }

        if (! is_array($metadata)) {
            return null;
        }

        $actionKey = data_get($metadata, 'action_key');

        return is_string($actionKey) && filled($actionKey) ? $actionKey : null;
    }
};
