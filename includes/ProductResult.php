<?php

declare(strict_types=1);

/**
 * Normalized AI recognition result with validation.
 */
final class ProductResult
{
    public string $objectLabel;
    public string $productName;
    public string $manufacturer;
    public string $specification;
    public string $description;
    /** @var array{ymin:int,xmin:int,ymax:int,xmax:int} */
    public array $boundingBox;
    public float $confidence;
    public string $provider;

    /**
     * @param array{ymin:int,xmin:int,ymax:int,xmax:int} $boundingBox
     */
    public function __construct(
        string $objectLabel,
        string $productName,
        string $manufacturer,
        string $specification,
        string $description,
        array $boundingBox,
        float $confidence,
        string $provider = ''
    ) {
        $this->objectLabel = $objectLabel;
        $this->productName = $productName;
        $this->manufacturer = $manufacturer;
        $this->specification = $specification;
        $this->description = $description;
        $this->boundingBox = $boundingBox;
        $this->confidence = $confidence;
        $this->provider = $provider;
    }

    /**
     * Build a validated result from untrusted AI JSON array.
     *
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw, string $provider = ''): ?self
    {
        $objectLabel = self::cleanString($raw['objectLabel'] ?? $raw['object_label'] ?? '');
        $productName = self::cleanString($raw['productName'] ?? $raw['product_name'] ?? '');

        if ($objectLabel === '' && $productName === '') {
            return null;
        }

        if ($objectLabel === '') {
            $objectLabel = $productName;
        }
        if ($productName === '') {
            $productName = $objectLabel;
        }

        $boxRaw = $raw['boundingBox'] ?? $raw['bounding_box'] ?? null;
        $box = self::normalizeBoundingBox(is_array($boxRaw) ? $boxRaw : null);

        $confidence = self::normalizeConfidence($raw['confidence'] ?? 0.5);

        return new self(
            $objectLabel,
            $productName,
            self::cleanString($raw['manufacturer'] ?? 'Unknown'),
            self::cleanString($raw['specification'] ?? ''),
            self::cleanString($raw['description'] ?? ''),
            $box,
            $confidence,
            $provider
        );
    }

    public function isComplete(): bool
    {
        return $this->objectLabel !== '' && $this->productName !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'objectLabel' => $this->objectLabel,
            'productName' => $this->productName,
            'manufacturer' => $this->manufacturer,
            'specification' => $this->specification,
            'description' => $this->description,
            'boundingBox' => $this->boundingBox,
            'confidence' => round($this->confidence, 4),
            'provider' => $this->provider,
        ];
    }

    private static function cleanString(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }
        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return mb_substr($text, 0, 500);
    }

    /**
     * @param array<string, mixed>|null $box
     * @return array{ymin:int,xmin:int,ymax:int,xmax:int}
     */
    private static function normalizeBoundingBox(?array $box): array
    {
        $defaults = ['ymin' => 200, 'xmin' => 200, 'ymax' => 800, 'xmax' => 800];
        if ($box === null) {
            return $defaults;
        }

        $ymin = self::coord($box['ymin'] ?? $box['y_min'] ?? null, $defaults['ymin']);
        $xmin = self::coord($box['xmin'] ?? $box['x_min'] ?? null, $defaults['xmin']);
        $ymax = self::coord($box['ymax'] ?? $box['y_max'] ?? null, $defaults['ymax']);
        $xmax = self::coord($box['xmax'] ?? $box['x_max'] ?? null, $defaults['xmax']);

        if ($ymax <= $ymin) {
            $ymax = min(1000, $ymin + 100);
        }
        if ($xmax <= $xmin) {
            $xmax = min(1000, $xmin + 100);
        }

        return [
            'ymin' => $ymin,
            'xmin' => $xmin,
            'ymax' => $ymax,
            'xmax' => $xmax,
        ];
    }

    private static function coord(mixed $value, int $fallback): int
    {
        if (!is_numeric($value)) {
            return $fallback;
        }
        $n = (int) round((float) $value);
        return max(0, min(1000, $n));
    }

    private static function normalizeConfidence(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.5;
        }
        $c = (float) $value;
        if ($c > 1.0 && $c <= 100.0) {
            $c = $c / 100.0;
        }
        return max(0.0, min(1.0, $c));
    }
}
