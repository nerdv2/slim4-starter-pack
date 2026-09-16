<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class RenameCustomerCreatedColumn extends AbstractMigration
{
    public function change(): void
    {
        $this->table('customer')
            ->renameColumn('created', 'created_at')
            ->update();
    }
}
