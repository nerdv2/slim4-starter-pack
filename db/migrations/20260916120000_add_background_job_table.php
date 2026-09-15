<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddBackgroundJobTable extends AbstractMigration
{
    public function up(): void
    {
        $this->table('background_job')
            ->addColumn('queue', 'string', ['limit' => 64, 'null' => false, 'default' => 'default'])
            ->addColumn('type', 'string', ['limit' => 128, 'null' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'null' => false, 'default' => 'pending'])
            ->addColumn('payload', 'text', ['null' => false])
            ->addColumn('result', 'text', ['null' => true])
            ->addColumn('progress', 'integer', ['null' => true])
            ->addColumn('progress_message', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('attempts', 'integer', ['null' => false, 'default' => 0])
            ->addColumn('max_attempts', 'integer', ['null' => false, 'default' => 3])
            ->addColumn('available_at', 'datetime', ['null' => true])
            ->addColumn('started_at', 'datetime', ['null' => true])
            ->addColumn('completed_at', 'datetime', ['null' => true])
            ->addColumn('locked_by', 'string', ['limit' => 128, 'null' => true])
            ->addColumn('locked_at', 'datetime', ['null' => true])
            ->addColumn('lease_token', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('error_message', 'text', ['null' => true])
            ->addColumn('error_trace', 'text', ['null' => true])
            ->addColumn('request_id', 'string', ['limit' => 64, 'null' => true])
            ->addColumn('created_at', 'datetime', ['null' => false])
            ->addColumn('updated_at', 'datetime', ['null' => false])
            ->addIndex(['queue', 'status', 'available_at', 'id'], ['name' => 'idx_background_job_claim'])
            ->addIndex(['status', 'available_at'], ['name' => 'idx_background_job_status_available'])
            ->addIndex(['locked_at'], ['name' => 'idx_background_job_locked_at'])
            ->addIndex(['lease_token'], ['name' => 'idx_background_job_lease_token'])
            ->addIndex(['type'], ['name' => 'idx_background_job_type'])
            ->addIndex(['request_id'], ['name' => 'idx_background_job_request_id'])
            ->create();

        if ($this->getAdapter()?->getAdapterType() === 'sqlite') {
            // Partial unique index: only one active (pending/running) job per request id.
            $this->execute(
                'CREATE UNIQUE INDEX uq_background_job_active_request_id ON background_job (request_id) '
                . "WHERE request_id IS NOT NULL AND status IN ('pending', 'running')"
            );

            return;
        }

        // MySQL/MariaDB generated column: terminal rows are NULL and stay outside the unique index.
        $this->execute(
            'ALTER TABLE background_job ADD COLUMN active_request_id VARCHAR(64) '
            . 'GENERATED ALWAYS AS (CASE WHEN status IN (\'pending\', \'running\') THEN request_id ELSE NULL END) VIRTUAL'
        );
        $this->execute(
            'CREATE UNIQUE INDEX uq_background_job_active_request_id ON background_job (active_request_id)'
        );
    }

    public function down(): void
    {
        $this->table('background_job')->drop()->save();
    }
}
