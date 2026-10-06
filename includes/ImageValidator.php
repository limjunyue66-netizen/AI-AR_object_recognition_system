<?php

declare(strict_types=1);

/**
 * Validates and normalizes inbound Base64 image payloads.
 */
final class ImageValidator
{
    private const MAX_BYTES = 4_500_000;
    private const ALLOWED_MIMES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

    /**
     * @return array{ok:bool, error?:string, binary?:string, mime?:string, width?:int, height?:int}
     */
    public static function validateBase64(string $input): array
    {
        $raw = trim($input);
        if ($raw === '') {
            return ['ok' => false, 'error' => 'Empty image.'];
        }

        $mime = 'image/jpeg';
        if (preg_match('#^data:(image/[a-zA-Z0-9.+-]+);base64,#i', $raw, $m)) {
            $mime = strtolower($m[1]);
            $raw = substr($raw, strlen($m[0]));
        }

        $raw = preg_replace('/\s+/', '', $raw) ?? '';
        if ($raw === '' || !preg_match('#^[A-Za-z0-9+/]+={0,2}$#', $raw)) {
            return ['ok' => false, 'error' => 'Invalid Base64 image data.'];
        }

        $binary = base64_decode($raw, true);
        if ($binary === false || $binary === '') {
            return ['ok' => false, 'error' => 'Unable to decode image data.'];
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return ['ok' => false, 'error' => 'Image is too large.'];
        }

        $info = @getimagesizefromstring($binary);
        if ($info === false || empty($info['mime'])) {
            return ['ok' => false, 'error' => 'Invalid image content.'];
        }

        $detectedMime = strtolower((string) $info['mime']);
        if (!in_array($detectedMime, self::ALLOWED_MIMES, true)) {
            return ['ok' => false, 'error' => 'Unsupported image type.'];
        }

        if (!in_array($mime, self::ALLOWED_MIMES, true)) {
            $mime = $detectedMime;
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        if ($width < 16 || $height < 16) {
            return ['ok' => false, 'error' => 'Image is too small.'];
        }

        return [
            'ok' => true,
            'binary' => $binary,
            'mime' => $detectedMime === 'image/jpg' ? 'image/jpeg' : $detectedMime,
            'width' => $width,
            'height' => $height,
        ];
    }
}
