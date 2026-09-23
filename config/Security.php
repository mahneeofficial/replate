<?php
declare(strict_types=1);

class Security {
    private static array $disposableDomains = [
        'mailinator.com', '10minutemail.com', 'yopmail.com', 'guerrillamail.com', 'temp-mail.org',
        'trash-mail.com', 'getairmail.com', 'sharklasers.com', 'dispostable.com', 'fakeinbox.com',
        'tempmail.com', 'throwawaymail.com', 'guerrillamail.net', 'guerrillamail.org', 'guerrillamail.biz',
        'guerrillamailblock.com', 'pokemail.net', 'spam4.me', 'getnada.com', 'maildrop.cc',
        'discard.email', 'discardmail.com', 'emkei.cz', 'crazymailing.com', 'mytemp.email',
        'tempail.com', '0815.ru', '10minutemail.co.uk', '10minutemail.net', '20mail.it',
        '33mail.com', 'my10minutemail.com', 'dropmail.me', 'inboxkitten.com', 'mohmal.com',
        'tempinbox.com', 'burnmail.net', 'trashmail.net', 'trashmail.me', 'anonbox.net',
        'binkmail.com', 'bobmail.info', 'chammy.info', 'devnullmail.com', 'letthemeatspam.com',
        'mailinater.com', 'reallymymail.com', 'reconmail.com', 'sogetthis.com', 'streetwhisper.com',
        'suremail.info', 'zippymail.in', 'emailondeck.com', 'mailcatch.com', 'fastmail.fm',
        'tempmailaddress.com', 'generator.email', 'emailgenerator.io', 'mailnesia.com', 'tmail.ws',
        'throwaway.email', 'tempmail.dev', 'guerrillamail.info', 'grr.la', 'guerrillamail.de',
        'trbvm.com', 'netmails.net', 'rmqkr.net', 'yopmail.fr', 'yopmail.net',
        'cool.fr.nf', 'jetable.fr.nf', 'courriel.fr.nf', 'moncourriel.fr.nf', 'monemail.fr.nf',
        'monmail.fr.nf', 'hide.biz.st', 'myyopmail.com', 'zhmail.net', 'zoha.com',
        'safetymail.info', 'mailnull.com', 'e4ward.com', 'gishpuppy.com', 'spamex.com',
        'spamgourmet.com', 'trashmail.com', 'mail-fake.com', 'fake-email.pp.ua', 'disposablemail.com'
    ];

    public static function applySecurityHeaders(): void {
        if (!headers_sent()) {
            header("X-Frame-Options: DENY");
            header("X-Content-Type-Options: nosniff");
            header("Referrer-Policy: strict-origin-when-cross-origin");
            header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://images.unsplash.com;");
            header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
        }
    }

    public static function isDisposableEmail(string $email): bool {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2) {
            return true;
        }
        return in_array($parts[1], self::$disposableDomains, true);
    }

    public static function normalizeEmail(string $email): string {
        $email = strtolower(trim($email));
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return $email;
        }

        $username = $parts[0];
        $domain = $parts[1];

        if (str_contains($username, '+')) {
            $username = explode('+', $username)[0];
        }

        if (in_array($domain, ['gmail.com', 'googlemail.com'], true)) {
            $username = str_replace('.', '', $username);
            $domain = 'gmail.com';
        }

        return $username . '@' . $domain;
    }

    public static function sanitize(string $data): string {
        return htmlspecialchars(trim($data), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function cleanInput(string $data): string {
        return trim(strip_tags($data));
    }

    public static function enforceRateLimit(string $key, int $maxAttempts = 5, int $decaySeconds = 300): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $storageDir = sys_get_temp_dir() . '/replate_limits';

        if (!is_dir($storageDir)) {
            @mkdir($storageDir, 0770, true);
        }

        $file = $storageDir . '/rate_' . md5($ip . '_' . $key);
        $data = ['hits' => 0, 'expires' => time() + $decaySeconds];

        $fp = @fopen($file, 'c+');
        if ($fp && flock($fp, LOCK_EX)) {
            $fileContent = stream_get_contents($fp);
            if ($fileContent !== false && !empty($fileContent)) {
                $decoded = json_decode($fileContent, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }

            if (time() > $data['expires']) {
                $data['hits'] = 0;
                $data['expires'] = time() + $decaySeconds;
            }

            $data['hits']++;

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($data));
            fflush($fp);
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        if ($data['hits'] > $maxAttempts) {
            if (!headers_sent()) {
                http_response_code(429);
                header("Content-Type: application/json; charset=UTF-8");
            }
            $remaining = max(1, (int)ceil(($data['expires'] - time()) / 60));
            echo json_encode([
                'success' => false,
                'error' => "ERR_SEC_05: Rate limit exceeded. Try again in {$remaining} minutes."
            ]);
            exit;
        }
    }
}