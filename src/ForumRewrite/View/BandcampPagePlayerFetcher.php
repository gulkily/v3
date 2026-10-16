<?php

declare(strict_types=1);
namespace ForumRewrite\View;

final class BandcampPagePlayerFetcher
{
    private const MAX_BYTES = 1048576;
    /** @param null|callable(string): ?array{status:int,type:string,body:string} $transport */
    public function __construct(private $transport = null) {}

    /** @return ?array{kind:string,id:string} */
    public function fetch(string $url): ?array
    {
        $release = BandcampReleaseUrl::parse($url);
        if ($release === null) { return null; }
        try {
            $response = $this->transport !== null ? ($this->transport)($release['url']) : $this->request($release['url']);
            if ($response === null || $response['status'] !== 200
                || !preg_match('#^text/html(?:;|$)#i', $response['type'])
                || strlen($response['body']) > self::MAX_BYTES) { return null; }
            $dom = new \DOMDocument();
            $previous = libxml_use_internal_errors(true);
            try { $loaded = $dom->loadHTML($response['body'], LIBXML_NONET); }
            finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
            if (!$loaded) { return null; }
            $ids = [];
            foreach ($dom->getElementsByTagName('meta') as $meta) {
                if (strtolower($meta->getAttribute('property')) !== 'og:video') { continue; }
                $player = $meta->getAttribute('content');
                if (!preg_match('#^https://bandcamp\.com/EmbeddedPlayer/(?:v=2/)?' . $release['kind'] . '=([1-9][0-9]{0,19})/(?:[A-Za-z_]+=[A-Za-z0-9]+/)*$#D', $player, $m)) { return null; }
                $ids[$m[1]] = true;
            }
            if (count($ids) !== 1) { return null; }
            return ['kind' => $release['kind'], 'id' => (string) array_key_first($ids)];
        } catch (\Throwable) { return null; }
    }

    public function isPublicAddress(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) { return false; }
        $number = (int) sprintf('%u', ip2long($ip));
        foreach (['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4'] as $range) {
            [$network, $bits] = explode('/', $range);
            $mask = (0xffffffff << (32 - (int) $bits)) & 0xffffffff;
            if (($number & $mask) === (ip2long($network) & $mask)) { return false; }
        }
        return true;
    }

    /** Pins the connection to a validated public IP; TLS and Host use the artist hostname. */
    private function request(string $url): ?array
    {
        $deadline = microtime(true) + 3;
        $host = parse_url($url, PHP_URL_HOST);
        $addresses = gethostbynamel($host);
        if (!$addresses) { return null; }
        foreach ($addresses as $ip) {
            if (!$this->isPublicAddress($ip)) { return null; }
        }
        $remaining = $deadline - microtime(true);
        if ($remaining <= 0) { return null; }
        $context = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => $remaining, 'follow_location' => 0, 'ignore_errors' => true,
                'header' => "Host: {$host}\r\nUser-Agent: Mozilla/5.0 (compatible)\r\nConnection: close\r\n"],
            'ssl' => ['peer_name' => $host, 'verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true],
        ]);
        $stream = @fopen('https://' . $addresses[0] . parse_url($url, PHP_URL_PATH), 'rb', false, $context);
        if ($stream === false) { return null; }
        try {
            $headers = stream_get_meta_data($stream)['wrapper_data'] ?? [];
            $status = 0; $type = '';
            foreach ($headers as $header) {
                if (preg_match('#^HTTP/\S+ ([0-9]{3})#', $header, $m)) { $status = (int) $m[1]; }
                if (stripos($header, 'Content-Type:') === 0) { $type = trim(substr($header, 13)); }
            }
            if ($status !== 200) { return null; }
            $body = '';
            while (!feof($stream)) {
                $remaining = $deadline - microtime(true);
                if ($remaining <= 0) { return null; }
                stream_set_timeout($stream, (int) $remaining, (int) (($remaining - (int) $remaining) * 1000000));
                $chunk = fread($stream, min(8192, self::MAX_BYTES + 1 - strlen($body)));
                if ($chunk === false || stream_get_meta_data($stream)['timed_out']) { return null; }
                $body .= $chunk;
                if (strlen($body) > self::MAX_BYTES) { return null; }
            }
            return ['status' => $status, 'type' => $type, 'body' => $body];
        } finally { fclose($stream); }
    }
}
