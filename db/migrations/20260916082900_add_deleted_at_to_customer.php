<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddDeletedAtToCustomer extends AbstractMigration
{
    public function change(): void
    {
        $this->table('customer')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'default' => null, 'after' => 'created'])
            ->update();
    }
}
