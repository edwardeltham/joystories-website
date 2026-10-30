<?php
// Newsletter sign-up handler for Hostinger.
// Every sign-up is saved to a CSV and emailed to Joy Stories. To also add people to a
// mailing service, put a newsletter-config.php one folder ABOVE public_html (so keys never
// sit in the website or in GitHub) containing, for example:
//   <?php return ['provider' => 'brevo', 'brevo_key' => 'xkeysib-...', 'brevo_list' => 3];
//   <?php return ['provider' => 'mailchimp', 'mailchimp_key' => 'abc...-us21', 'mailchimp_list' => 'a1b2c3'];
header('Content-Type: application/json');
$TO = 'edward@joystories.co';
$FROM = 'website@joystories.co'; // must be on the joystories.co domain so mail isn't flagged as spam

$cfgFile = dirname(__DIR__) . '/newsletter-config.php';
$cfg = is_readable($cfgFile) ? (include $cfgFile) : [];
if (!is_array($cfg)) $cfg = [];

function done($ok, $code = 200, $extra = []) { http_response_code($code); echo json_encode(['ok' => $ok] + $extra); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') done(false, 405);
// Spam checks: bots fill the hidden field, or submit faster than a person can type
if (!empty($_POST['website'])) done(true);
$t = (int)($_POST['t'] ?? 0);
if ($t <= 0 || time() - $t < 3 || time() - $t > 86400) done(false, 400);

$email = strtolower(trim(mb_substr($_POST['email'] ?? '', 0, 200)));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/["\\,\s]/', $email)) done(false, 400);
$source = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['source'] ?? '')) ?: 'site';

// Keep the list outside public_html when possible, so it can't be downloaded
$dir = dirname(__DIR__) . '/joystories-data';
if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
  $dir = __DIR__ . '/newsletter-data';
  if (!is_dir($dir)) { @mkdir($dir, 0700, true); @file_put_contents("$dir/.htaccess", "Require all denied\n"); }
}
$csv = "$dir/newsletter.csv";

// Rate limit: at most 5 sign-ups per visitor per hour
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$rl = "$dir/rate-" . md5($ip) . '.txt';
$hits = array_filter(array_map('intval', @file($rl) ?: []), function ($h) { return $h > time() - 3600; });
if (count($hits) >= 5) done(false, 429);
$hits[] = time();
@file_put_contents($rl, implode("\n", $hits));

// Already signed up: say yes again, without another email
$existing = @file_get_contents($csv) ?: '';
if (strpos($existing, '"' . $email . '"') !== false) done(true);

// Email and source are already validated, so neither can contain a quote or comma
$fh = @fopen($csv, 'a');
if ($fh) {
  if ($existing === '') fwrite($fh, "\"date\",\"email\",\"source\"\n");
  fwrite($fh, '"' . gmdate('Y-m-d H:i:s') . "\",\"$email\",\"$source\"\n");
  fclose($fh);
}

// Optional mailing service
$synced = '';
function post_json($url, $headers, $data) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
    CURLOPT_POSTFIELDS => json_encode($data)]);
  curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return $code;
}
$provider = $cfg['provider'] ?? '';
if ($provider === 'brevo' && !empty($cfg['brevo_key'])) {
  $code = post_json('https://api.brevo.com/v3/contacts', ['api-key: ' . $cfg['brevo_key']],
    ['email' => $email, 'listIds' => [(int)($cfg['brevo_list'] ?? 0)], 'updateEnabled' => true]);
  $synced = "Brevo: HTTP $code";
} elseif ($provider === 'mailchimp' && !empty($cfg['mailchimp_key'])) {
  $dc = substr(strrchr($cfg['mailchimp_key'], '-'), 1);
  $code = post_json("https://$dc.api.mailchimp.com/3.0/lists/{$cfg['mailchimp_list']}/members",
    ['Authorization: Basic ' . base64_encode('joy:' . $cfg['mailchimp_key'])],
    ['email_address' => $email, 'status' => 'pending']); // pending = Mailchimp sends a confirm email
  $synced = "Mailchimp: HTTP $code";
}

$subject = 'New Joy Letter sign-up: ' . $email;
$body = "Email: $email\nSigned up from: $source\nTime (UTC): " . gmdate('d M Y, H:i') . "\n" . ($synced ? "$synced\n" : '');
$headers = "From: Joy Stories Website <$FROM>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$mailed = mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, "-f$FROM");

done($fh || $mailed, ($fh || $mailed) ? 200 : 500);
