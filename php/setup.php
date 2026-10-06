<?php
// Sets the admin PIN. Run once on the server:  php setup.php
// The PIN is stored only as a hash in app/config.php (never committed).
if (PHP_SAPI !== 'cli') exit("Run this from the command line.\n");
$file = __DIR__ . '/app/config.php';
$existing = is_file($file) ? (require $file) : [];
fwrite(STDOUT, "Choose an admin PIN (at least 6 characters; digits are fine): ");
$pin = trim((string)fgets(STDIN));
if (strlen($pin) < 6) exit("Too short. Nothing changed.\n");
fwrite(STDOUT, "Repeat it: ");
if (trim((string)fgets(STDIN)) !== $pin) exit("They do not match. Nothing changed.\n");
$existing['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
$existing += ['site_name' => 'overraide'];
file_put_contents($file, "<?php\nreturn " . var_export($existing, true) . ";\n", LOCK_EX);
echo "Saved to app/config.php\n";
