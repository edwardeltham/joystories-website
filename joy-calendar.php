<?php
// The Joy Calendar download, for Hostinger.
// POST an email: we save it with the newsletter sign-ups, email the visitor their own
// download link, tell Edward, and hand the link back so the page can offer it straight away.
// GET with a signed link (?e=...&k=...): streams the PDF from downloads/, which the web
// can't reach directly, so the calendar is only ever given in exchange for an email.
require __DIR__ . '/newsletter-lib.php';

const JC_PDF = __DIR__ . '/downloads/the-joy-calendar.pdf';
const JC_NAME = 'The Joy Calendar by Joy Stories.pdf';

// A secret made once on the server; links signed with it can't be guessed
function jc_secret($cfg) {
  if (!empty($cfg['download_secret'])) return $cfg['download_secret'];
  $f = nl_data_dir() . '/download-secret.txt';
  $s = trim(@file_get_contents($f) ?: '');
  if (strlen($s) < 32) { $s = bin2hex(random_bytes(32)); @file_put_contents($f, $s); }
  return $s;
}
function jc_b64($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function jc_sign($email, $secret) { return substr(hash_hmac('sha256', "joy-calendar|$email", $secret), 0, 32); }

$cfg = nl_config();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $email = base64_decode(strtr($_GET['e'] ?? '', '-_', '+/'));
  if (!$email || !hash_equals(jc_sign($email, jc_secret($cfg)), (string)($_GET['k'] ?? '')) || !is_readable(JC_PDF)) {
    header('Location: /employee-engagement.html#free-joy-calendar', true, 302);
    exit;
  }
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . JC_NAME . '"');
  header('Content-Length: ' . filesize(JC_PDF));
  header('X-Robots-Tag: noindex');
  header('Cache-Control: private, max-age=86400');
  readfile(JC_PDF);
  exit;
}

header('Content-Type: application/json');
[$email, $source] = nl_read_post(['url' => '#']);
$dir = nl_data_dir();
nl_rate_limit($dir);

$url = 'https://joystories.co/joy-calendar.php?e=' . jc_b64($email) . '&k=' . jc_sign($email, jc_secret($cfg));

// Saved once per person; asking again just gets the link again
$saved = nl_save($dir, $email, 'joy-calendar', true);

// The visitor's copy, so the calendar is in their inbox for later
$html = '<div style="font-family:Arial,Helvetica,sans-serif;max-width:520px;margin:0 auto;color:#0a0a0a;line-height:1.55">' .
  '<div style="height:6px;background:linear-gradient(90deg,#ffd400,#ff7a00,#ff3d7f,#2f6bff,#00c48c)"></div>' .
  '<h1 style="font-family:Georgia,serif;font-weight:400;font-size:30px;margin:28px 0 8px">Your Joy Calendar is here.</h1>' .
  '<p>42 lesser-known days worth celebrating at work, each with one simple 7 to 12 minute activity that anyone on your team can run.</p>' .
  '<p style="margin:28px 0"><a href="' . htmlspecialchars($url) . '" style="background:#0a0a0a;color:#fff;text-decoration:none;padding:14px 26px;font-weight:bold;letter-spacing:.1em;text-transform:uppercase;font-size:13px">Download the calendar</a></p>' .
  '<p>A tip to start: pick one day that lands in a quiet week, read the Method once, and say "The Learning" out loud at the end. That\'s it.</p>' .
  '<p>Want the whole year planned and run for your team? Just reply to this email.</p>' .
  '<p>Edward<br>Joy Stories &middot; <a href="https://joystories.co" style="color:#0a0a0a">joystories.co</a> &middot; +91 98402 54762</p>' .
  '<p style="font-size:12px;color:#777;margin-top:28px">You asked for the Joy Calendar on joystories.co. We\'ll also send you the Joy Letter, one short email a month. Reply "unsubscribe" anytime.</p></div>';
$text = "Your Joy Calendar is here.\n\n42 lesser-known days worth celebrating at work, each with one simple 7 to 12 minute activity that anyone on your team can run.\n\nDownload it here:\n$url\n\n" .
  "A tip to start: pick one day that lands in a quiet week, read the Method once, and say \"The Learning\" out loud at the end.\n\n" .
  "Want the whole year planned and run for your team? Just reply to this email.\n\nEdward\nJoy Stories | joystories.co | +91 98402 54762\n\n" .
  "You asked for the Joy Calendar on joystories.co. We'll also send you the Joy Letter, one short email a month. Reply \"unsubscribe\" anytime.\n";
$via = nl_send($cfg, $email, 'Your Joy Calendar from Joy Stories', $html, $text);
$sent = $via !== '';

if ($saved === 'new') {
  $synced = nl_sync($cfg, $email, 'brevo_calendar_list');
  $body = "Email: $email\nDownloaded: The Joy Calendar (from $source)\nTime (UTC): " . gmdate('d M Y, H:i') . "\n" .
    'Link emailed to them: ' . ($sent ? "yes, via $via" : 'NO, sending failed') . "\n" . ($synced ? "$synced\n" : '');
  nl_notify($cfg, 'Joy Calendar download: ' . $email, $body);
}

nl_done(true, 200, ['url' => $url, 'emailed' => $sent]);
