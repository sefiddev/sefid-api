<?php
/**
 * نمونه‌ی امضای سفید (PHP ≥ 7.4). منطق دقیقا با لایه‌ی سفید (signing.ts) یکسان است.
 *
 *   php sign.php
 *
 * در پروداکشن کلیدها را از متغیرهای محیطی/پیکربندی امن بخوانید.
 */

$PUBLIC_KEY = getenv('SEFID_PUBLIC_KEY') ?: 'pk_xxx';
$SECRET_KEY = getenv('SEFID_SECRET_KEY') ?: 'sk_xxx';
$API_BASE   = getenv('SEFID_API_BASE') ?: 'https://sefid.dev';

function sefid_b64url($data) {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/** توکن هویت برای دادن به ویجت. */
function sefid_build_identity_token($secret, array $payload) {
    $body = sefid_b64url(json_encode($payload, JSON_UNESCAPED_UNICODE));
    return $body . '.' . hash_hmac('sha256', $body, $secret);
}

/** امضا + ارسال POST امضاشده به سفید. $path مثل "/api/ingest/products". */
function sefid_signed_post($base, $public, $secret, $path, array $bodyObj) {
    $body = json_encode($bodyObj, JSON_UNESCAPED_UNICODE);
    $ts = (string) round(microtime(true) * 1000); // unix ms
    $nonce = bin2hex(random_bytes(16));
    $canonical = "POST\n" . $path . "\n" . $ts . "\n" . $nonce . "\n" . hash('sha256', $body);
    $sig = hash_hmac('sha256', $canonical, $secret);

    $ch = curl_init($base . $path);
    curl_setopt_array($ch, array(
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json',
            'X-Sefid-Key: ' . $public,
            'X-Sefid-Timestamp: ' . $ts,
            'X-Sefid-Nonce: ' . $nonce,
            'X-Sefid-Signature: ' . $sig,
        ),
    ));
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return array('status' => $status, 'body' => $resp);
}

// ── نمونه ──
if (php_sapi_name() === 'cli' && isset($argv) && realpath($argv[0]) === __FILE__) {
    $now = time();
    $token = sefid_build_identity_token($SECRET_KEY, array(
        'loggedIn'   => true,
        'externalId' => '123',
        'name'       => 'سارا',
        'phone'      => '09120000000',
        'iat'        => $now,
        'exp'        => $now + 30 * 24 * 3600,
    ));
    echo "identityToken: $token\n";

    $out = sefid_signed_post($API_BASE, $PUBLIC_KEY, $SECRET_KEY, '/api/ingest/products', array(
        'products' => array(array(
            'externalId' => 'SKU-1',
            'name'       => 'شومیز مشکی',
            'price'      => 620000,
            'inStock'    => true,
            'stockQty'   => 10,
            'productUrl' => 'https://site/p/shomiz',
            'shippable'  => true,
            'categories' => array('پوشاک'),
            'variations' => array(array('attributes' => array('size' => 'M'), 'price' => 620000, 'inStock' => true)),
        )),
    ));
    echo "ingest: {$out['status']} {$out['body']}\n";
}
