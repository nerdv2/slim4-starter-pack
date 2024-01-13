<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class DefaultMigration extends AbstractMigration
{

    public function change(): void
    {
        // create the table
        $table = $this->table('user_logins');
        $table->addColumn('user_id', 'integer')
              ->addColumn('created', 'datetime')
              ->create();
    }
}
