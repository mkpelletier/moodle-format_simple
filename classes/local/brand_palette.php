<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace format_simple\local;

/**
 * The site's brand colour, and the palette the format derives from it.
 *
 * An administrator chooses one colour, either directly or by pointing at the theme's own brand
 * colour. Everything the format paints in its accent (the tinted page wash, the active unit's band,
 * the filled Course Info badge, the rail buttons and popovers) is worked out from that one colour
 * here, separately for light and dark mode. The results go out as --simple-brand-* custom
 * properties on the body, which styles.css reads with its original blues as fallbacks, so a site
 * that chooses nothing looks exactly as it did before.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class brand_palette {
    /** Keep the format's own blue palette. */
    public const SCHEME_DEFAULT = 'default';

    /** Take the brand colour from the active theme's settings. */
    public const SCHEME_THEME = 'theme';

    /** Use the colour the administrator picked. */
    public const SCHEME_CUSTOM = 'custom';

    /** Page background in dark mode, as in styles.css. */
    private const DARK_BG = '#0f1420';

    /** Card and panel surface in dark mode, which accent text has to read against. */
    private const DARK_SURFACE = '#182234';

    /** Contrast accent text needs against white: WCAG AA for body text. */
    private const LIGHT_CONTRAST = 4.5;

    /**
     * Contrast accent text needs against the dark surface.
     *
     * Above AA on purpose: the stock dark accent, #5b9bf5, sits at about 5.6:1, and a brand colour
     * should not leave dark mode any harder to read than it was.
     */
    private const DARK_CONTRAST = 5.5;

    /**
     * The brand colour the site has chosen, if any.
     *
     * @param \theme_config $theme The theme in use, consulted when the scheme defers to it.
     * @return string|null A lowercase #rrggbb value, or null to keep the built-in palette.
     */
    public static function resolve_brand_colour(\theme_config $theme): ?string {
        $scheme = get_config('format_simple', 'colourscheme') ?: self::SCHEME_DEFAULT;

        if ($scheme === self::SCHEME_CUSTOM) {
            return self::normalise_hex((string) get_config('format_simple', 'brandcolour'));
        }

        if ($scheme === self::SCHEME_THEME) {
            // A child theme usually leaves the brand colour to its parent, so walk up the chain.
            foreach (array_merge([$theme->name], (array) $theme->parents) as $name) {
                $colour = self::normalise_hex((string) get_config('theme_' . $name, 'brandcolor'));
                if ($colour !== null) {
                    return $colour;
                }
            }
        }

        return null;
    }

    /**
     * The stylesheet that carries the brand palette onto the page.
     *
     * @param \theme_config $theme The theme in use.
     * @return string CSS rules, or an empty string when the built-in palette applies.
     */
    public static function get_css(\theme_config $theme): string {
        $brand = self::resolve_brand_colour($theme);
        if ($brand === null) {
            return '';
        }

        $palette = self::derive($brand);

        // The dark rule has to define every token the light one does, or the light value would
        // inherit straight through into dark mode.
        return self::rule('body.format-simple', $palette['light'])
            . self::rule('body.format-simple[data-simple-theme="dark"]', $palette['dark']);
    }

    /**
     * Work out the full palette for one brand colour.
     *
     * Each mode gets the same set of tokens:
     * - accent: text, bars and filled badges. Shifted, if it has to be, until it reads as text.
     * - accent-strong: a step further from the background than accent, for the selected tile.
     * - accent-deep: a dark shade that white text sits on, for the course banner.
     * - accent-light: a tint for the active unit's band and the Course Info badge.
     * - accent-border: a mid tint for tile borders.
     * - bg: the wash behind the whole format.
     * - on-accent: text and icons drawn on an accent fill.
     * - glow and edge: the accent at low opacity, for a shadow and a hairline border.
     *
     * Tints come from the brand colour as given, so a pale brand still gives pale tints; accent
     * text comes from the contrast-adjusted colour, so it is always legible.
     *
     * @param string $brand A #rrggbb value.
     * @return array{light: array<string, string>, dark: array<string, string>}
     */
    public static function derive(string $brand): array {
        $white = [255, 255, 255];
        $black = [0, 0, 0];
        $darkbg = self::to_rgb(self::DARK_BG);
        $darksurface = self::to_rgb(self::DARK_SURFACE);
        $colour = self::to_rgb($brand);

        $lightaccent = self::shift_to_contrast($colour, $white, self::LIGHT_CONTRAST, -1);
        $darkaccent = self::shift_to_contrast($colour, $darksurface, self::DARK_CONTRAST, 1);
        $deep = self::mix($lightaccent, $black, 0.65);

        $light = [
            'accent' => self::to_hex($lightaccent),
            'accent-strong' => self::to_hex(self::mix($lightaccent, $black, 0.8)),
            'accent-deep' => self::to_hex($deep),
            'accent-light' => self::to_hex(self::mix($colour, $white, 0.15)),
            'accent-border' => self::to_hex(self::mix($colour, $white, 0.4)),
            'bg' => self::to_hex(self::mix($colour, $white, 0.08)),
            'on-accent' => self::to_hex(self::best_text_on($lightaccent, [$white, $darkbg])),
            'glow' => self::to_rgba($lightaccent, 0.3),
            'edge' => self::to_rgba($lightaccent, 0.06),
        ];

        $dark = [
            'accent' => self::to_hex($darkaccent),
            'accent-strong' => self::to_hex(self::mix($darkaccent, $white, 0.65)),
            'accent-deep' => self::to_hex($deep),
            'accent-light' => self::to_hex(self::mix($darkaccent, $darkbg, 0.2)),
            'accent-border' => self::to_hex(self::mix($darkaccent, $darkbg, 0.45)),
            'bg' => self::DARK_BG,
            'on-accent' => self::to_hex(self::best_text_on($darkaccent, [$white, $darkbg])),
            'glow' => self::to_rgba($darkaccent, 0.3),
            'edge' => self::to_rgba($darkaccent, 0.08),
        ];

        return ['light' => $light, 'dark' => $dark];
    }

    /**
     * Reduce a colour setting to a lowercase #rrggbb value.
     *
     * Only hex is understood. The theme's colour picker also accepts names and rgb() values;
     * those are ignored rather than guessed at, and the built-in palette applies instead.
     *
     * @param string $value The raw setting.
     * @return string|null The normalised colour, or null when it is not a hex colour.
     */
    public static function normalise_hex(string $value): ?string {
        $value = strtolower(trim($value));

        if (preg_match('/^#?([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        }
        if (preg_match('/^#?([0-9a-f]{6})$/', $value, $m)) {
            return '#' . $m[1];
        }

        return null;
    }

    /**
     * WCAG contrast ratio between two colours.
     *
     * @param string $first A #rrggbb value.
     * @param string $second A #rrggbb value.
     * @return float From 1 (identical) to 21 (black on white).
     */
    public static function contrast_ratio(string $first, string $second): float {
        return self::contrast(self::to_rgb($first), self::to_rgb($second));
    }

    /**
     * One CSS rule declaring the given tokens.
     *
     * @param string $selector The selector.
     * @param array<string, string> $tokens Token name => value, all generated here, never raw input.
     * @return string
     */
    private static function rule(string $selector, array $tokens): string {
        $declarations = '';
        foreach ($tokens as $name => $value) {
            $declarations .= "--simple-brand-{$name}: {$value};";
        }
        return "{$selector} {{$declarations}}\n";
    }

    /**
     * Move a colour's lightness until it reaches a contrast target against a background.
     *
     * Working in HSL keeps the hue and saturation, so the result still reads as the brand colour
     * rather than as a greyed-out version of it. A colour that already meets the target is
     * returned untouched.
     *
     * @param int[] $colour RGB.
     * @param int[] $against RGB of the background.
     * @param float $target The contrast ratio to reach.
     * @param int $direction -1 to darken, 1 to lighten.
     * @return int[] RGB.
     */
    private static function shift_to_contrast(array $colour, array $against, float $target, int $direction): array {
        [$h, $s, $l] = self::to_hsl($colour);

        // Always ends: black against white and white against the dark surface both clear any target used here.
        while (self::contrast($colour, $against) < $target && $l > 0 && $l < 1) {
            $l = max(0, min(1, $l + 0.01 * $direction));
            $colour = self::from_hsl($h, $s, $l);
        }

        return $colour;
    }

    /**
     * Whichever of the candidates contrasts best with a fill.
     *
     * @param int[] $fill RGB.
     * @param int[][] $candidates RGB values, the first preferred on a tie.
     * @return int[] RGB.
     */
    private static function best_text_on(array $fill, array $candidates): array {
        $best = $candidates[0];
        foreach ($candidates as $candidate) {
            if (self::contrast($fill, $candidate) > self::contrast($fill, $best)) {
                $best = $candidate;
            }
        }
        return $best;
    }

    /**
     * Blend two colours.
     *
     * @param int[] $colour RGB.
     * @param int[] $base RGB.
     * @param float $amount How much of $colour to keep, from 0 to 1.
     * @return int[] RGB.
     */
    private static function mix(array $colour, array $base, float $amount): array {
        $mixed = [];
        for ($i = 0; $i < 3; $i++) {
            $mixed[] = (int) round($colour[$i] * $amount + $base[$i] * (1 - $amount));
        }
        return $mixed;
    }

    /**
     * WCAG contrast ratio between two RGB colours.
     *
     * @param int[] $first RGB.
     * @param int[] $second RGB.
     * @return float
     */
    private static function contrast(array $first, array $second): float {
        $a = self::luminance($first);
        $b = self::luminance($second);
        return (max($a, $b) + 0.05) / (min($a, $b) + 0.05);
    }

    /**
     * WCAG relative luminance.
     *
     * @param int[] $rgb RGB.
     * @return float From 0 to 1.
     */
    private static function luminance(array $rgb): float {
        $channels = array_map(
            fn(int $channel): float => $channel / 255 <= 0.03928
                ? $channel / 255 / 12.92
                : (($channel / 255 + 0.055) / 1.055) ** 2.4,
            $rgb
        );
        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    /**
     * Parse a #rrggbb value.
     *
     * @param string $hex A #rrggbb value.
     * @return int[] RGB.
     */
    private static function to_rgb(string $hex): array {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    /**
     * Format an RGB colour as #rrggbb.
     *
     * @param int[] $rgb RGB.
     * @return string
     */
    private static function to_hex(array $rgb): string {
        return sprintf('#%02x%02x%02x', ...array_map(fn($c) => max(0, min(255, (int) round($c))), $rgb));
    }

    /**
     * Format an RGB colour with an alpha channel.
     *
     * @param int[] $rgb RGB.
     * @param float $alpha From 0 to 1.
     * @return string
     */
    private static function to_rgba(array $rgb, float $alpha): string {
        return sprintf('rgba(%d, %d, %d, %s)', $rgb[0], $rgb[1], $rgb[2], $alpha);
    }

    /**
     * Convert RGB to HSL.
     *
     * @param int[] $rgb RGB.
     * @return float[] Hue in degrees, saturation and lightness from 0 to 1.
     */
    private static function to_hsl(array $rgb): array {
        [$r, $g, $b] = array_map(fn($c) => $c / 255, $rgb);
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;
        $d = $max - $min;

        if ($d == 0) {
            return [0.0, 0.0, $l];
        }

        $s = $d / (1 - abs(2 * $l - 1));
        if ($max == $r) {
            $h = 60 * fmod(($g - $b) / $d, 6);
        } else if ($max == $g) {
            $h = 60 * (($b - $r) / $d + 2);
        } else {
            $h = 60 * (($r - $g) / $d + 4);
        }

        return [$h < 0 ? $h + 360 : $h, $s, $l];
    }

    /**
     * Convert HSL to RGB.
     *
     * @param float $h Hue in degrees.
     * @param float $s Saturation from 0 to 1.
     * @param float $l Lightness from 0 to 1.
     * @return int[] RGB.
     */
    private static function from_hsl(float $h, float $s, float $l): array {
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        return array_map(fn($v) => (int) round(($v + $m) * 255), [$r, $g, $b]);
    }
}
