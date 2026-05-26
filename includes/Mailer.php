<?php
/**
 * Mailer.php — إرسال البريد من العنوان الرسمي (CONTACT_EMAIL).
 * ============================================================
 * يفضّل SMTP المصادق (أفضل وصول) إذا ضُبطت بيانات SMTP في config.local.php،
 * وإلا يرجع لدالة mail() المدمجة. يرسل رسالة multipart (نص + HTML) بترميز UTF-8.
 * كل دالة تُرجِع true/false ولا ترمي استثناءات تكسر التشغيل.
 * ============================================================
 */
if (!defined('WC2026')) { exit('Access denied'); }

class Mailer
{
    /** هل بيانات SMTP مكتملة؟ */
    public static function smtpConfigured(): bool
    {
        return defined('SMTP_HOST') && SMTP_HOST !== ''
            && defined('SMTP_USER') && SMTP_USER !== ''
            && defined('SMTP_PASS') && SMTP_PASS !== '';
    }

    /** يرسل رسالة واحدة. يرجّع true عند النجاح. */
    public static function send(string $to, string $subject, string $html, ?string $text = null): bool
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

        $from     = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : 'info@localhost';
        $fromName = defined('SITE_NAME_AR') ? SITE_NAME_AR : 'World Cup 2026';
        $text     = $text ?? trim(preg_replace('/\n{3,}/', "\n\n", html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));

        [$encSubject, $headers, $body] = self::buildMime($from, $fromName, $subject, $html, $text);

        if (self::smtpConfigured()) {
            return self::sendSmtp($from, $to, $encSubject, $headers, $body);
        }
        // mail() يضيف To/Subject بنفسه
        return @mail($to, $encSubject, $body, $headers . "\r\n");
    }

    /** يبني ترويسات وجسم رسالة multipart/alternative. */
    private static function buildMime(string $from, string $fromName, string $subject, string $html, string $text): array
    {
        $boundary = '=_wc_' . bin2hex(random_bytes(8));
        $host     = parse_url(defined('SITE_URL') ? SITE_URL : '', PHP_URL_HOST) ?: 'localhost';
        $encSubj  = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $encName  = '=?UTF-8?B?' . base64_encode($fromName) . '?=';

        $headers =
            "From: {$encName} <{$from}>\r\n" .
            "Reply-To: {$from}\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n" .
            "Date: " . date('r') . "\r\n" .
            "Message-ID: <" . bin2hex(random_bytes(10)) . "@{$host}>";

        $body =
            "--{$boundary}\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($text)) . "\r\n" .
            "--{$boundary}\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: base64\r\n\r\n" .
            chunk_split(base64_encode($html)) . "\r\n" .
            "--{$boundary}--\r\n";

        return [$encSubj, $headers, $body];
    }

    /** إرسال عبر SMTP مصادق (SSL/TLS + AUTH LOGIN). */
    private static function sendSmtp(string $from, string $to, string $encSubject, string $headers, string $body): bool
    {
        $host   = SMTP_HOST;
        $port   = defined('SMTP_PORT') ? (int)SMTP_PORT : 465;
        $secure = defined('SMTP_SECURE') ? strtolower(SMTP_SECURE) : 'ssl';
        $timeout = 20;

        $transport = ($secure === 'ssl') ? "ssl://{$host}:{$port}" : "tcp://{$host}:{$port}";
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $fp = @stream_socket_client($transport, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) return false;
        stream_set_timeout($fp, $timeout);

        $ehlo = parse_url(defined('SITE_URL') ? SITE_URL : '', PHP_URL_HOST) ?: 'localhost';
        $ok = true;
        $ok = $ok && self::expect($fp, '220');
        self::cmd($fp, "EHLO {$ehlo}"); $ok = $ok && self::expect($fp, '250');

        if ($secure === 'tls') {
            self::cmd($fp, 'STARTTLS'); $ok = $ok && self::expect($fp, '220');
            if ($ok && !@stream_socket_enable_crypto($fp, true,
                    STREAM_CRYPTO_METHOD_TLS_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
                $ok = false;
            }
            self::cmd($fp, "EHLO {$ehlo}"); $ok = $ok && self::expect($fp, '250');
        }

        self::cmd($fp, 'AUTH LOGIN');               $ok = $ok && self::expect($fp, '334');
        self::cmd($fp, base64_encode(SMTP_USER));   $ok = $ok && self::expect($fp, '334');
        self::cmd($fp, base64_encode(SMTP_PASS));   $ok = $ok && self::expect($fp, '235');
        self::cmd($fp, "MAIL FROM:<{$from}>");       $ok = $ok && self::expect($fp, '250');
        self::cmd($fp, "RCPT TO:<{$to}>");           $ok = $ok && self::expect($fp, '250');
        self::cmd($fp, 'DATA');                      $ok = $ok && self::expect($fp, '354');

        if ($ok) {
            // الرسالة الكاملة: To + Subject + بقية الترويسات + الجسم، مع تهريب النقطة.
            $msg = "To: {$to}\r\nSubject: {$encSubject}\r\n" . $headers . "\r\n\r\n" . $body;
            $msg = preg_replace('/^\./m', '..', $msg);
            fwrite($fp, $msg . "\r\n.\r\n");
            $ok = self::expect($fp, '250');
        }

        self::cmd($fp, 'QUIT');
        fclose($fp);
        return $ok;
    }

    private static function cmd($fp, string $line): void { @fwrite($fp, $line . "\r\n"); }

    /** يقرأ ردّ SMTP (متعدّد الأسطر) ويتحقّق من بدئه بالرمز المتوقّع. */
    private static function expect($fp, string $code): bool
    {
        $data = '';
        while (($line = fgets($fp, 600)) !== false) {
            $data .= $line;
            // السطر الأخير: الرمز متبوعاً بمسافة (لا شَرطة)
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return strpos($data, $code) === 0;
    }
}
