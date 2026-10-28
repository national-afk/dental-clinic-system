<?php
/**
 * Run this ONCE from the command line to create your first login:
 *   php setup/create_admin.php
 *
 * It asks for a name, username and password, hashes the password
 * properly with PHP's password_hash(), and inserts it into the
 * `staff` table in Supabase. Delete this file afterwards if you like.
 */
require_once __DIR__ . '/../config/config.php';

function ask(string $prompt): string
{
    echo $prompt;
    return trim(fgets(STDIN));
}

echo "=== Smile Care — create first admin user ===\n";
$name = ask("Full name: ");
$username = ask("Username: ");
$password = ask("Password: ");

$hash = password_hash($password, PASSWORD_BCRYPT);

$result = Supabase::insert('staff', [
    'full_name' => $name,
    'username' => $username,
    'password_hash' => $hash,
    'role' => 'superior',
    'active' => true,
]);

if ($result) {
    echo "\n✅ Superior (top-level) user created. You can now log in at login.php with:\n";
    echo "   Username: $username\n";
} else {
    echo "\n❌ Something went wrong. Check SUPABASE_URL/SUPABASE_KEY in config/config.php\n";
    echo "   and that you ran supabase/schema.sql first.\n";
}
