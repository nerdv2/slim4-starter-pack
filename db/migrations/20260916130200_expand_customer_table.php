<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class ExpandCustomerTable extends AbstractMigration
{
    public function change(): void
    {
        $this->table('customer')
            ->addColumn('email', 'string', ['limit' => 190, 'null' => true, 'default' => null, 'after' => 'name'])
            ->addColumn('phone', 'string', ['limit' => 40, 'null' => true, 'default' => null, 'after' => 'email'])
            ->addColumn('company', 'string', ['limit' => 150, 'null' => true, 'default' => null, 'after' => 'phone'])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'lead', 'after' => 'company'])
            ->addColumn('address', 'text', ['null' => true, 'default' => null, 'after' => 'status'])
            ->addColumn('notes', 'text', ['null' => true, 'default' => null, 'after' => 'address'])
            ->addColumn('avatar_path', 'string', ['limit' => 255, 'null' => true, 'default' => null, 'after' => 'notes'])
            ->addColumn('updated_at', 'datetime', ['null' => true, 'default' => null, 'after' => 'avatar_path'])
            ->addIndex(['name'], ['name' => 'idx_customer_name'])
            ->addIndex(['email'], ['name' => 'idx_customer_email'])
            ->addIndex(['status'], ['name' => 'idx_customer_status'])
            ->update();
    }
}
