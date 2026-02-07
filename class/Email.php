<?php

class Email {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from_email;
    private $from_name;
    private $use_phpmailer = false;
    
    public function __construct() {
        $this->host = env('MAIL_HOST');
        $this->port = env('MAIL_PORT');
        $this->username = env('MAIL_USER');
        $this->password = env('MAIL_PASS');
        $this->from_email = env('MAIL_FROM', 'info@elektroobchod.online');
        $this->from_name = env('MAIL_FROM_NAME', 'ElektroObchod');
        
        // Check if PHPMailer is available
        $this->use_phpmailer = class_exists('PHPMailer\PHPMailer\PHPMailer');
        
        error_log("Email class initialized. Using: " . ($this->use_phpmailer ? 'PHPMailer' : 'mail()'));
        error_log("SMTP: {$this->host}:{$this->port}, User: {$this->username}");
    }
    
    public function sendVerificationEmail($to, $name, $token) {
        error_log("Sending verification email to: {$to}");
        
        $subject = "Potvrdenie emailovej adresy";
        
        // Create verification link
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];
        $verification_link = "{$protocol}://{$domain}/verify.php?token=" . urlencode($token);
        
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a6bdf; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #4a6bdf; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
                .footer { text-align: center; margin-top: 30px; color: #666; font-size: 0.9em; }
                .token { background: #fff; padding: 15px; border: 1px solid #ddd; border-radius: 4px; margin: 20px 0; word-break: break-all; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ElektroObchod</h1>
            </div>
            <div class='content'>
                <h2>Vitajte, {$name}!</h2>
                <p>Ďakujeme za registráciu v našom e-shope. Pre dokončenie registrácie potrebujeme overiť vašu emailovú adresu.</p>
                <p>Kliknite na tlačidlo nižšie pre potvrdenie emailovej adresy:</p>
                <p>
                    <a href='{$verification_link}' class='button' style='color: white;'>Potvrdiť email</a>
                </p>
                <p>Alebo použite tento odkaz:</p>
                <div class='token'>{$verification_link}</div>
                <p><strong>Odkaz je platný 24 hodín.</strong></p>
                <p>Ak ste si nevytvorili účet, tento email môžete ignorovať.</p>
            </div>
            <div class='footer'>
                <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                <p>© " . date('Y') . " elektroobchod.online</p>
            </div>
        </body>
        </html>
        ";
        
        $plain_message = "Vitajte {$name}!\n\nĎakujeme za registráciu v našom e-shope. Pre dokončenie registrácie potvrďte svoju emailovú adresu kliknutím na odkaz:\n\n{$verification_link}\n\nOdkaz je platný 24 hodín.\n\nAk ste si nevytvorili účet, tento email môžete ignorovať.";
        
        if ($this->use_phpmailer) {
            return $this->sendWithPHPMailer($to, $subject, $html_message, $plain_message);
        } else {
            return $this->sendWithMail($to, $subject, $html_message);
        }
    }
    
    private function sendWithPHPMailer($to, $subject, $html_message, $plain_message) {
        error_log("Attempting to send with PHPMailer");
        
        try {
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = true;
            $mail->Username = $this->username;
            $mail->Password = $this->password;
            
            // Port 465 requires SSL
            if ($this->port == 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
            } else {
                $mail->Port = $this->port;
            }
            
            // Debugging
            $mail->SMTPDebug = 2; // Enable verbose debug output
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer [$level]: $str");
            };
            
            // Timeout
            $mail->Timeout = 30;
            
            // Recipients
            $mail->setFrom($this->from_email, $this->from_name);
            $mail->addAddress($to);
            $mail->addReplyTo($this->from_email, $this->from_name);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $html_message;
            $mail->AltBody = $plain_message;
            $mail->CharSet = 'UTF-8';
            
            $mail->send();
            error_log("PHPMailer: Email sent successfully to {$to}");
            return true;
            
        } catch (\Exception $e) {
            error_log("PHPMailer Error: " . $e->getMessage());
            // Fall back to mail() function
            error_log("Falling back to mail() function");
            return $this->sendWithMail($to, $subject, $html_message);
        }
    }
    
    private function sendWithMail($to, $subject, $html_message) {
        error_log("Using mail() function as fallback");
        
        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: " . $this->from_name . " <" . $this->from_email . ">" . "\r\n";
        $headers .= "Reply-To: " . $this->from_email . "\r\n";
        $headers .= "Return-Path: " . $this->from_email . "\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        $headers .= "X-Priority: 1 (Highest)\r\n";
        
        // Set encoding
        $subject = "=?UTF-8?B?" . base64_encode($subject) . "?=";
        
        set_time_limit(30);
        
        $result = mail($to, $subject, $html_message, $headers);
        error_log("mail() function result: " . ($result ? 'true' : 'false'));
        
        return $result;
    }
    
    public function testConnection() {
        error_log("Testing connection to {$this->host}:{$this->port}");
        
        if (empty($this->host) || empty($this->username)) {
            error_log("Email credentials missing");
            return false;
        }
        
        // Test SMTP connection
        $timeout = 10;
        $fp = @fsockopen($this->host, $this->port, $errno, $errstr, $timeout);
        
        if (!$fp) {
            error_log("Cannot connect to SMTP server {$this->host}:{$this->port} - $errstr ($errno)");
            return false;
        }
        
        // Check if it's actually an SMTP server
        $response = fgets($fp, 512);
        error_log("SMTP response: " . trim($response));
        
        fclose($fp);
        error_log("SMTP server connection test passed");
        return true;
    }
}