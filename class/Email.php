<?php

class Email {
    private $host;
    private $port;
    private $username;
    private $password;
    private $from_email;
    private $from_name;
    private $use_phpmailer = false;
    private $pdo;
    
    public function __construct($pdo = null) {
        $this->host = env('MAIL_HOST');
        $this->port = env('MAIL_PORT');
        $this->username = env('MAIL_USER');
        $this->password = env('MAIL_PASS');
        $this->from_email = env('MAIL_FROM', 'info@elektroobchod.online');
        $this->from_name = env('MAIL_FROM_NAME', 'ElektroObchod');
        $this->pdo = $pdo;
        
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
                body { 
                    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #2d3748; 
                    background: #f7fafc; 
                    margin: 0; 
                    padding: 20px; 
                }
                .email-container { 
                    max-width: 700px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
                }
                .header { 
                    background-color: #4a6bdf;
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .header a {
                    color: white;
                    text-decoration: none;
                }
                .content { 
                    padding: 40px; 
                }
                .verification-card { 
                    background: #fff; 
                    border-radius: 8px; 
                    padding: 25px; 
                    margin: 25px 0; 
                    border: 1px solid #e2e8f0; 
                }
                .info-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 15px; 
                    margin-top: 20px;
                }
                .info-item { 
                    padding: 10px 0; 
                    border-bottom: 1px solid #edf2f7; 
                }
                .verification-button { 
                    display: inline-block;
                    background: #4a6bdf;
                    color: white;
                    padding: 14px 28px;
                    text-decoration: none;
                    border-radius: 6px;
                    font-weight: 600;
                    margin: 20px 0;
                    transition: all 0.2s;
                    border: none;
                    font-size: 1em;
                    cursor: pointer;
                }
                .verification-button:hover {
                    background: #3a5bc7;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                }
                .token-display { 
                    background: #f8fafc; 
                    padding: 15px; 
                    border-radius: 6px; 
                    margin: 20px 0; 
                    border: 1px solid #e2e8f0; 
                    word-break: break-all;
                    font-family: monospace;
                    font-size: 0.9em;
                }
                .footer { 
                    text-align: center; 
                    padding: 25px; 
                    color: #718096; 
                    font-size: 0.9em; 
                    border-top: 1px solid #e2e8f0; 
                    background: #f8fafc; 
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1 style='margin:0; font-size: 28px;'><a href='https://elektroobchod.online'>ElektroObchod</a></h1>
                </div>
                
                <div class='content'>
                    <h2>Vitajte, {$name}!</h2>
                    <p>Ďakujeme za registráciu v našom e-shope. Pre dokončenie registrácie potrebujeme overiť vašu emailovú adresu.</p>
                    
                    <div class='verification-card'>
                        <p>Kliknite na tlačidlo nižšie pre potvrdenie emailovej adresy:</p>
                        
                        <p style='text-align: center;'>
                            <a href='{$verification_link}' class='verification-button' style='color:white;'>Potvrdiť email</a>
                        </p>
                        
                        <p>Alebo použite tento odkaz:</p>
                        
                        <div class='token-display'>{$verification_link}</div>
                        
                        <div class='info-grid'>
                            <div class='info-item'>
                                <strong>Platnosť odkazu:</strong><br>
                                24 hodín
                            </div>
                            <div class='info-item'>
                                <strong>Čas odoslania:</strong><br>
                                " . date('d.m.Y H:i') . "
                            </div>
                        </div>
                    </div>
                    
                    <p><strong>Ak ste si nevytvorili účet, tento email môžete ignorovať.</strong></p>
                    
                    <p style='margin-top: 30px;'>Ak máte akékoľvek otázky, neváhajte nás kontaktovať.</p>
                </div>
                
                <div class='footer'>
                    <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                    <p>© " . date('Y') . " elektroobchod.online</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $plain_message = "Aktualizácia stavu objednávky\n\n";
        $plain_message .= "Dobrý deň {$name},\n\n";
        $plain_message .= "Ďakujeme za registráciu v našom e-shope. Pre dokončenie registrácie potvrďte svoju emailovú adresu kliknutím na odkaz:\n\n";
        $plain_message .= "{$verification_link}\n\n";
        $plain_message .= "Odkaz je platný 24 hodín.\n\n";
        $plain_message .= "Čas odoslania: " . date('d.m.Y H:i') . "\n\n";
        $plain_message .= "Ak ste si nevytvorili účet, tento email môžete ignorovať.\n\n";
        $plain_message .= "© " . date('Y') . " elektroobchod.online\n";
        
        return $this->sendEmail($to, $subject, $html_message, $plain_message);
    }
    
    public function sendOrderConfirmation($to, $orderData) {
        $subject = "Potvrdenie objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
        
        $orderDetails = $this->getOrderDetails($orderData['id']);
        
        $paymentMethod = strtolower($orderData['payment_name'] ?? '');
        $paymentStatus = strpos($paymentMethod, 'karta') !== false ? 'Uhradené' : 'Čaká na úhradu (dobierka)';

        $productsTable = '';
        $subtotal = 0;
        if (!empty($orderDetails)) {
            $productsTable = '<table class="products-table">';
            $productsTable .= '<tr><th>Obrázok</th><th>Produkt</th><th>Množstvo</th><th>Cena</th><th>Celkom</th></tr>';
            
            foreach ($orderDetails as $item) {
                $itemTotal = $item['price'] * $item['quantity'];
                $subtotal += $itemTotal;
                $imageUrl = $this->getProductImageUrl($item['image']);
                
                $productsTable .= '<tr>';
                $productsTable .= '<td class="text-center">';
                if ($imageUrl) {
                    $productsTable .= '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($item['name']) . '">';
                } else {
                    $productsTable .= '-';
                }
                $productsTable .= '</td>';
                $productsTable .= '<td>' . htmlspecialchars($item['name']) . '</td>';
                $productsTable .= '<td class="text-center">' . $item['quantity'] . ' ks</td>';
                $productsTable .= '<td class="text-right">' . number_format($item['price'], 2, ',', ' ') . ' €</td>';
                $productsTable .= '<td class="text-right">' . number_format($itemTotal, 2, ',', ' ') . ' €</td>';
                $productsTable .= '</tr>';
            }
            
            $productsTable .= '</table>';
        }
        
        $totalDisplay = '';
        if (strpos($paymentMethod, 'karta') !== false) {
            $totalDisplay = '<td>Uhradené</td>';
        } else {
            $totalDisplay = '<td>' . number_format($orderData['total'], 2, ',', ' ') . ' €</td>';
        }
        
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #2d3748; 
                    background: #f7fafc; 
                    margin: 0; 
                    padding: 20px; 
                }
                .email-container { 
                    max-width: 700px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
                }
                .header { 
                    background-color: #4a6bdf;
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .header a {
                    color: white;
                    text-decoration: none;
                }
                .content { 
                    padding: 40px; 
                }
                .order-card { 
                    background: #fff; 
                    border-radius: 8px; 
                    padding: 25px; 
                    margin: 25px 0; 
                    border: 1px solid #e2e8f0; 
                }
                .info-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 15px; 
                    margin-top: 20px;
                }
                .info-item { 
                    padding: 10px 0; 
                    border-bottom: 1px solid #edf2f7; 
                }
                .products-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 25px 0; 
                }
                .products-table th { 
                    background: #f8fafc; 
                    padding: 14px; 
                    text-align: left; 
                    font-weight: 600; 
                    color: #4a5568; 
                    border-bottom: 2px solid #e2e8f0; 
                }
                .products-table td { 
                    padding: 14px; 
                    border-bottom: 1px solid #edf2f7; 
                    vertical-align: middle; 
                }
                .products-table tr:hover { 
                    background: #f8fafc; 
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .summary { 
                    background: #f8fafc; 
                    padding: 25px; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                }
                .summary table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .summary-row td {
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                    font-size: 15px;
                    color: #4a5568;
                }
                .summary-row:first-child td {
                    padding-top: 0;
                }
                .summary-total td {
                    padding-top: 15px;
                    padding-bottom: 0;
                    border-top: 2px solid #cbd5e0;
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 1.2em;
                    color: #2d3748;
                }
                .summary td:first-child {
                    text-align: left;
                    padding-right: 15px;
                }
                .summary td:last-child {
                    text-align: right;
                    font-weight: 500;
                    white-space: nowrap;
                }
                .summary-total td:last-child {
                    font-weight: 700;
                }
                .footer { 
                    text-align: center; 
                    padding: 25px; 
                    color: #718096; 
                    font-size: 0.9em; 
                    border-top: 1px solid #e2e8f0; 
                    background: #f8fafc; 
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1 style='margin:0; font-size: 28px;'><a href='https://elektroobchod.online'>ElektroObchod</a></h1>
                </div>
                
                <div class='content'>
                    <h2>Ďakujeme za vašu objednávku!</h2>
                    <p>Vaša objednávka bola úspešne prijatá a spracováva sa.</p>
                    
                    <div class='order-card'>
                        <h3 style='margin-top:0; color: #2d3748;'>Informácie o objednávke</h3>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <strong>Číslo objednávky:</strong><br>
                                #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "
                            </div>
                            <div class='info-item' style='border-bottom: none;'>
                                <strong>Dátum objednávky:</strong><br>
                                " . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "
                            </div>
                        </div>
                    </div>
                    
                    <div class='summary'>
                        <table width='100%' cellpadding='0' cellspacing='0' border='0'>
                            <tr class='summary-row'>
                                <td>Suma produktov:</td>
                                <td>" . number_format($subtotal, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row'>
                                <td>Doprava (" . htmlspecialchars($orderData['shipping_name'] ?? 'Neuvedené') . "):</td>
                                <td>" . number_format($orderData['shipping_price'] ?? 0, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row'>
                                <td>Poplatok za platbu (" . htmlspecialchars($orderData['payment_name'] ?? 'Neuvedené') . "):</td>
                                <td>" . number_format($orderData['payment_price'] ?? 0, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row summary-total'>
                                <td>Celkovo na úhradu:</td>
                                " . $totalDisplay . "
                            </tr>
                        </table>
                    </div>
                    
                    <h3>Produkty v objednávke</h3>
                    {$productsTable}
                    
                    <p>Aktuálny stav a podrobnosti si môžete pozrieť ak ste prihlásený na <a href='" . $this->getOrderTrackingUrl($orderData['id']) . "'>detaile objednávky</a>.</p>
                    
                    <p style='margin-top: 30px;'>Ak máte akékoľvek otázky, neváhajte nás kontaktovať.</p>
                </div>
                
                <div class='footer'>
                    <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                    <p>© " . date('Y') . " elektroobchod.online</p>
                </div>
            </div>
        </body>
        </html>";
        
        $plain_message = "Potvrdenie objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "\n";
        $plain_message .= "Dátum: " . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "\n";
        $plain_message .= "Spôsob dopravy: " . ($orderData['shipping_name'] ?? 'Neuvedené') . "\n";
        $plain_message .= "Spôsob platby: " . ($orderData['payment_name'] ?? 'Neuvedené') . "\n";
        $plain_message .= "Stav platby: " . $paymentStatus . "\n\n";
        
        if (!empty($orderDetails)) {
            $plain_message .= "Produkty:\n";
            foreach ($orderDetails as $item) {
                $plain_message .= "- " . $item['name'] . " (" . $item['quantity'] . " ks) - " . number_format($item['price'] * $item['quantity'], 2, ',', ' ') . " €\n";
            }
            $plain_message .= "\n";
        }
        
        $plain_message .= "Suma produktov: " . number_format($subtotal, 2, ',', ' ') . " €\n";
        $plain_message .= "Doprava: " . number_format($orderData['shipping_price'] ?? 0, 2, ',', ' ') . " €\n";
        $plain_message .= "Poplatok za platbu: " . number_format($orderData['payment_price'] ?? 0, 2, ',', ' ') . " €\n";
        $plain_message .= "Celková suma: " . number_format($orderData['total'], 2, ',', ' ') . " €\n\n";
        $plain_message .= "Sledovať objednávku: " . $this->getOrderTrackingUrl($orderData['id']) . "\n\n";
        $plain_message .= "Ak máte akékoľvek otázky, neváhajte nás kontaktovať.\n";
        
        return $this->sendEmail($to, $subject, $html_message, $plain_message);
    }

    public function sendOrderStatusUpdate($to, $orderData) {
        $status = $orderData['status'];
        
        // Only send emails for these statuses
        if (!in_array($status, ['shipped', 'delivered', 'ready_for_pickup', 'cancelled'])) {
            return false;
        }
        
        $statusLabels = [
            'pending' => 'Čaká na spracovanie',
            'processing' => 'Spracováva sa',
            'shipped' => 'Odoslané',
            'delivered' => 'Doručené',
            'ready_for_pickup' => 'Pripravené na odber',
            'picked_up' => 'Vyzdvihnuté',
            'cancelled' => 'Zrušené'
        ];
        
        $statusLabel = $statusLabels[$status] ?? $status;
        
        $subjectTemplates = [
            'shipped' => "Vaša objednávka #%s bola odoslaná",
            'delivered' => "Vaša objednávka #%s bola doručená",
            'ready_for_pickup' => "Vaša objednávka #%s je pripravená na odber",
            'picked_up' => "Vaša objednávka #%s bola vyzdvihnutá",
            'cancelled' => "Objednávka #%s bola zrušená"
        ];
        
        $orderNumber = str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
        
        if (isset($subjectTemplates[$status])) {
            $subject = sprintf($subjectTemplates[$status], $orderNumber);
        } else {
            // Fallback for any other status
            $subject = "Stav objednávky #" . $orderNumber . " bol aktualizovaný";
        }
        
        $orderDetails = $this->getOrderDetails($orderData['id']);
        $statusDescription = $this->getStatusDescription($status);
        
        // Calculate subtotal for the summary table
        $subtotal = 0;
        if (!empty($orderDetails)) {
            foreach ($orderDetails as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }
        }

        $paymentMethod = strtolower($orderData['payment_name'] ?? '');
        $totalDisplay = '';
        if (strpos($paymentMethod, 'karta') !== false) {
            $totalDisplay = '<td>Uhradené</td>';
        } else {
            $totalDisplay = '<td>' . number_format($orderData['total'], 2, ',', ' ') . ' €</td>';
        }
        
        $productsTable = '';
        if (!empty($orderDetails)) {
            $productsTable = '<table class="products-table">';
            $productsTable .= '<tr><th>Obrázok</th><th>Produkt</th><th>Množstvo</th><th>Cena</th></tr>';
            
            foreach ($orderDetails as $item) {
                $imageUrl = $this->getProductImageUrl($item['image']);
                
                $productsTable .= '<tr>';
                $productsTable .= '<td class="text-center">';
                if ($imageUrl) {
                    $productsTable .= '<img src="' . $imageUrl . '" alt="' . htmlspecialchars($item['name']) . '">';
                } else {
                    $productsTable .= '-';
                }
                $productsTable .= '</td>';
                $productsTable .= '<td>' . htmlspecialchars($item['name']) . '</td>';
                $productsTable .= '<td class="text-center">' . $item['quantity'] . ' ks</td>';
                $productsTable .= '<td class="text-right">' . number_format($item['price'], 2, ',', ' ') . ' €</td>';
                $productsTable .= '</tr>';
            }
            
            $productsTable .= '</table>';
        }
        
        $html_message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>{$subject}</title>
            <style>
                body { 
                    font-family: 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; 
                    line-height: 1.6; 
                    color: #2d3748; 
                    background: #f7fafc; 
                    margin: 0; 
                    padding: 20px; 
                }
                .email-container { 
                    max-width: 700px; 
                    margin: 0 auto; 
                    background: white; 
                    border-radius: 12px; 
                    overflow: hidden; 
                    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
                }
                .header { 
                    background-color: #4a6bdf;
                    color: white; 
                    padding: 30px; 
                    text-align: center; 
                }
                .header a {
                    color: white;
                    text-decoration: none;
                }
                .content { 
                    padding: 40px; 
                }
                .order-card { 
                    background: #fff; 
                    border-radius: 8px; 
                    padding: 25px; 
                    margin: 25px 0; 
                    border: 1px solid #e2e8f0; 
                }
                .info-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
                    gap: 15px; 
                    margin-top: 20px;
                }
                .info-item { 
                    padding: 10px 0; 
                    border-bottom: 1px solid #edf2f7; 
                }
                .products-table { 
                    width: 100%; 
                    border-collapse: collapse; 
                    margin: 25px 0; 
                }
                .products-table th { 
                    background: #f8fafc; 
                    padding: 14px; 
                    text-align: left; 
                    font-weight: 600; 
                    color: #4a5568; 
                    border-bottom: 2px solid #e2e8f0; 
                }
                .products-table td { 
                    padding: 14px; 
                    border-bottom: 1px solid #edf2f7; 
                    vertical-align: middle; 
                }
                .products-table tr:hover { 
                    background: #f8fafc; 
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .summary { 
                    background: #f8fafc; 
                    padding: 25px; 
                    border-radius: 8px; 
                    margin: 25px 0; 
                }
                .summary table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .summary-row td {
                    padding: 12px 0;
                    border-bottom: 1px solid #e2e8f0;
                    font-size: 15px;
                    color: #4a5568;
                }
                .summary-row:first-child td {
                    padding-top: 0;
                }
                .summary-total td {
                    padding-top: 15px;
                    padding-bottom: 0;
                    border-top: 2px solid #cbd5e0;
                    border-bottom: none;
                    font-weight: bold;
                    font-size: 1.2em;
                    color: #2d3748;
                }
                .summary td:first-child {
                    text-align: left;
                    padding-right: 15px;
                }
                .summary td:last-child {
                    text-align: right;
                    font-weight: 500;
                    white-space: nowrap;
                }
                .summary-total td:last-child {
                    font-weight: 700;
                }
                .footer { 
                    text-align: center; 
                    padding: 25px; 
                    color: #718096; 
                    font-size: 0.9em; 
                    border-top: 1px solid #e2e8f0; 
                    background: #f8fafc; 
                }
            </style>
        </head>
        <body>
            <div class='email-container'>
                <div class='header'>
                    <h1 style='margin:0; font-size: 28px;'><a href='https://elektroobchod.online'>ElektroObchod</a></h1>
                </div>
                
                <div class='content'>
                    <h2>Dobrý deň,</h2>
                    <p>{$statusDescription}</p>
                    
                    <div class='order-card'>
                        <h3 style='margin-top:0; color: #2d3748;'>Informácie o objednávke</h3>
                        <div class='info-grid'>
                            <div class='info-item'>
                                <strong>Číslo objednávky:</strong><br>
                                #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "
                            </div>
                            <div class='info-item'>
                                <strong>Dátum aktualizácie:</strong><br>
                                " . date('d.m.Y H:i') . "
                            </div>
                        </div>
                    </div>
                    
                     <div class='summary'>
                        <table width='100%' cellpadding='0' cellspacing='0' border='0'>
                            <tr class='summary-row'>
                                <td>Suma produktov:</td>
                                <td>" . number_format($subtotal, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row'>
                                <td>Doprava (" . htmlspecialchars($orderData['shipping_name'] ?? 'Neuvedené') . "):</td>
                                <td>" . number_format($orderData['shipping_price'] ?? 0, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row'>
                                <td>Poplatok za platbu (" . htmlspecialchars($orderData['payment_name'] ?? 'Neuvedené') . "):</td>
                                <td>" . number_format($orderData['payment_price'] ?? 0, 2, ',', ' ') . " €</td>
                            </tr>
                            <tr class='summary-row summary-total'>
                                <td>Celkovo na úhradu:</td>
                                " . $totalDisplay . "
                            </tr>
                        </table>
                    </div>
                    
                    <h3>Produkty v objednávke</h3>
                    {$productsTable}
                    
                    <p>Aktuálny stav a podrobnosti si môžete pozrieť ak ste prihlásený na <a href='" . $this->getOrderTrackingUrl($orderData['id']) . "'>detaile objednávky</a>.</p>
                    
                    <p style='margin-top: 30px;'>Ak máte akékoľvek otázky, neváhajte nás kontaktovať.</p>
                </div>
                
                <div class='footer'>
                    <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                    <p>© " . date('Y') . " elektroobchod.online</p>
                </div>
            </div>
        </body>
        </html>";
        
        $plain_message = "Aktualizácia stavu objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "\n";
        $plain_message .= "Dobrý deň,\n\n";
        $plain_message .= $statusDescription . "\n\n";
        $plain_message .= "Nový stav: " . $statusLabel . "\n";
        $plain_message .= "Číslo objednávky: #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "\n";
        $plain_message .= "Dátum aktualizácie: " . date('d.m.Y H:i') . "\n\n";
        
        $plain_message .= "Suma produktov: " . number_format($subtotal, 2, ',', ' ') . " €\n";
        $plain_message .= "Doprava: " . number_format($orderData['shipping_price'] ?? 0, 2, ',', ' ') . " €\n";
        $plain_message .= "Poplatok za platbu: " . number_format($orderData['payment_price'] ?? 0, 2, ',', ' ') . " €\n";
        $plain_message .= "Celková suma: " . number_format($orderData['total'], 2, ',', ' ') . " €\n\n";
        
        if (!empty($orderDetails)) {
            $plain_message .= "Produkty v objednávke:\n";
            foreach ($orderDetails as $item) {
                $plain_message .= "- " . $item['name'] . " (" . $item['quantity'] . " ks) - " . number_format($item['price'], 2, ',', ' ') . " €\n";
            }
            $plain_message .= "\n";
        }
        
        $plain_message .= "Sledovať objednávku: " . $this->getOrderTrackingUrl($orderData['id']) . "\n\n";
        $plain_message .= "Ak máte akékoľvek otázky, neváhajte nás kontaktovať.\n";
        
        return $this->sendEmail($to, $subject, $html_message, $plain_message);
    }

    private function getStatusDescription($status) {
        $descriptions = [
            'pending' => 'Vaša objednávka čaká na spracovanie. O jej ďalšom postupe vás budeme informovať.',
            'processing' => 'Vaša objednávka sa momentálne spracováva. Pripravujeme ju na odoslanie.',
            'shipped' => 'Vaša objednávka bola odoslaná. Čoskoro by mala byť doručená na zvolenú adresu.',
            'delivered' => 'Vaša objednávka bola úspešne doručená. Ďakujeme za nákup!',
            'ready_for_pickup' => 'Vaša objednávka je pripravená na osobný odber. Môžete si ju vyzdvihnúť v našej predajni.',
            'picked_up' => 'Vaša objednávka bola vyzdvihnutá. Ďakujeme za nákup!',
            'cancelled' => 'Vaša objednávka bola zrušená. Ak máte otázky, kontaktujte nás.'
        ];
        $description = $descriptions[$status];
        
        return $description;
    }

    private function getProductImageUrl($image) {
        if (empty($image)) {
            return null;
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];

        return "{$protocol}://{$domain}/img/productsmall/{$image}";
    }

    private function getOrderDetails($orderId) {
        if (!$this->pdo) {
            return [];
        }
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT od.product_id, od.quantity, od.price, p.name, p.image
                FROM order_details od
                JOIN products p ON od.product_id = p.id
                WHERE od.order_id = ?
            ");
            $stmt->execute([$orderId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching order details: " . $e->getMessage());
            return [];
        }
    }

    private function getOrderTrackingUrl($orderId) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $domain = $_SERVER['HTTP_HOST'];
        return "{$protocol}://{$domain}/order_details.php?id={$orderId}";
    }

    private function sendEmail($to, $subject, $html_message, $plain_message) {
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
            
            // Port 465 SMTP + requires SSL
            if ($this->port == 465) {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port = 465;
            } else {
                $mail->Port = $this->port;
            }
            
            // Debugging
            $mail->SMTPDebug = 2;
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
}