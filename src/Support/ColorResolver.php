<?php

declare(strict_types=1);

namespace Relaticle\Flowforge\Support;

use Filament\Support\Colors\Color;
use Filament\Support\Facades\FilamentColor;

use function Filament\Support\get_color_css_variables;

/**
 * Resolves color values from various formats into a consistent structure.
 *
 * Supports:
 * - Semantic colors (primary, danger, etc.)
 * - Color constants (Color::Red arrays)
 * - Tailwind color names (red, blue, etc.)
 * - Hex colors (#ff0000)
 */
class ColorResolver
{
    /**
     * Resolve a color value to either a semantic name or color array.
     *
     * @return string|array{50: string, 100: string, 200: string, 300: string, 400: string, 500: string, 600: string, 700: string, 800: string, 900: string, 950: string}|null
     */
    public static function resolve(mixed $color): string | array | null
    {
        if (blank($color)) {
            return null;
        }

        // If it's already a Color constant array (has numeric keys for shades)
        if (is_array($color) && isset($color[500])) {
            return $color;
        }

        // If it's a string, check various formats
        if (is_string($color)) {
            // Check if it's a registered Filament semantic color
            $filamentColors = FilamentColor::getColors();
            if (isset($filamentColors[$color])) {
                return $color; // Return semantic name for native Filament badge
            }

            // Try to get Tailwind color by name (case-insensitive)
            $tailwindColor = self::getTailwindColor($color);
            if ($tailwindColor !== null) {
                return $tailwindColor;
            }

            // Try to parse as hex color (validate format first)
            if (str_starts_with($color, '#') && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
                try {
                    return Color::hex($color);
                } catch (\Exception) {
                    // Invalid hex, will fall through to null
                }
            }
        }

        return null;
    }

    // A hex stays exactly as given: Filament's generated palette keeps only its hue at shade 500.
    public static function dotStyle(mixed $color): ?string
    {
        $resolved = self::resolve($color);

        if ($resolved === null) {
            return null;
        }

        if (is_string($color) && preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return '--color-500:' . strtolower($color);
        }

        return get_color_css_variables($resolved, shades: [500]);
    }

    /**
     * Check if the resolved color is a semantic Filament color.
     */
    public static function isSemantic(mixed $color): bool
    {
        return is_string($color) && isset(FilamentColor::getColors()[$color]);
    }

    /**
     * Get a Tailwind color constant by name.
     */
    private static function getTailwindColor(string $name): ?array
    {
        $colorName = ucfirst(strtolower($name));

        // Use reflection to check if the Color class has this constant
        if (defined("Filament\\Support\\Colors\\Color::{$colorName}")) {
            return constant("Filament\\Support\\Colors\\Color::{$colorName}");
        }

        return null;
    }
}
