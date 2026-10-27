<?php
// Contact form handler for Hostinger: emails each enquiry to Joy Stories.
header('Content-Type: application/json');
$TO = 'edward@joystories.co';
$FROM = 'website@joystories.co'; // must be on the joystories.co domain so mail isn't flagged as spam

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo '{"ok":false}'; exit; }
if (!empty($_POST['website'])) { echo '{"ok":true}'; exit; } // honeypot: bots fill the hidden field

function field($k, $max = 200) { return trim(str_replace(["\r", "\n"], ' ', mb_substr($_POST[$k] ?? '', 0, $max))); }
$name = field('name'); $email = field('email'); $company = field('company');
$size = field('size'); $interest = field('interest');
$message = trim(mb_substr($_POST['message'] ?? '', 0, 5000));
if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { http_response_code(400); echo '{"ok":false}'; exit; }

$subject = 'Website enquiry from ' . $company;
$body = "Name: $name\nEmail: $email\nCompany: $company\nTeam size: $size\nInterested in: $interest\n\n$message\n";
$headers = "From: Joy Stories Website <$FROM>\r\nReply-To: $name <$email>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
$ok = mail($TO, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers, "-f$FROM");
http_response_code($ok ? 200 : 500);
echo json_encode(['ok' => $ok]);
