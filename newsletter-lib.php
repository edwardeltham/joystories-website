<?php
// Shared by newsletter.php and joy-calendar.php: spam checks, saving sign-ups and the
// optional mailing service. Settings live in newsletter-config.php one folder ABOVE
// public_html (so keys never sit in the website or in GitHub), for example:
//   <?php return ['provider' => 'brevo', 'brevo_key' => 'xkeysib-...', 'brevo_list' => 3];
//   <?php return ['provider' => 'mailchimp', 'mailchimp_key' => 'abc...-us21', 'mailchimp_list' => 'a1b2c3'];
// Optional: 'brevo_calendar_list' => 4 puts Joy Calendar downloads on their own Brevo list.
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) { http_response_code(404); exit; }

const NL_TO = 'edward@joystories.co';
const NL_FROM = 'website@joystories.co'; // must be on the joystories.co domain so mail isn't flagged as spam

function nl_done($ok, $code = 200, $extra = []) { http_response_code($code); echo json_encode(['ok' => $ok] + $extra); exit; }

function nl_config() {
  $f = dirname(__DIR__) . '/newsletter-config.php';
  $cfg = is_readable($f) ? (include $f) : [];
  return is_array($cfg) ? $cfg : [];
}

// Keep the list outside public_html when possible, so it can't be downloaded
function nl_data_dir() {
  $dir = dirname(__DIR__) . '/joystories-data';
  if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
    $dir = __DIR__ . '/newsletter-data';
    if (!is_dir($dir)) { @mkdir($dir, 0700, true); @file_put_contents("$dir/.htaccess", "Require all denied\n"); }
  }
  return $dir;
}

// Checks a form post and returns [email, source]; stops early for bots and bad input
function nl_read_post($bot_reply = []) {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') nl_done(false, 405);
  // Spam checks: bots fill the hidden field, or submit faster than a person can type
  if (!empty($_POST['website'])) nl_done(true, 200, $bot_reply);
  $t = (int)($_POST['t'] ?? 0);
  if ($t <= 0 || time() - $t < 3 || time() - $t > 86400) nl_done(false, 400);
  $email = strtolower(trim(mb_substr($_POST['email'] ?? '', 0, 200)));
  if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/["\\,\s]/', $email)) nl_done(false, 400);
  $source = preg_replace('/[^a-z0-9-]/', '', strtolower($_POST['source'] ?? '')) ?: 'site';
  return [$email, $source];
}

// Rate limit: at most $max sign-ups per visitor per hour
function nl_rate_limit($dir, $max = 5) {
  $rl = "$dir/rate-" . md5($_SERVER['REMOTE_ADDR'] ?? '') . '.txt';
  $hits = array_filter(array_map('intval', @file($rl) ?: []), function ($h) { return $h > time() - 3600; });
  if (count($hits) >= $max) nl_done(false, 429);
  $hits[] = time();
  @file_put_contents($rl, implode("\n", $hits));
}

// Adds a row to newsletter.csv. Returns 'new', 'existing' or 'failed'.
// $per_source: count someone as new again when they sign up from a different place
function nl_save($dir, $email, $source, $per_source = false) {
  $csv = "$dir/newsletter.csv";
  $existing = @file_get_contents($csv) ?: '';
  // Email and source are already validated, so neither can contain a quote or comma
  $needle = $per_source ? "\"$email\",\"$source\"" : "\"$email\"";
  if (strpos($existing, $needle) !== false) return 'existing';
  $fh = @fopen($csv, 'a');
  if (!$fh) return 'failed';
  if ($existing === '') fwrite($fh, "\"date\",\"email\",\"source\"\n");
  fwrite($fh, '"' . gmdate('Y-m-d H:i:s') . "\",\"$email\",\"$source\"\n");
  fclose($fh);
  return 'new';
}

function nl_post_json($url, $headers, $data) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8,
    CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
    CURLOPT_POSTFIELDS => json_encode($data)]);
  curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
  return (int)$code;
}

// Optional mailing service. Returns a line for Edward's email, or '' when none is set up.
function nl_sync($cfg, $email, $brevo_list_key = 'brevo_list') {
  $provider = $cfg['provider'] ?? '';
  if ($provider === 'brevo' && !empty($cfg['brevo_key'])) {
    $list = (int)($cfg[$brevo_list_key] ?? $cfg['brevo_list'] ?? 0);
    $code = nl_post_json('https://api.brevo.com/v3/contacts', ['api-key: ' . $cfg['brevo_key']],
      ['email' => $email, 'listIds' => [$list], 'updateEnabled' => true]);
    return "Brevo: HTTP $code";
  }
  if ($provider === 'mailchimp' && !empty($cfg['mailchimp_key'])) {
    $dc = substr(strrchr($cfg['mailchimp_key'], '-'), 1);
    $code = nl_post_json("https://$dc.api.mailchimp.com/3.0/lists/{$cfg['mailchimp_list']}/members",
      ['Authorization: Basic ' . base64_encode('joy:' . $cfg['mailchimp_key'])],
      ['email_address' => $email, 'status' => 'pending']); // pending = Mailchimp sends a confirm email
    return "Mailchimp: HTTP $code";
  }
  return '';
}

function nl_mail($to, $subject, $body, $content_type = 'text/plain; charset=UTF-8') {
  $headers = "From: Joy Stories <" . NL_FROM . ">\r\nReply-To: Edward at Joy Stories <" . NL_TO . ">\r\n" .
    "MIME-Version: 1.0\r\nContent-Type: $content_type\r\n";
  return mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, '-f' . NL_FROM);
}

// Email to a visitor, as HTML with a plain-text twin (Gmail trusts that more than HTML alone).
// Goes through Brevo when a key is set, since Brevo signs its mail; falls back to the server's mail().
function nl_send_visitor($cfg, $to, $subject, $html, $text) {
  if (!empty($cfg['brevo_key'])) {
    $code = nl_post_json('https://api.brevo.com/v3/smtp/email', ['api-key: ' . $cfg['brevo_key']], [
      'sender' => ['name' => 'Joy Stories', 'email' => NL_FROM], 'replyTo' => ['email' => NL_TO, 'name' => 'Edward at Joy Stories'],
      'to' => [['email' => $to]], 'subject' => $subject, 'htmlContent' => $html, 'textContent' => $text]);
    if ($code >= 200 && $code < 300) return 'brevo';
  }
  $b = 'joy' . bin2hex(random_bytes(8));
  $body = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text)) .
    "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$b--\r\n";
  return nl_mail($to, $subject, $body, "multipart/alternative; boundary=\"$b\"") ? 'mail' : '';
}
