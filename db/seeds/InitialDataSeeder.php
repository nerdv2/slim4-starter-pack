<?php

declare(strict_types=1);

use Phinx\Seed\AbstractSeed;

/**
 * Development data: the bootstrap admin account and a handful of demo customers.
 *
 * Run with `composer run seed`. The seeder is safe to re-run: existing rows are
 * not duplicated.
 */
final class InitialDataSeeder extends AbstractSeed
{
    public const string ADMIN_EMAIL = 'admin@example.com';

    public const string ADMIN_PASSWORD = 'Admin123!';

    public function run(): void
    {
        $now = date('Y-m-d H:i:s');

        $existingAdmin = $this->fetchRow(
            "SELECT id FROM user WHERE email = '" . self::ADMIN_EMAIL . "' LIMIT 1"
        );
        if ($existingAdmin === false) {
            $this->table('user')->insert([
                [
                    'name' => 'Admin',
                    'email' => self::ADMIN_EMAIL,
                    'password_hash' => password_hash(self::ADMIN_PASSWORD, PASSWORD_DEFAULT),
                    'type' => 'admin',
                    'last_login_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ],
            ])->saveData();
        }

        $existingCustomers = $this->fetchRow('SELECT COUNT(*) AS total FROM customer');
        if ($existingCustomers !== false && (int) ($existingCustomers['total'] ?? 0) > 0) {
            return;
        }

        $this->table('customer')->insert([
            [
                'name' => 'Nadia Putri',
                'email' => 'nadia@northwind.example',
                'phone' => '+62 811 2000 100',
                'company' => 'Northwind Traders',
                'status' => 'active',
                'address' => 'Jl. Sudirman 21, Jakarta',
                'notes' => 'Renewal due in Q4.',
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Budi Santoso',
                'email' => 'budi@acme.example',
                'phone' => '+62 812 3000 200',
                'company' => 'Acme Manufacturing',
                'status' => 'active',
                'address' => 'Jl. Gatot Subroto 5, Bandung',
                'notes' => null,
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Clara Wijaya',
                'email' => 'clara@lumen.example',
                'phone' => '+62 813 4000 300',
                'company' => 'Lumen Studio',
                'status' => 'prospect',
                'address' => null,
                'notes' => 'Requested a quote for 30 seats.',
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Dimas Pratama',
                'email' => 'dimas@orbit.example',
                'phone' => null,
                'company' => 'Orbit Logistics',
                'status' => 'lead',
                'address' => null,
                'notes' => 'Met at the trade show.',
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Eka Lestari',
                'email' => 'eka@brightpath.example',
                'phone' => '+62 815 5000 400',
                'company' => 'Brightpath Consulting',
                'status' => 'inactive',
                'address' => 'Jl. Diponegoro 8, Surabaya',
                'notes' => 'Paused the subscription last quarter.',
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
            [
                'name' => 'Fajar Nugroho',
                'email' => 'fajar@stonebridge.example',
                'phone' => '+62 816 6000 500',
                'company' => 'Stonebridge Finance',
                'status' => 'prospect',
                'address' => null,
                'notes' => null,
                'avatar_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ],
        ])->saveData();
    }
}
