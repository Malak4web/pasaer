<?php

namespace App\Support;

/**
 * أدوات للنص العربي: تطبيع للبحث، تقدير زمن القراءة، وتنسيق المدة.
 */
class Arabic
{
    /** إزالة التشكيل والتطويل وتوحيد الهمزات والتاء المربوطة والألف المقصورة */
    public static function normalize(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $text = mb_strtolower(strip_tags($text));
        $text = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $text = preg_replace('/\s+/u', ' ', $text);

        return trim($text);
    }

    /** دقائق القراءة التقريبية (~180 كلمة في الدقيقة للنص العربي) */
    public static function readMinutes(?string $text): int
    {
        $words = count(preg_split('/\s+/u', trim(strip_tags((string) $text)), -1, PREG_SPLIT_NO_EMPTY));

        return max(1, (int) ceil($words / 180));
    }

    /** "1:02:05" أو "28:14" → ثوانٍ */
    public static function parseDuration(?string $value): int
    {
        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }
        if (ctype_digit($value)) {
            return (int) $value;
        }

        $seconds = 0;
        foreach (explode(':', $value) as $part) {
            $seconds = $seconds * 60 + (int) $part;
        }

        return $seconds;
    }

    /** ثوانٍ → "28:14" أو "1:02:05" */
    public static function formatDuration(int $seconds): string
    {
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }

    /** استخراج معرّف يوتيوب من أي صيغة رابط، أو قبول المعرّف نفسه */
    public static function youtubeId(?string $input): ?string
    {
        $input = trim((string) $input);
        if (preg_match('/^[A-Za-z0-9_-]{11}$/', $input)) {
            return $input;
        }
        $pattern = '~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})~';

        return preg_match($pattern, $input, $m) ? $m[1] : null;
    }
}
