<?php

namespace App\Services\Marketplace\Support;

class Code128SvgGenerator
{
    /**
     * @var array<int, string>
     */
    protected const PATTERNS = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312', '132212', '221213',
        '221312', '231212', '112232', '122132', '122231', '113222', '123122', '123221', '223211', '221132',
        '221231', '213212', '223112', '312131', '311222', '321122', '321221', '312212', '322112', '322211',
        '212123', '212321', '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121', '313121', '211331',
        '231131', '213113', '213311', '213131', '311123', '311321', '331121', '312113', '312311', '332111',
        '314111', '221411', '431111', '111224', '111422', '121124', '121421', '141122', '141221', '112214',
        '112412', '122114', '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112', '421211', '212141',
        '214121', '412121', '111143', '111341', '131141', '114113', '114311', '411113', '411311', '113141',
        '114131', '311141', '411131', '211412', '211214', '211232', '2331112',
    ];

    public function dataUri(
        ?string $value,
        int $barHeight = 56,
        bool $withText = true,
        float $moduleWidth = 1.35
    ): string {
        $svg = $this->svg($value, $barHeight, $withText, $moduleWidth);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function svg(
        ?string $value,
        int $barHeight = 56,
        bool $withText = true,
        float $moduleWidth = 1.35
    ): string {
        $normalized = $this->normalize($value);
        $codes = $this->encodeB($normalized);
        $quietZone = 10;
        $fontSize = $withText ? max(11, (int) round($barHeight * 0.22)) : 0;
        $textHeight = $withText ? $fontSize + 10 : 0;

        $x = $quietZone;
        $bars = [];

        foreach ($codes as $code) {
            $pattern = self::PATTERNS[$code] ?? null;

            if ($pattern === null) {
                continue;
            }

            $isBar = true;

            foreach (str_split($pattern) as $widthChar) {
                $width = (int) $widthChar;

                if ($isBar) {
                    $bars[] = sprintf(
                        '<rect x="%.2F" y="0" width="%.2F" height="%d" fill="#111827" />',
                        $x * $moduleWidth,
                        $width * $moduleWidth,
                        $barHeight
                    );
                }

                $x += $width;
                $isBar = !$isBar;
            }
        }

        $x += $quietZone;
        $totalWidth = max(1, $x * $moduleWidth);
        $totalHeight = $barHeight + $textHeight;

        $text = '';

        if ($withText) {
            $text = sprintf(
                '<text x="%.2F" y="%d" text-anchor="middle" font-family="DejaVu Sans, sans-serif" font-size="%d" fill="#334155">%s</text>',
                $totalWidth / 2,
                $barHeight + $fontSize,
                $fontSize,
                htmlspecialchars($normalized, ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%.2F" height="%d" viewBox="0 0 %.2F %d">%s%s</svg>',
            $totalWidth,
            $totalHeight,
            $totalWidth,
            $totalHeight,
            implode('', $bars),
            $text
        );
    }

    protected function normalize(?string $value): string
    {
        $candidate = trim((string) $value);

        if ($candidate === '') {
            return 'NO-DATA';
        }

        $ascii = preg_replace('/[^\x20-\x7E]/', '', $candidate) ?? '';

        return $ascii !== '' ? $ascii : 'NO-DATA';
    }

    /**
     * @return array<int, int>
     */
    protected function encodeB(string $value): array
    {
        $codes = [104];
        $checksum = 104;

        foreach (str_split($value) as $index => $character) {
            $code = max(0, ord($character) - 32);
            $codes[] = $code;
            $checksum += $code * ($index + 1);
        }

        $codes[] = $checksum % 103;
        $codes[] = 106;

        return $codes;
    }
}
