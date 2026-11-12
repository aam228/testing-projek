<?php

namespace App\Helpers;

class ProductImageHelper
{
    /**
     * Clean and normalize image path:
     * 1. If full URL (http/https), preserve as-is (external domain).
     * 2. If relative path, strip legacy 'uploads/' prefix for local images.
     */
    public static function cleanImageName($val): string
    {
        if (!is_string($val)) {
            return '';
        }
        $val = trim($val);
        if (empty($val) || $val === '||||||') {
            return '';
        }

        // 1. Kondisi URL penuh (domain luar/beda) -> biarkan utuh apa adanya
        if (preg_match('#^https?://#i', $val)) {
            return $val;
        }

        // 2. Kondisi path lokal -> bersihkan prefix uploads/ jika ada
        $val = preg_replace('#^/?uploads?/#i', '', $val);

        return $val;
    }

    /**
     * Parse main image and sub images into a clean, deduplicated array of image strings.
     * Handles:
     * - Legacy pipe-separated strings (e.g. "img1.jpg|img2.jpg")
     * - JSON array strings (e.g. '["https://...","https://..."]')
     * - Filenames with brackets (e.g. "[910]_haze_eternal.webp")
     * - Empty strings, nulls, and '||||||' placeholders
     */
    public static function parseImages($mainImage, $subImages = null): array
    {
        $images = [];

        $mainCleaned = self::cleanImageName($mainImage);
        if (!empty($mainCleaned)) {
            $images[] = $mainCleaned;
        }

        $subTrimmed = is_string($subImages) ? trim($subImages) : '';
        if (!empty($subTrimmed) && $subTrimmed !== '||||||') {
            $parsedList = [];
            if (str_starts_with($subTrimmed, '[') && str_ends_with($subTrimmed, ']')) {
                $decoded = json_decode($subTrimmed, true);
                if (is_array($decoded)) {
                    $parsedList = $decoded;
                }
            }
            if (empty($parsedList)) {
                $parsedList = explode('|', $subTrimmed);
            }
            foreach ($parsedList as $img) {
                $imgCleaned = self::cleanImageName($img);
                if (!empty($imgCleaned)) {
                    $images[] = $imgCleaned;
                }
            }
        }

        // Deduplicate while preserving original order
        $unique = [];
        foreach ($images as $img) {
            if (!in_array($img, $unique, true)) {
                $unique[] = $img;
            }
        }
        return $unique;
    }

    /**
     * Get the single primary image from main and sub images.
     */
    public static function parseSingleImage($mainImage, $subImages = null): string
    {
        $all = self::parseImages($mainImage, $subImages);
        return $all[0] ?? '';
    }
}
