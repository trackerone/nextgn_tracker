<?php

declare(strict_types=1);

namespace App\Support\Languages;

final class LanguageMetadataOptions
{
    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return [
            'English',
            'German',
            'French',
            'Portuguese',
            'Spanish',
            'Dutch',
            'Finnish',
            'Russian',
            'Vietnamese',
            'Chinese',
            'Italian',
            'Swedish',
            'Norwegian',
            'Norwegian Bokmål',
            'Danish',
            'Japanese',
            'Thai',
            'Korean',
            'Greek',
            'Arabic',
            'Indonesian',
            'Polish',
            'Turkish',
            'Bulgarian',
            'Hebrew',
            'Romanian',
            'Icelandic',
            'Hungarian',
            'Czech',
            'Estonian',
            'Hindi',
            'Lithuanian',
            'Latvian',
            'Malay',
            'Slovak',
            'Slovenian',
            'Tamil',
            'Telugu',
            'Ukrainian',
            'Croatian',
            'Persian',
            'Panjabi',
        ];
    }

    /**
     * @return list<string>
     */
    public static function examples(): array
    {
        return [
            'English',
            'Japanese',
            'Spanish',
            'German',
            'Danish',
            'Norwegian Bokmål',
            'Panjabi',
        ];
    }
}
