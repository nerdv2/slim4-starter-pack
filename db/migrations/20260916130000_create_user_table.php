<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class CreateUserTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('user')
            ->addColumn('name', 'string', ['limit' => 120])
            ->addColumn('email', 'string', ['limit' => 190])
            ->addColumn('password_hash', 'string', ['limit' => 255])
            ->addColumn('type', 'string', ['limit' => 20, 'default' => 'staff'])
            ->addColumn('last_login_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => null])
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null])
            ->addIndex(['email'], ['unique' => true, 'name' => 'uq_user_email'])
            ->addIndex(['type'], ['name' => 'idx_user_type'])
            ->create();
    }
}
