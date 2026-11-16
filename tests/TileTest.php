<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Prüft die Instanz-Kacheln (HTML-SDK): Jedes Modul mit Kachel hat ein Skript, und jeder Text der Kachel
 * (translate() in der Visu, Schlüssel = englischer Text) ist in der locale.json des Moduls übersetzt.
 */
class TileTest extends TestCase
{
    public function testEveryTileModuleHasScript(): void
    {
        $Modules = $this->tileModules();
        $this->assertNotEmpty($Modules);
        foreach ($Modules as $Dir => $Script) {
            $this->assertFileExists($Script, $Dir . ': Skript der Kachel fehlt');
            $this->assertStringContainsString('FB.render', file_get_contents($Script), $Dir);
        }
    }

    public function testAllTileTextsAreTranslated(): void
    {
        $CoreKeys = $this->scriptKeys(__DIR__ . '/../libs/tile/tile.js');
        preg_match('/\$TileCoreTexts\s*=\s*\[(.*?)\];/s', file_get_contents(__DIR__ . '/../libs/FritzBoxTile.php'), $Match);
        $CoreKeys = array_merge($CoreKeys, $this->stringList($Match[1] ?? ''));
        $Missing = [];
        foreach ($this->tileModules() as $Dir => $Script) {
            $Module = file_get_contents($Dir . '/module.php');
            preg_match('/function GetTileTextKeys\(\): array\s*\{\s*return \[(.*?)\];/s', $Module, $Match);
            $Keys = array_unique(array_merge($CoreKeys, $this->scriptKeys($Script), $this->stringList($Match[1] ?? '')));
            $Translations = json_decode(file_get_contents($Dir . '/locale.json'), true)['translations']['de'] ?? [];
            foreach ($Keys as $Key) {
                if (!array_key_exists($Key, $Translations)) {
                    $Missing[] = basename($Dir) . ': ' . $Key;
                }
            }
        }
        $this->assertSame([], $Missing, "Fehlende Übersetzungen:\n" . implode("\n", $Missing));
    }

    public function testNoFixedColorsInTileCss(): void
    {
        // Farben nur aus den CSS-Variablen der Visu; feste Werte nur als Rückfall in var(--…, #…) im Kopf
        $Css = file_get_contents(__DIR__ . '/../libs/tile/tile.css');
        $Css = preg_replace('/var\(--[a-z-]+,\s*#[0-9a-f]{3,8}\)/i', '', $Css);
        $Css = preg_replace('/#[0-9a-f]{3,4}[0-9a-f]{0,4}(?=;|\s|\))/i', '', preg_replace('/box-shadow:[^;]+;/', '', $Css));
        $this->assertDoesNotMatchRegularExpression('/(?<![\w-])#[0-9a-f]{6}\b/i', $Css);
    }

    /**
     * Module mit Kachel (use \FritzBoxModul\Tile) => Pfad des Skripts.
     *
     * @return array<string, string>
     */
    private function tileModules(): array
    {
        $Result = [];
        foreach (glob(__DIR__ . '/../FritzBox */module.php') as $File) {
            $Code = file_get_contents($File);
            if (!str_contains($Code, 'use \FritzBoxModul\Tile;')) {
                continue;
            }
            $Dir = dirname($File);
            $Result[$Dir] = str_contains($Code, 'libs/tile/devices.js') ? __DIR__ . '/../libs/tile/devices.js' : $Dir . '/tile.js';
        }
        return $Result;
    }

    /**
     * Literale Schlüssel aus T('…') bzw. FB.T('…') eines Skripts.
     *
     * @return string[]
     */
    private function scriptKeys(string $File): array
    {
        preg_match_all("/\bT\('((?:[^'\\\\]|\\\\.)*)'\)/", file_get_contents($File), $Matches);
        return array_map(fn ($Key) => stripcslashes($Key), $Matches[1]);
    }

    /**
     * Strings aus einer PHP-Arrayliste ('a', 'b').
     *
     * @return string[]
     */
    private function stringList(string $List): array
    {
        preg_match_all("/'((?:[^'\\\\]|\\\\.)*)'/", $List, $Matches);
        return array_map(fn ($Key) => stripcslashes($Key), $Matches[1]);
    }
}
