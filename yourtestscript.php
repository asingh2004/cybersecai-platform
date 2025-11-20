<?php
require 'vendor/autoload.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$mail = new PHPMailer(true);
$mail->SMTPDebug = SMTP::DEBUG_SERVER;
$mail->isSMTP();
$mail->Host = 'smtp.gmail.com';
$mail->SMTPAuth = true;
$mail->Username = 'info@cybersecai.io';
$mail->Password = 'ucob tino jayc tfxr'; // With spaces is fine for Google
$mail->SMTPSecure = 'tls';
$mail->Port = 587;
$mail->setFrom('info@cybersecai.io', 'CSAI Test');
$mail->addAddress('asingh2004@gmail.com');
$mail->Subject = 'Test 123';
$mail->Body = 'Test from PHP direct, Success';
$mail->send();
echo "Sent!";