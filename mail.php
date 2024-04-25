<?php

require_once 'cors_config.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;

$input_data = file_get_contents("php://input");
$data = json_decode($input_data, true);

if (isset($data['action']) && $data['action'] == 'mail')
{
    $mail = new PHPMailer;

    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'shop.hellenicgrocery@gmail.com';
    $mail->Password = 'iswi mopr frxq syvq';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    
    $mail->setFrom('shop.hellenicgrocery@gmail.com', 'Hellenic Grocery');
    $mail->addAddress('jack.sam.benning@gmail.com', 'Jack Benning');
    $mail->addReplyTo('shop.hellenicgrocery@gmail.com', 'Hellenic Grocery');
    
    $mail->isHTML(true);

    if ($data['mail_type'] == 'order') {
        $mail->Subject = $data['subject'];
        $mail->Body    = $data['email_HTML'];
        $mail->AltBody = 'This is the body in plain text for non-HTML mail clients';
    }
    
    
    if(!$mail->send()) {
        echo 'Message could not be sent.';
        echo 'Mailer Error: ' . $mail->ErrorInfo;
    } else {
        echo 'Message has been sent';
    }
}
