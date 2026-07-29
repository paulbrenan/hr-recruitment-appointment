<?php
/**
 * create_admin_user.php
 *
 * One-off script (not a patch_*.php — doesn't touch app code) that
 * creates (or resets the password of) an HR staff account in the
 * `users` table, using the same guard/model the AuthController login
 * authenticates against.
 *
 * IMPORTANT: change the email/password below before running, then
 * delete this file afterwards (or at least change the password once
 * you're logged in) -- it contains a plaintext password.
 *
 * Usage: php create_admin_user.php
 * Run from your Laravel project root (same folder as artisan).
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

// ── Edit these before running ───────────────────────────────────────────
$email    = 'admin@example.com';
$password = 'admin123';
$name     = 'HR Staff';
// ─────────────────────────────────────────────────────────────────────────

$user = User::where('email', $email)->first();

if ($user) {
    $user->password = \Illuminate\Support\Facades\Hash::make($password);
    $user->save();
    echo "[OK] Existing user found -- password reset for {$email}\n";
} else {
    User::create([
        'name'     => $name,
        'email'    => $email,
        'password' => \Illuminate\Support\Facades\Hash::make($password),
    ]);
    echo "[OK] Created new user {$email}\n";
}

echo "\nLogin with:\n";
echo "  email:    {$email}\n";
echo "  password: {$password}\n";
echo "\nChange the password after logging in, and delete this script.\n";
