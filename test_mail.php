<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$to = 'lilzimm48@gmail.com'; // CHANGE THIS to an email you can check
$subject = 'Test Email from PHP Mail Function';
$message = 'This is a test email sent from PHP\'s mail() function.';
$headers = 'From: webmaster@yourdomain.com' . "\r\n" .
           'Reply-To: webmaster@yourdomain.com' . "\r\n" .
           'X-Mailer: PHP/' . phpversion();

if (mail($to, $subject, $message, $headers)) {
    echo "Email sent successfully to $to!";
} else {
    echo "Email sending failed. Check server logs for details.";
    // You can get more specific errors if you configure php.ini for mail logging
}
?>