<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * php artisan uat:seed-users
 *
 * Creates (or resets the password of) the fixed set of UAT test
 * accounts. Idempotent -- safe to run multiple times, e.g. after every
 * migrate:fresh during UAT setup.
 *
 * Seeds:
 *   - admin@example.com      / admin1234      (HR staff, default 'web' guard, User model)
 *   - records@example.com    / records1234    (HR staff, default 'web' guard, User model --
 *                                              there's no separate "records" role yet, so
 *                                              this is just another staff account for UAT)
 *   - superadmin@example.com / superadmin1234 ('superadmin' guard, SuperAdmin model)
 */
class SeedUatUsers extends Command
{
    protected $signature = 'uat:seed-users';

    protected $description = 'Create or reset the fixed set of UAT test accounts (admin, records, superadmin)';

    /**
     * @var array<int, array{email: string, password: string, name: string}>
     */
    private array $staffAccounts = [
        ['email' => 'admin@example.com',   'password' => 'admin1234',   'name' => 'Admin (UAT)'],
        ['email' => 'records@example.com', 'password' => 'records1234', 'name' => 'Records (UAT)'],
    ];

    private array $superAdminAccount = [
        'email' => 'superadmin@example.com',
        'password' => 'superadmin1234',
        'name' => 'Super Admin (UAT)',
    ];

    public function handle(): int
    {
        foreach ($this->staffAccounts as $account) {
            $user = User::where('email', $account['email'])->first();

            if ($user) {
                $user->password = Hash::make($account['password']);
                $user->save();
                $this->info("[OK] {$account['email']} already existed — password reset.");
            } else {
                User::create([
                    'name'     => $account['name'],
                    'email'    => $account['email'],
                    'password' => Hash::make($account['password']),
                ]);
                $this->info("[OK] {$account['email']} created.");
            }
        }

        $superAdmin = SuperAdmin::where('email', $this->superAdminAccount['email'])->first();

        if ($superAdmin) {
            $superAdmin->password = Hash::make($this->superAdminAccount['password']);
            $superAdmin->save();
            $this->info("[OK] {$this->superAdminAccount['email']} already existed — password reset.");
        } else {
            SuperAdmin::create([
                'name'     => $this->superAdminAccount['name'],
                'email'    => $this->superAdminAccount['email'],
                'password' => Hash::make($this->superAdminAccount['password']),
            ]);
            $this->info("[OK] {$this->superAdminAccount['email']} created.");
        }

        $this->newLine();
        $this->table(
            ['Role', 'Email', 'Password'],
            [
                ['admin',       'admin@example.com',       'admin1234'],
                ['records',     'records@example.com',     'records1234'],
                ['superadmin',  'superadmin@example.com',  'superadmin1234'],
            ]
        );

        return self::SUCCESS;
    }
}
