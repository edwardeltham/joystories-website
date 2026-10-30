<?php
// Newsletter sign-up handler for Hostinger.
// Every sign-up is saved to a CSV and emailed to Joy Stories. To also add people to a
// mailing service, see the settings note at the top of newsletter-lib.php.
header('Content-Type: application/json');
require __DIR__ . '/newsletter-lib.php';

[$email, $source] = nl_read_post();
$dir = nl_data_dir();
nl_rate_limit($dir);

// Already signed up: say yes again, without another email
$saved = nl_save($dir, $email, $source);
if ($saved === 'existing') nl_done(true);

$cfg = nl_config();
$synced = nl_sync($cfg, $email);

$body = "Email: $email\nSigned up from: $source\nTime (UTC): " . gmdate('d M Y, H:i') . "\n" . ($synced ? "$synced\n" : '');
$mailed = nl_notify($cfg, 'New Joy Letter sign-up: ' . $email, $body) !== '';

$ok = $saved === 'new' || $mailed;
nl_done($ok, $ok ? 200 : 500);
