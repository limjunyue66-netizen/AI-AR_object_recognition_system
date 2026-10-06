<?php

declare(strict_types=1);

require_once __DIR__ . '/ProductResult.php';

/**
 * Optional conservative web enrichment using DuckDuckGo Instant Answer.
 * Only accepts more specific names when evidence strongly matches the AI result.
 */
final class ProductLookup
{
    public static function enrich(ProductResult $result): ?ProductResult
    {
        $query = trim($result->manufacturer . ' ' . $result->productName);
        if ($query === '' || mb_strlen($query) < 3) {
            return null;
        }

        $url = 'https://api.duckduckgo.com/?' . http_build_query([
            'q' => $query,
            'format' => 'json',
            'no_redirect' => 1,
            'no_html' => 1,
            'skip_disambig' => 1,
        ]);

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 2,
            CURLOPT_CONNECTTIMEOUT => 1,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'AI-AR-ObjectScanner/1.0',
        ]);

        $body = curl_exec($ch);
        curl_close($ch);

        if (!is_string($body) || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (!is_array($data)) {
            return null;
        }

        $heading = trim((string) ($data['Heading'] ?? ''));
        $abstract = trim((string) ($data['AbstractText'] ?? ''));
        $abstractSource = trim((string) ($data['AbstractSource'] ?? ''));

        if ($heading === '' && $abstract === '') {
            return null;
        }

        if (!self::isSameProduct($result, $heading, $abstract)) {
            return null;
        }

        $clone = new ProductResult(
            $result->objectLabel,
            self::preferSpecificName($result->productName, $heading),
            $result->manufacturer !== '' && strcasecmp($result->manufacturer, 'Unknown') !== 0
                ? $result->manufacturer
                : self::guessManufacturer($heading, $result->manufacturer),
            $result->specification !== '' ? $result->specification : mb_substr($abstract, 0, 160),
            $result->description !== '' ? $result->description : ($abstract !== '' ? mb_substr($abstract, 0, 280) : $result->description),
            $result->boundingBox,
            $result->confidence,
            $result->provider
        );

        // Keep original if enrichment did not meaningfully improve anything
        if (
            $clone->productName === $result->productName
            && $clone->specification === $result->specification
            && $clone->description === $result->description
        ) {
            return null;
        }

        // Attach source hint into description lightly when abstract came from web
        if ($abstract !== '' && $abstractSource !== '' && $clone->description === mb_substr($abstract, 0, 280)) {
            // already set from abstract — fine
        }

        return $clone;
    }

    private static function isSameProduct(ProductResult $result, string $heading, string $abstract): bool
    {
        $hay = mb_strtolower($heading . ' ' . $abstract);
        $tokens = self::tokens($result->productName . ' ' . $result->manufacturer . ' ' . $result->objectLabel);
        if ($tokens === []) {
            return false;
        }

        $hits = 0;
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 3) {
                continue;
            }
            if (mb_strpos($hay, $token) !== false) {
                $hits++;
            }
        }

        // Require at least half of meaningful tokens to overlap
        $needed = max(1, (int) ceil(count($tokens) * 0.5));
        return $hits >= $needed;
    }

    /**
     * @return list<string>
     */
    private static function tokens(string $text): array
    {
        $text = mb_strtolower($text);
        $parts = preg_split('/[^a-z0-9]+/i', $text) ?: [];
        $stop = ['the', 'and', 'for', 'with', 'from', 'unknown', 'generic', 'product'];
        $out = [];
        foreach ($parts as $p) {
            $p = trim($p);
            if ($p === '' || in_array($p, $stop, true)) {
                continue;
            }
            if (!in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        return $out;
    }

    private static function preferSpecificName(string $original, string $heading): string
    {
        $heading = trim($heading);
        if ($heading === '') {
            return $original;
        }

        // Only use heading if it looks more specific (longer) and shares tokens
        $oTokens = self::tokens($original);
        $hTokens = self::tokens($heading);
        $shared = array_intersect($oTokens, $hTokens);
        if ($shared === []) {
            return $original;
        }

        if (mb_strlen($heading) > mb_strlen($original) + 2) {
            return mb_substr($heading, 0, 120);
        }

        return $original;
    }

    private static function guessManufacturer(string $heading, string $fallback): string
    {
        $parts = preg_split('/\s+/', trim($heading)) ?: [];
        if ($parts !== [] && preg_match('/^[A-Za-z][A-Za-z0-9&\-]+$/', $parts[0])) {
            return $parts[0];
        }
        return $fallback !== '' ? $fallback : 'Unknown';
    }
}
