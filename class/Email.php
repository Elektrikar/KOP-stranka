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
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4a6bdf; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 8px 8px; }
                .button { display: inline-block; background: #4a6bdf; color: white; padding: 12px 24px; text-decoration: none; border-radius: 4px; font-weight: bold; margin: 20px 0; }
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
                <p>Ďakujeme za registráciu v našom e-shope. Pre dokončenie registráciu potrebujeme overiť vašu emailovú adresu.</p>
                <p>Kliknite na tlačidlo nižšie pre potvrdenie emailovej adresy:</p>
                <p>
                    <a href='{$verification_link}' class='button'>Potvrdiť email</a>
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
        
        $plain_message = "Vitajte {$name}!\n\nĎakujeme za registráciu v našom e-shope. Pre dokončenie registráciu potvrďte svoju emailovú adresu kliknutím na odkaz:\n\n{$verification_link}\n\nOdkaz je platný 24 hodín.\n\nAk ste si nevytvorili účet, tento email môžete ignorovať.";
        
        return $this->sendEmail($to, $subject, $html_message, $plain_message);
    }
    
    public function sendOrderConfirmation($to, $orderData) {
        $subject = "Potvrdenie objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT);
        
        // Get order details with products
        $orderDetails = $this->getOrderDetails($orderData['id']);
        
        // Check if payment is completed (based on payment method)
        $paymentMethod = strtolower($orderData['payment_name'] ?? '');
        $paymentStatus = '';
        if (strpos($paymentMethod, 'karta') !== false || strpos($paymentMethod, 'card') !== false) {
            $paymentStatus = 'Uhradené';
        } else {
            $paymentStatus = 'Čaká na úhradu (dobierka)';
        }
        
        // Check shipping method
        $shippingMethod = strtolower($orderData['shipping_name'] ?? '');
        $shippingInfo = '';
        if (strpos($shippingMethod, 'osobný odber') !== false || strpos($shippingMethod, 'pickup') !== false) {
            $shippingInfo = '<div class="shipping-note"><strong>Osobný odber:</strong> Po naskladnení si môžete objednávku vyzdvihnúť na našej predajni.</div>';
        } else {
            $shippingInfo = '<div class="shipping-note"><strong>Doprava:</strong> Vaša objednávka bude pripravená na odoslanie po spracovaní.</div>';
        }
        
        // Build products table
        $productsTable = '';
        $subtotal = 0;
        if (!empty($orderDetails)) {
            $productsTable = '<table class="products-table" width="100%" cellpadding="10" cellspacing="0">';
            $productsTable .= '<tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">';
            $productsTable .= '<th style="text-align: left; padding: 10px;">Produkt</th>';
            $productsTable .= '<th style="text-align: center; padding: 10px;">Množstvo</th>';
            $productsTable .= '<th style="text-align: right; padding: 10px;">Cena</th>';
            $productsTable .= '<th style="text-align: right; padding: 10px;">Celkom</th>';
            $productsTable .= '</tr>';
            
            foreach ($orderDetails as $item) {
                $itemTotal = $item['price'] * $item['quantity'];
                $subtotal += $itemTotal;
                
                $productsTable .= '<tr style="border-bottom: 1px solid #eee;">';
                $productsTable .= '<td style="padding: 10px;">' . htmlspecialchars($item['name']) . '</td>';
                $productsTable .= '<td style="text-align: center; padding: 10px;">' . $item['quantity'] . ' ks</td>';
                $productsTable .= '<td style="text-align: right; padding: 10px;">' . number_format($item['price'], 2, ',', ' ') . ' €</td>';
                $productsTable .= '<td style="text-align: right; padding: 10px;">' . number_format($itemTotal, 2, ',', ' ') . ' €</td>';
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
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; }
                .header { background: #4a6bdf; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 35px; border-radius: 0 0 8px 8px; }
                .order-info { background: white; padding: 25px; border-radius: 8px; margin: 25px 0; border: 1px solid #ddd; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #eee; }
                .total-row { font-weight: bold; border-top: 2px solid #333; padding-top: 15px; margin-top: 15px; font-size: 1.1em; }
                .status-badge { display: inline-block; padding: 6px 12px; border-radius: 12px; font-size: 0.9em; font-weight: bold; margin-left: 10px; }
                .status-paid { background: #d4edda; color: #155724; }
                .status-pending { background: #fff3cd; color: #856404; }
                .footer { text-align: center; margin-top: 40px; color: #666; font-size: 0.9em; padding-top: 20px; border-top: 1px solid #ddd; }
                .products-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .products-table th { background: #f8f9fa; font-weight: bold; }
                .products-table td, .products-table th { padding: 12px; border-bottom: 1px solid #ddd; }
                .shipping-note { background: #e8f4fd; padding: 15px; border-radius: 6px; margin: 15px 0; border-left: 4px solid #4a6bdf; }
                .order-summary { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd; }
                .summary-row { display: flex; justify-content: space-between; padding: 8px 0; }
                .summary-total { font-weight: bold; border-top: 2px solid #333; padding-top: 12px; margin-top: 12px; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ElektroObchod</h1>
                <h2>Potvrdenie objednávky</h2>
            </div>
            <div class='content'>
                <p>Ďakujeme za vašu objednávku. Bola úspešne prijatá a spracováva sa.</p>
                
                <div class='order-info'>
                    <h3>Informácie o objednávke</h3>
                    <div class='info-row'>
                        <span>Číslo objednávky: </span>
                        <span><strong>#" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "</strong></span>
                    </div>
                    <div class='info-row'>
                        <span>Dátum objednávky: </span>
                        <span>" . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "</span>
                    </div>
                    <div class='info-row'>
                        <span>Stav: </span>
                        <span><span class='status-badge status-pending'>Čaká na spracovanie</span></span>
                    </div>
                    <div class='info-row'>
                        <span>Spôsob dopravy: </span>
                        <span>" . htmlspecialchars($orderData['shipping_name'] ?? 'Neuvedené') . "</span>
                    </div>
                    <div class='info-row'>
                        <span>Spôsob platby: </span>
                        <span>" . htmlspecialchars($orderData['payment_name'] ?? 'Neuvedené') . "</span>
                    </div>
                    <div class='info-row total-row'>
                        <span>Stav platby: </span>
                        <span><span class='status-badge " . (strpos($paymentMethod, 'karta') !== false ? 'status-paid' : 'status-pending') . "'>" . $paymentStatus . "</span></span>
                    </div>
                </div>
                
                {$shippingInfo}
                
                <h3>Produkty v objednávke</h3>
                {$productsTable}
                
                <div class='order-summary'>
                    <div class='summary-row'>
                        <span>Suma produktov:</span>
                        <span>" . number_format($subtotal, 2, ',', ' ') . " €</span>
                    </div>
                    <div class='summary-row'>
                        <span>Doprava (" . htmlspecialchars($orderData['shipping_name'] ?? 'Neuvedené') . "):</span>
                        <span>" . number_format($orderData['shipping_price'] ?? 0, 2, ',', ' ') . " €</span>
                    </div>
                    <div class='summary-row'>
                        <span>Poplatok za platbu (" . htmlspecialchars($orderData['payment_name'] ?? 'Neuvedené') . "):</span>
                        <span>" . number_format($orderData['payment_price'] ?? 0, 2, ',', ' ') . " €</span>
                    </div>
                    <div class='summary-row summary-total'>
                        <span>Celková suma k úhrade:</span>
                        <span><strong>" . number_format($orderData['total'], 2, ',', ' ') . " €</strong></span>
                    </div>
                </div>
                
                <p>Priebeh objednávky môžete sledovať na tejto adrese:</p>
                <p><a href='" . $this->getOrderTrackingUrl($orderData['id']) . "'>Sledovať objednávku</a></p>
                
                <p>Ak máte akékoľvek otázky, neváhajte nás kontaktovať.</p>
            </div>
            <div class='footer'>
                <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                <p>© " . date('Y') . " elektroobchod.online</p>
            </div>
        </body>
        </html>";
        
        $plain_message = "Potvrdenie objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "\n";
        $plain_message .= "Dátum: " . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "\n";
        $plain_message .= "Stav: Čaká na spracovanie\n";
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

    public function sendOrderStatusUpdate($to, $name, $orderData) {
        $statusLabels = [
            'pending' => 'Čaká na spracovanie',
            'processing' => 'Spracováva sa',
            'shipped' => 'Odoslané',
            'delivered' => 'Doručené',
            'cancelled' => 'Zrušené'
        ];
        
        $status = $orderData['status'];
        $statusLabel = $statusLabels[$status] ?? $status;
        $statusClass = "status-" . $status;
        
        $subject = "Stav objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . " bol aktualizovaný";
        
        // Get order details with products
        $orderDetails = $this->getOrderDetails($orderData['id']);
        
        // Check shipping method for special handling
        $shippingMethod = strtolower($orderData['shipping_name'] ?? '');
        $statusDescription = $this->getStatusDescription($status, $shippingMethod);
        
        // Build products table
        $productsTable = '';
        if (!empty($orderDetails)) {
            $productsTable = '<table class="products-table" width="100%" cellpadding="10" cellspacing="0">';
            $productsTable .= '<tr style="background: #f8f9fa; border-bottom: 2px solid #ddd;">';
            $productsTable .= '<th style="text-align: left; padding: 10px;">Produkt</th>';
            $productsTable .= '<th style="text-align: center; padding: 10px;">Množstvo</th>';
            $productsTable .= '<th style="text-align: right; padding: 10px;">Cena</th>';
            $productsTable .= '</tr>';
            
            foreach ($orderDetails as $item) {
                $productsTable .= '<tr style="border-bottom: 1px solid #eee;">';
                $productsTable .= '<td style="padding: 10px;">' . htmlspecialchars($item['name']) . '</td>';
                $productsTable .= '<td style="text-align: center; padding: 10px;">' . $item['quantity'] . ' ks</td>';
                $productsTable .= '<td style="text-align: right; padding: 10px;">' . number_format($item['price'], 2, ',', ' ') . ' €</td>';
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
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 700px; margin: 0 auto; padding: 20px; }
                .header { background: #4a6bdf; color: white; padding: 25px; text-align: center; border-radius: 8px 8px 0 0; }
                .content { background: #f8f9fa; padding: 35px; border-radius: 0 0 8px 8px; }
                .status-update { background: white; padding: 30px; border-radius: 8px; margin: 25px 0; text-align: center; border: 2px solid #4a6bdf; }
                .status-text { font-size: 1.2em; margin: 15px 0; }
                .status-badge { display: inline-block; padding: 10px 20px; border-radius: 25px; font-size: 1.2em; font-weight: bold; }
                .status-pending { background: #fff3cd; color: #856404; }
                .status-processing { background: #cce5ff; color: #004085; }
                .status-shipped { background: #d1ecf1; color: #0c5460; }
                .status-delivered { background: #d4edda; color: #155724; }
                .status-cancelled { background: #f8d7da; color: #721c24; }
                .footer { text-align: center; margin-top: 40px; color: #666; font-size: 0.9em; padding-top: 20px; border-top: 1px solid #ddd; }
                .products-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .products-table th { background: #f8f9fa; font-weight: bold; }
                .products-table td, .products-table th { padding: 12px; border-bottom: 1px solid #ddd; }
                .info-box { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #4a6bdf; }
                .next-steps { background: #e8f4fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3; }
                .order-details { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; border: 1px solid #ddd; }
                .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #eee; }
                .detail-row:last-child { border-bottom: none; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h1>ElektroObchod</h1>
                <h2>Aktualizácia stavu objednávky</h2>
            </div>
            <div class='content'>
                <p>Vitajte <strong>{$name}</strong>,</p>
                <p>Stav vašej objednávky bol aktualizovaný.</p>
                
                <div class='status-update'>
                    <h3>Objednávka #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . "</h3>
                    <div class='status-text'>
                        Nový stav: <span class='status-badge {$statusClass}'>{$statusLabel}</span>
                    </div>
                </div>
                
                <div class='info-box'>
                    <p>{$statusDescription}</p>
                </div>
                
                <div class='order-details'>
                    <h4>Detaily objednávky</h4>
                    <div class='detail-row'>
                        <span>Dátum objednávky:</span>
                        <span>" . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "</span>
                    </div>
                    <div class='detail-row'>
                        <span>Spôsob dopravy:</span>
                        <span>" . htmlspecialchars($orderData['shipping_name'] ?? 'Neuvedené') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span>Spôsob platby:</span>
                        <span>" . htmlspecialchars($orderData['payment_name'] ?? 'Neuvedené') . "</span>
                    </div>
                    <div class='detail-row'>
                        <span>Celková suma:</span>
                        <span><strong>" . number_format($orderData['total'], 2, ',', ' ') . " €</strong></span>
                    </div>
                </div>
                
                <h4>Produkty v objednávke</h4>
                {$productsTable}
                
                " . $this->getNextSteps($status, $shippingMethod) . "
                
                <p>Priebeh objednávky môžete sledovať na tejto adrese:</p>
                <p><a href='" . $this->getOrderTrackingUrl($orderData['id']) . "'>Sledovať objednávku</a></p>
                
                <p>Ak máte akékoľvek otázky, neváhajte nás kontaktovať.</p>
            </div>
            <div class='footer'>
                <p>Tento email bol odoslaný z webovej stránky ElektroObchod</p>
                <p>© " . date('Y') . " elektroobchod.online</p>
            </div>
        </body>
        </html>
        ";
        
        $plain_message = "Vitajte {$name},\n\n";
        $plain_message .= "Stav vašej objednávky #" . str_pad($orderData['id'], 6, '0', STR_PAD_LEFT) . " bol aktualizovaný.\n\n";
        $plain_message .= "Nový stav: {$statusLabel}\n";
        $plain_message .= $statusDescription . "\n\n";
        $plain_message .= "Detaily objednávky:\n";
        $plain_message .= "Dátum: " . date('d.m.Y H:i', strtotime($orderData['created_at'])) . "\n";
        $plain_message .= "Doprava: " . ($orderData['shipping_name'] ?? 'Neuvedené') . "\n";
        $plain_message .= "Platba: " . ($orderData['payment_name'] ?? 'Neuvedené') . "\n";
        $plain_message .= "Suma: " . number_format($orderData['total'], 2, ',', ' ') . " €\n\n";
        
        if (!empty($orderDetails)) {
            $plain_message .= "Produkty:\n";
            foreach ($orderDetails as $item) {
                $plain_message .= "- " . $item['name'] . " (" . $item['quantity'] . " ks)\n";
            }
            $plain_message .= "\n";
        }
        
        $plain_message .= "Sledovať objednávku: " . $this->getOrderTrackingUrl($orderData['id']) . "\n\n";
        $plain_message .= "Ak máte akékoľvek otázky, neváhajte nás kontaktovať.\n";
        
        return $this->sendEmail($to, $subject, $html_message, $plain_message);
    }

    private function getStatusDescription($status, $shippingMethod = '') {
        $descriptions = [
            'pending' => 'Vaša objednávka čaká na spracovanie. O jej ďalšom postupe vás budeme informovať.',
            'processing' => 'Vaša objednávka sa momentálne spracováva. Pripravujeme ju na odoslanie.',
            'shipped' => 'Vaša objednávka bola odoslaná. Čoskoro by mala byť doručená.',
            'delivered' => 'Vaša objednávka bola úspešne doručená. Ďakujeme za nákup!',
            'cancelled' => 'Vaša objednávka bola zrušená. Ak máte otázky, kontaktujte nás.'
        ];
        
        $description = $descriptions[$status] ?? 'Stav vašej objednávky bol aktualizovaný.';
        
        // Special handling for delivery status based on shipping method
        if ($status === 'delivered') {
            if (strpos($shippingMethod, 'osobný odber') !== false || strpos($shippingMethod, 'pickup') !== false) {
                $description = 'Vaša objednávka je pripravená na osobný odber. Môžete si ju vyzdvihnúť na našej predajni.';
            }
        }
        
        return $description;
    }
    
    private function getNextSteps($status, $shippingMethod = '') {
        $nextSteps = '';
        
        if ($status === 'delivered') {
            if (strpos($shippingMethod, 'osobný odber') !== false || strpos($shippingMethod, 'pickup') !== false) {
                $nextSteps = '
                <div class="next-steps">
                    <h4>Ďalšie kroky</h4>
                    <p><strong>Osobný odber:</strong> Vaša objednávka je pripravená na odber. Prosíme, príjdite si ju vyzdvihnúť do 7 pracovných dní.</p>
                    <p><strong>Potrebné doklady:</strong> Pri odbere budete potrebovať číslo objednávky a občiansky preukaz.</p>
                </div>';
            } else {
                $nextSteps = '
                <div class="next-steps">
                    <h4>Ďalšie kroky</h4>
                    <p><strong>Doručená objednávka:</strong> Ak máte s produktom akékoľvek otázky alebo problémy, neváhajte nás kontaktovať.</p>
                    <p><strong>Záruka:</strong> Na všetky produkty poskytujeme 2-ročnú záruku.</p>
                </div>';
            }
        } elseif ($status === 'shipped') {
            $nextSteps = '
            <div class="next-steps">
                <h4>Ďalšie kroky</h4>
                <p><strong>Sledovanie zásielky:</strong> V najbližších dňoch obdržíte informácie o sledovaní zásielky.</p>
                <p><strong>Doručenie:</strong> Pri doručovaní buďte, prosím, dostupní na uvedenom telefóne.</p>
            </div>';
        } elseif ($status === 'processing') {
            $nextSteps = '
            <div class="next-steps">
                <h4>Ďalšie kroky</h4>
                <p><strong>Príprava objednávky:</strong> Vaša objednávka sa pripravuje na expedíciu.</p>
                <p><strong>Očakávaný čas:</strong> Spracovanie objednávky trvá 1-2 pracovné dni.</p>
            </div>';
        }
        
        return $nextSteps;
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
}