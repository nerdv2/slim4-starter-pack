<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateRefreshTokenTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('refresh_token')
            ->addColumn('user_id', 'integer')
            ->addColumn('family_id', 'string', ['limit' => 64])
            ->addColumn('token_hash', 'string', ['limit' => 64])
            ->addColumn('expires_at', 'datetime')
            ->addColumn('revoked_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('user_agent', 'string', ['limit' => 255, 'null' => true, 'default' => null])
            ->addColumn('ip_address', 'string', ['limit' => 45, 'null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['token_hash'], ['unique' => true, 'name' => 'uq_refresh_token_hash'])
            ->addIndex(['user_id', 'revoked_at'], ['name' => 'idx_refresh_token_user'])
            ->addIndex(['family_id'], ['name' => 'idx_refresh_token_family'])
            ->addIndex(['expires_at'], ['name' => 'idx_refresh_token_expires'])
            ->create();
    }
}
