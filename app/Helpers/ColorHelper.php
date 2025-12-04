<?php

namespace App\Helpers;

class ColorHelper
{
    public static function isDark($hexColor)
    {
        $hexColor = str_replace('#', '', $hexColor);

        if (strlen($hexColor) === 3) {
            $hexColor = $hexColor[0].$hexColor[0] . $hexColor[1].$hexColor[1] . $hexColor[2].$hexColor[2];
        }

        $r = hexdec(substr($hexColor, 0, 2));
        $g = hexdec(substr($hexColor, 2, 2));
        $b = hexdec(substr($hexColor, 4, 2));

        // Fórmula de luminância
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b);

        return $luminance < 140; // abaixo disso é escura
    }
}
