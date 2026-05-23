<?php
/**
 * Arabic Shaping and RTL Helper for PHP
 */

class ArabicShaper {
    // Basic Arabic Glyph Table (Simplified)
    private static $glyphs = [
        // Unicode => [Isolated, End, Middle, Beginning]
        0x0621 => [0xFE80, 0xFE80, 0xFE80, 0xFE80], // HAMZA
        0x0622 => [0xFE81, 0xFE82, 0xFE82, 0xFE81], // ALEF WITH MADDA ABOVE
        0x0623 => [0xFE83, 0xFE84, 0xFE84, 0xFE83], // ALEF WITH HAMZA ABOVE
        0x0624 => [0xFE85, 0xFE86, 0xFE86, 0xFE85], // WAW WITH HAMZA ABOVE
        0x0625 => [0xFE87, 0xFE88, 0xFE88, 0xFE87], // ALEF WITH HAMZA BELOW
        0x0626 => [0xFE89, 0xFE8A, 0xFE8B, 0xFE8C], // YEH WITH HAMZA ABOVE
        0x0627 => [0xFE8D, 0xFE8E, 0xFE8E, 0xFE8D], // ALEF
        0x0628 => [0xFE8F, 0xFE90, 0xFE91, 0xFE92], // BEH
        0x0629 => [0xFE93, 0xFE94, 0xFE94, 0xFE93], // TEH MARBUTA
        0x062A => [0xFE95, 0xFE96, 0xFE97, 0xFE98], // TEH
        0x062B => [0xFE99, 0xFE9A, 0xFE9B, 0xFE9C], // THEH
        0x062C => [0xFE9D, 0xFE9E, 0xFE9F, 0xFEA0], // JEEM
        0x062D => [0xFEA1, 0xFEA2, 0xFEA3, 0xFEA4], // HAH
        0x062E => [0xFEA5, 0xFEA6, 0xFEA7, 0xFEA8], // KHAH
        0x062F => [0xFEA9, 0xFEAA, 0xFEAA, 0xFEA9], // DAL
        0x0630 => [0xFEAB, 0xFEAC, 0xFEAC, 0xFEAB], // THAL
        0x0631 => [0xFEAD, 0xFEAE, 0xFEAE, 0xFEAD], // REH
        0x0632 => [0xFEAF, 0xFEB0, 0xFEB0, 0xFEAF], // ZAIN
        0x0633 => [0xFEB1, 0xFEB2, 0xFEB3, 0xFEB4], // SEEN
        0x0634 => [0xFEB5, 0xFEB6, 0xFEB7, 0xFEB8], // SHEEN
        0x0635 => [0xFEB9, 0xFEBA, 0xFEBB, 0xFEBC], // SAD
        0x0636 => [0xFEBD, 0xFEBE, 0xFEBF, 0xFEC0], // DAD
        0x0637 => [0xFEC1, 0xFEC2, 0xFEC3, 0xFEC4], // TAH
        0x0638 => [0xFEC5, 0xFEC6, 0xFEC7, 0xFEC8], // ZAH
        0x0639 => [0xFEC9, 0xFECA, 0xFECB, 0xFECC], // AIN
        0x063A => [0xFECD, 0xFECE, 0xFECF, 0xFED0], // GHAIN
        0x0641 => [0xFED1, 0xFED2, 0xFED3, 0xFED4], // FEH
        0x0642 => [0xFED5, 0xFED6, 0xFED7, 0xFED8], // QAF
        0x0643 => [0xFED9, 0xFEDA, 0xFEDB, 0xFEDC], // KAF
        0x0644 => [0xFEDD, 0xFEDE, 0xFEDF, 0xFEE0], // LAM
        0x0645 => [0xFEE1, 0xFEE2, 0xFEE3, 0xFEE4], // MEEM
        0x0646 => [0xFEE5, 0xFEE6, 0xFEE7, 0xFEE8], // NOON
        0x0647 => [0xFEE9, 0xFEEA, 0xFEEB, 0xFEEC], // HEH
        0x0648 => [0xFEED, 0xFEEE, 0xFEEE, 0xFEED], // WAW
        0x0649 => [0xFEEF, 0xFEF0, 0xFEF0, 0xFEEF], // ALEF MAKSURA
        0x064A => [0xFEF1, 0xFEF2, 0xFEF3, 0xFEF4], // YEH
        0x067E => [0xFB56, 0xFB57, 0xFB58, 0xFB59], // PEH
        0x0686 => [0xFB7A, 0xFB7B, 0xFB7C, 0xFB7D], // TCHEH
        0x06A9 => [0xFB8E, 0xFB8F, 0xFB90, 0xFB91], // KEHEH
        0x06AF => [0xFB92, 0xFB93, 0xFB94, 0xFB95], // GAF
        0x06CC => [0xFBFC, 0xFBFD, 0xFBFE, 0xFBFF], // FARSI YEH
    ];

    public static function prepare($text) {
        if (empty($text)) return '';
        if (is_array($text) || is_object($text)) return '[Complex Data]';
        $text = (string)$text;
        $text = str_replace('[object Object]', '', $text); // Strip JS object artifacts
        if (!self::containsArabic($text)) return $text;

        // Split into Arabic and Non-Arabic blocks
        // Arabic block: \x{0600}-\x{06FF} and common punctuation used in Arabic context
        $blocks = preg_split('/([\x{0600}-\x{06FF}]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = [];

        foreach ($blocks as $block) {
            if (empty($block)) continue;
            if (self::containsArabic($block)) {
                $result[] = self::shapeArabicBlock($block);
            } else {
                $result[] = $block;
            }
        }

        // For RTL rendering in FPDF, we need to reverse the order of blocks
        // and also handle the logical to visual conversion.
        return implode('', array_reverse($result));
    }

    private static function canConnectToNext($u) {
        // Letters that CANNOT connect to the next letter:
        // Hamza, Alef (all forms), Dal, Thal, Ra, Zay, Waw, Alef Maksura
        $noNext = [0x0621, 0x0622, 0x0623, 0x0624, 0x0625, 0x0627, 0x062F, 0x0630, 0x0631, 0x0632, 0x0648, 0x0649];
        return isset(self::$glyphs[$u]) && !in_array($u, $noNext);
    }

    private static function canBeConnectedFromPrev($u) {
        // Almost all Arabic letters can be connected from the previous letter except Hamza
        return isset(self::$glyphs[$u]) && $u != 0x0621;
    }

    private static function shapeArabicBlock($block) {
        // First handle LAM-ALEF ligatures (Logical Order)
        $block = preg_replace('/\x{0644}\x{0622}/u', "\u{FEF5}", $block); // MADDA
        $block = preg_replace('/\x{0644}\x{0623}/u', "\u{FEF7}", $block); // HAMZA ABOVE
        $block = preg_replace('/\x{0644}\x{0625}/u', "\u{FEF9}", $block); // HAMZA BELOW
        $block = preg_replace('/\x{0644}\x{0627}/u', "\u{FEFB}", $block); // PLAIN

        preg_match_all('/./us', $block, $ar);
        $chars = $ar[0];
        $numChars = count($chars);
        $newChars = [];

        for ($i = 0; $i < $numChars; $i++) {
            $u = self::utf8ToUnicode($chars[$i]);

            // Handling already ligated LAM-ALEF
            if ($u >= 0xFEF5 && $u <= 0xFEFC) {
                $prevU = ($i > 0) ? self::utf8ToUnicode($chars[$i - 1]) : 0;
                $connectedBefore = self::canConnectToNext($prevU);
                if ($connectedBefore) {
                    $newChars[] = self::unicodeToUtf8($u + 1); // End form is usually +1
                } else {
                    $newChars[] = self::unicodeToUtf8($u); // Isolated
                }
                continue;
            }

            if (!isset(self::$glyphs[$u])) {
                $newChars[] = $chars[$i];
                continue;
            }

            $prevU = ($i > 0) ? self::utf8ToUnicode($chars[$i - 1]) : 0;
            $nextU = ($i < $numChars - 1) ? self::utf8ToUnicode($chars[$i + 1]) : 0;

            // Connects to previous if previous letter can connect to next
            $connectedBefore = self::canConnectToNext($prevU);
            // Connects to next if THIS letter can connect to next AND next letter can be connected from prev
            $connectedAfter = self::canConnectToNext($u) && self::canBeConnectedFromPrev($nextU);

            if ($connectedBefore && $connectedAfter) {
                $newChars[] = self::unicodeToUtf8(self::$glyphs[$u][2]); // Middle
            } elseif ($connectedBefore) {
                $newChars[] = self::unicodeToUtf8(self::$glyphs[$u][1]); // End
            } elseif ($connectedAfter) {
                $newChars[] = self::unicodeToUtf8(self::$glyphs[$u][3]); // Beginning
            } else {
                $newChars[] = self::unicodeToUtf8(self::$glyphs[$u][0]); // Isolated
            }
        }
        return implode('', array_reverse($newChars));
    }

    public static function getAlign($text, $default = 'L') {
        return self::containsArabic($text) ? 'R' : $default;
    }

    private static function containsArabic($text) {
        return preg_match('/[\x{0600}-\x{06FF}]/u', (string)$text);
    }

    private static function utf8ToUnicode($char) {
        $len = strlen($char);
        if ($len == 1) return ord($char);
        if ($len == 2) return ((ord($char[0]) & 0x1F) << 6) | (ord($char[1]) & 0x3F);
        if ($len == 3) return ((ord($char[0]) & 0x0F) << 12) | ((ord($char[1]) & 0x3F) << 6) | (ord($char[2]) & 0x3F);
        if ($len == 4) return ((ord($char[0]) & 0x07) << 18) | ((ord($char[1]) & 0x3F) << 12) | ((ord($char[2]) & 0x3F) << 6) | (ord($char[3]) & 0x3F);
        return 0;
    }

    private static function unicodeToUtf8($u) {
        if ($u <= 0x7F) return chr($u);
        if ($u <= 0x7FF) return chr(0xC0 | ($u >> 6)) . chr(0x80 | ($u & 0x3F));
        if ($u <= 0xFFFF) return chr(0xE0 | ($u >> 12)) . chr(0x80 | (($u >> 6) & 0x3F)) . chr(0x80 | ($u & 0x3F));
        return chr(0xF0 | ($u >> 18)) . chr(0x80 | (($u >> 12) & 0x3F)) . chr(0x80 | (($u >> 6) & 0x3F)) . chr(0x80 | ($u & 0x3F));
    }
}
