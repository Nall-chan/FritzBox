<?php

declare(strict_types=1);

namespace FritzBox;

/**
 * Liest Senderlisten im Format M3U (Extended M3U der FritzBox: `#EXTINF:0,<Name>`, optional `#EXTVLCOPT:…`, dann die Adresse).
 */
class M3U
{
    /**
     * @param string $Content Inhalt der m3u-Datei.
     *
     * @return array<int, array{name: string, url: string}> Einträge in der Reihenfolge der Datei.
     */
    public static function Parse(string $Content): array
    {
        $Result = [];
        $Name = '';
        foreach (preg_split('/\r\n|\r|\n/', $Content) as $Line) {
            $Line = trim($Line);
            if ($Line === '') {
                continue;
            }
            if (str_starts_with($Line, '#EXTINF:')) {
                $Comma = strpos($Line, ',');
                $Name = ($Comma === false) ? '' : trim(substr($Line, $Comma + 1));
                continue;
            }
            if (str_starts_with($Line, '#')) {
                continue;
            }
            $Result[] = ['name' => ($Name !== '') ? $Name : $Line, 'url' => $Line];
            $Name = '';
        }
        return $Result;
    }
}
