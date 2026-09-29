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

namespace format_simple;

use format_simple\local\brand_palette;

/**
 * Tests for the brand colour palette.
 *
 * @package    format_simple
 * @copyright  2025 South African Theological Seminary <ict@sats.ac.za>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \format_simple\local\brand_palette
 */
final class brand_palette_test extends \advanced_testcase {
    /**
     * Hex colours are normalised, and anything else is refused rather than guessed at.
     */
    public function test_normalise_hex(): void {
        $this->assertSame('#aabbcc', brand_palette::normalise_hex('#ABC'));
        $this->assertSame('#2563eb', brand_palette::normalise_hex(' 2563EB '));
        $this->assertNull(brand_palette::normalise_hex('red'));
        $this->assertNull(brand_palette::normalise_hex('rgb(37, 99, 235)'));
        $this->assertNull(brand_palette::normalise_hex(''));
    }

    /**
     * With no colour chosen, nothing is emitted and the stylesheet's own blues apply.
     */
    public function test_default_scheme_emits_nothing(): void {
        $this->resetAfterTest();

        $this->assertSame('', brand_palette::get_css(\theme_config::load('boost')));

        set_config('colourscheme', brand_palette::SCHEME_DEFAULT, 'format_simple');
        set_config('brandcolour', '#aa0000', 'format_simple');
        $this->assertSame('', brand_palette::get_css(\theme_config::load('boost')));
    }

    /**
     * A custom colour produces a light and a dark rule carrying every token.
     */
    public function test_custom_scheme(): void {
        $this->resetAfterTest();
        set_config('colourscheme', brand_palette::SCHEME_CUSTOM, 'format_simple');
        set_config('brandcolour', '#2563eb', 'format_simple');

        $css = brand_palette::get_css(\theme_config::load('boost'));

        $this->assertStringContainsString('body.format-simple {', $css);
        $this->assertStringContainsString('body.format-simple[data-simple-theme="dark"] {', $css);
        // Already legible on white, so used exactly as given.
        $this->assertStringContainsString('--simple-brand-accent: #2563eb;', $css);

        $palette = brand_palette::derive('#2563eb');
        $this->assertSame(array_keys($palette['light']), array_keys($palette['dark']));
    }

    /**
     * An unusable custom colour falls back to the built-in palette.
     */
    public function test_custom_scheme_with_invalid_colour(): void {
        $this->resetAfterTest();
        set_config('colourscheme', brand_palette::SCHEME_CUSTOM, 'format_simple');
        set_config('brandcolour', 'not a colour', 'format_simple');

        $this->assertSame('', brand_palette::get_css(\theme_config::load('boost')));
    }

    /**
     * The theme scheme reads the theme's own brand colour.
     */
    public function test_theme_scheme(): void {
        $this->resetAfterTest();
        set_config('colourscheme', brand_palette::SCHEME_THEME, 'format_simple');

        set_config('brandcolor', '', 'theme_boost');
        $this->assertNull(brand_palette::resolve_brand_colour(\theme_config::load('boost')));

        set_config('brandcolor', '#AA0000', 'theme_boost');
        $this->assertSame('#aa0000', brand_palette::resolve_brand_colour(\theme_config::load('boost')));
    }

    /**
     * Whatever the brand colour, accent text stays legible in both modes.
     *
     * Covers the awkward range: pale, dark, saturated and grey.
     */
    public function test_accent_contrast(): void {
        $brands = ['#2563eb', '#0f6cbf', '#f5c400', '#f0f0f0', '#0a1f44', '#111111', '#00ff00', '#808080', '#b01030'];

        foreach ($brands as $brand) {
            $palette = brand_palette::derive($brand);

            $this->assertGreaterThanOrEqual(
                4.5,
                brand_palette::contrast_ratio($palette['light']['accent'], '#ffffff'),
                "{$brand}: light accent on white"
            );
            $this->assertGreaterThanOrEqual(
                5.5,
                brand_palette::contrast_ratio($palette['dark']['accent'], '#182234'),
                "{$brand}: dark accent on the dark surface"
            );

            // Icons on an accent fill need at least 3:1 in both modes.
            foreach (['light', 'dark'] as $mode) {
                $this->assertGreaterThanOrEqual(
                    3,
                    brand_palette::contrast_ratio($palette[$mode]['on-accent'], $palette[$mode]['accent']),
                    "{$brand}: on-accent in {$mode} mode"
                );
            }
        }
    }

    /**
     * In dark mode a light accent takes a dark glyph, which is the badge the old white icon failed.
     */
    public function test_dark_on_accent_is_dark_for_light_accent(): void {
        $palette = brand_palette::derive('#2563eb');

        $this->assertSame('#0f1420', $palette['dark']['on-accent']);
        $this->assertSame('#ffffff', $palette['light']['on-accent']);
    }
}
