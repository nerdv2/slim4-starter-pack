<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DefaultMigration extends AbstractMigration
{

    public function change(): void
    {
        // create the table
        $table = $this->table('customer');
        $table->addColumn('name', 'string')
              ->addColumn('created', 'datetime')
              ->create();
    }
}
