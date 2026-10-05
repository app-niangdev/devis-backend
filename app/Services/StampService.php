<?php

namespace App\Services;

use App\Models\Tenant;
use App\Support\SenegalPhone;
use GdImage;

/**
 * Tampon rond de l'entreprise, dessiné à partir de ses informations :
 * nom en haut, métier en bas, téléphones au centre (fond transparent).
 */
class StampService
{
    public const DEFAULT_COLOR = '#1E3A8A';

    /** Dessin en grand puis réduction : contours lissés. */
    private const DRAW_SIZE = 1200;
    private const OUTPUT_SIZE = 600;

    private const OUTER_RADIUS = 585;
    private const OUTER_THICKNESS = 16;
    private const INNER_RADIUS = 405;
    private const INNER_THICKNESS = 12;

    /** Rayon de la ligne médiane du texte circulaire. */
    private const TEXT_RADIUS = 495;
    private const ARC_FONT_SIZE = 72;

    /** Angle maximal occupé par chaque texte (le reste accueille les étoiles). */
    private const MAX_ARC = 140;

    private string $font;

    public function __construct()
    {
        $this->font = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');
    }

    /** Image PNG du tampon. */
    public function png(Tenant $tenant, ?string $color = null): string
    {
        $size = self::DRAW_SIZE;
        $image = $this->canvas($size);
        $ink = $this->allocate($image, $color ?: $tenant->stamp_color ?: self::DEFAULT_COLOR);
        $center = $size / 2;

        $this->ring($image, $center, self::OUTER_RADIUS, self::OUTER_THICKNESS, $ink);
        $this->ring($image, $center, self::INNER_RADIUS, self::INNER_THICKNESS, $ink);

        $this->arcText($image, $center, mb_strtoupper($tenant->name ?? ''), $ink, top: true);
        $this->arcText($image, $center, mb_strtoupper($tenant->trade ?: ($tenant->slogan ?? '')), $ink, top: false);

        $this->star($image, $center - self::TEXT_RADIUS, $center, 40, $ink);
        $this->star($image, $center + self::TEXT_RADIUS, $center, 40, $ink);

        $this->centerText($image, $center, $this->centerLines($tenant), $ink);

        $output = $this->canvas(self::OUTPUT_SIZE);
        imagecopyresampled($output, $image, 0, 0, 0, 0, self::OUTPUT_SIZE, self::OUTPUT_SIZE, $size, $size);

        ob_start();
        imagepng($output);

        return (string) ob_get_clean();
    }

    public function dataUri(Tenant $tenant, ?string $color = null): string
    {
        return 'data:image/png;base64,' . base64_encode($this->png($tenant, $color));
    }

    /** « TEL » et jusqu'à deux numéros ; à défaut, le sigle de l'entreprise. */
    private function centerLines(Tenant $tenant): array
    {
        $phones = array_slice(array_values(array_unique(array_filter(array_map(
            fn ($p) => SenegalPhone::format(SenegalPhone::normalize($p)) ?? $p,
            [$tenant->phone_call, $tenant->phone_whatsapp, $tenant->phone_other],
        )))), 0, 2);

        if ($phones) {
            return ['TEL', ...$phones];
        }

        return array_filter([mb_strtoupper($tenant->short_name ?? '')]);
    }

    private function canvas(int $size): GdImage
    {
        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagefilledrectangle($image, 0, 0, $size, $size, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return $image;
    }

    private function allocate(GdImage $image, string $hex): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocate($image, $r ?? 0, $g ?? 0, $b ?? 0);
    }

    /** Anneau : disque plein évidé en son centre. */
    private function ring(GdImage $image, float $center, int $radius, int $thickness, int $ink): void
    {
        imagefilledellipse($image, (int) $center, (int) $center, $radius * 2, $radius * 2, $ink);
        imagealphablending($image, false);
        $hole = ($radius - $thickness) * 2;
        imagefilledellipse($image, (int) $center, (int) $center, $hole, $hole, imagecolorallocatealpha($image, 255, 255, 255, 127));
        imagealphablending($image, true);
    }

    /**
     * Texte le long du cercle : en haut il se lit dans le sens horaire (lettres vers l'extérieur),
     * en bas de gauche à droite (lettres vers le centre).
     */
    private function arcText(GdImage $image, float $center, string $text, int $ink, bool $top): void
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return;
        }

        $radius = self::TEXT_RADIUS;
        $chars = mb_str_split($text);
        $fontSize = self::ARC_FONT_SIZE;
        $spacing = 6.0;

        // Réduit la police (jusqu'à un tiers) pour tenir dans l'arc disponible ;
        // au-delà, le texte déborde sur les étoiles plutôt que de chevaucher ses lettres
        $maxLength = deg2rad(self::MAX_ARC) * $radius;
        $length = $this->arcLength($chars, $fontSize, $spacing);
        if ($length > $maxLength) {
            $spacing = 3.0;
            $fontSize = max(self::ARC_FONT_SIZE / 3, $fontSize * $maxLength / $length);
            $length = $this->arcLength($chars, $fontSize, $spacing);
        }

        $capHeight = $this->capHeight($fontSize);
        // Ligne de base : le texte est centré sur le rayon médian
        $baseline = $top ? $radius - $capHeight / 2 : $radius + $capHeight / 2;
        $middle = $top ? -M_PI_2 : M_PI_2;
        $offset = 0.0;

        foreach ($chars as $char) {
            $width = $this->charWidth($char, $fontSize);
            $along = ($offset + $width / 2 - $length / 2) / $radius;
            $offset += $width + $spacing;

            // Angle de la lettre (sens horaire depuis l'axe horizontal, repère image)
            $phi = $top ? $middle + $along : $middle - $along;
            // Direction de lecture (tangente au cercle)
            [$dx, $dy] = $top ? [-sin($phi), cos($phi)] : [sin($phi), -cos($phi)];
            $x = $center + $baseline * cos($phi) - $dx * $width / 2;
            $y = $center + $baseline * sin($phi) - $dy * $width / 2;
            $angle = $top ? -rad2deg($phi) - 90 : 90 - rad2deg($phi);

            imagettftext($image, $fontSize, $angle, (int) round($x), (int) round($y), $ink, $this->font, $char);
        }
    }

    private function arcLength(array $chars, float $fontSize, float $spacing): float
    {
        $widths = array_map(fn ($c) => $this->charWidth($c, $fontSize), $chars);

        return array_sum($widths) + $spacing * (count($chars) - 1);
    }

    private function charWidth(string $char, float $fontSize): float
    {
        if ($char === ' ') {
            return $fontSize * 0.45;
        }
        $box = imagettfbbox($fontSize, 0, $this->font, $char);

        return abs($box[2] - $box[0]);
    }

    private function capHeight(float $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $this->font, 'H');

        return abs($box[7] - $box[1]);
    }

    /** Lignes centrées dans le disque intérieur, chacune aussi grande que possible. */
    private function centerText(GdImage $image, float $center, array $lines, int $ink): void
    {
        $lines = array_values($lines);
        if (!$lines) {
            return;
        }

        $maxWidth = self::INNER_RADIUS * 1.45;
        $fontSize = 74;
        foreach ($lines as $line) {
            $box = imagettfbbox($fontSize, 0, $this->font, $line);
            $width = abs($box[2] - $box[0]);
            if ($width > $maxWidth) {
                $fontSize = $fontSize * $maxWidth / $width;
            }
        }

        $lineHeight = $this->capHeight($fontSize) * 1.55;
        $top = $center - ($lineHeight * count($lines)) / 2 + $lineHeight * 0.78;

        foreach ($lines as $i => $line) {
            $box = imagettfbbox($fontSize, 0, $this->font, $line);
            $x = $center - abs($box[2] - $box[0]) / 2 - $box[0];
            imagettftext($image, $fontSize, 0, (int) round($x), (int) round($top + $i * $lineHeight), $ink, $this->font, $line);
        }
    }

    /** Étoile pleine à cinq branches. */
    private function star(GdImage $image, float $cx, float $cy, float $radius, int $ink): void
    {
        $points = [];
        for ($i = 0; $i < 10; $i++) {
            $r = $i % 2 === 0 ? $radius : $radius * 0.42;
            $a = -M_PI_2 + $i * M_PI / 5;
            $points[] = (int) round($cx + $r * cos($a));
            $points[] = (int) round($cy + $r * sin($a));
        }
        imagefilledpolygon($image, $points, $ink);
    }
}
