<?php
declare(strict_types=1);

function send_app_email(string $to, string $subject, string $html): bool
{
        $host = getenv('SMTP_HOST') ?: '';
    $port = (int) (getenv('SMTP_PORT') ?: 587);
        $user = getenv('SMTP_USER') ?: '';
        $pass = getenv('SMTP_PASS') ?: '';
        $from = getenv('SMTP_FROM') ?: '';
    $fromName = getenv('SMTP_FROM_NAME') ?: 'Fashion Store';

    if (!$host || !$user || !$pass || !$from || $port < 1 || $port > 65535
        || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)
        || preg_match('/[\r\n]/', $subject . $fromName)) {
        error_log('SMTP is not configured correctly; set SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS, SMTP_FROM, and SMTP_FROM_NAME.');
        return false;
    }

    $context = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
    $transport = $port === 465 ? 'ssl://' : 'tcp://';
    $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $error, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        error_log('SMTP connection failed: ' . $error . ' (' . $errno . ')');
        return false;
    }
    stream_set_timeout($socket, 15);

    $readResponse = static function (int $expected) use ($socket): bool {
        do {
            $line = fgets($socket, 515);
            if ($line === false || strlen($line) < 4 || !ctype_digit(substr($line, 0, 3))) {
                return false;
            }
            $code = (int) substr($line, 0, 3);
        } while ($line[3] !== ' ');

        $meta = stream_get_meta_data($socket);
        return empty($meta['timed_out']) && $code === $expected;
    };
    $writeAll = static function (string $data) use ($socket): bool {
        $length = strlen($data);
        $written = 0;
        while ($written < $length) {
            $count = fwrite($socket, substr($data, $written));
            if ($count === false || $count === 0) {
                return false;
            }
            $written += $count;
        }
        return true;
    };
    $command = static function (string $value, int $expected) use ($writeAll, $readResponse): bool {
        return $writeAll($value . "\r\n") && $readResponse($expected);
    };
    $fail = static function () use ($socket): bool {
        fclose($socket);
        error_log('SMTP delivery failed. Check the configured SMTP service and credentials.');
        return false;
    };

    if (!$readResponse(220) || !$command('EHLO localhost', 250)) {
        return $fail();
    }
    if ($port !== 465) {
        if (!$command('STARTTLS', 220)
            || !stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)
            || !$command('EHLO localhost', 250)) {
            return $fail();
        }
    }
    if (!$command('AUTH LOGIN', 334)
        || !$command(base64_encode($user), 334)
        || !$command(base64_encode($pass), 235)
        || !$command('MAIL FROM:<' . $from . '>', 250)
        || !$command('RCPT TO:<' . $to . '>', 250)
        || !$command('DATA', 354)) {
        return $fail();
    }

    $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
    $headers = 'Date: ' . date(DATE_RFC2822) . "\r\n"
        . 'From: ' . $encodedName . ' <' . $from . ">\r\n"
        . 'To: <' . $to . ">\r\n"
        . 'Subject: ' . $subject . "\r\n"
        . "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $body = preg_replace('/\r?\n/', "\r\n", $html) ?? '';
    $body = preg_replace('/(?m)^\./', '..', $body) ?? $body;
    if (!$writeAll($headers . $body . "\r\n.\r\n") || !$readResponse(250)) {
        return $fail();
    }

    $writeAll("QUIT\r\n");
    fclose($socket);
    return true;
}