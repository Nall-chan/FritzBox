<?php

declare(strict_types=1);

namespace FritzBoxModul;

// Vor 9.1 gibt es die maximierte Kachel über das HTML-SDK nicht, dann gilt die normale Kachel (HTML).
foreach (['INSTANCE_VISUALIZATION_TYPE_HTML' => 1, 'INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN' => 1, 'VARIABLE_ACTION_DISABLED' => 1] as $ConstantName => $ConstantValue) {
    if (!defined($ConstantName)) {
        define($ConstantName, $ConstantValue);
    }
}

/**
 * Instanz-Kachel für die Kachel-Visualisierung (HTML-SDK).
 *
 * Gemeinsamer Unterbau aller Kacheln der Library: `libs/tile/tile.html` (Gerüst), `tile.css` und `tile.js` (Bausteine)
 * sowie das Skript des Moduls (`tile.js` im Modulordner). Das Modul liefert seine Daten über `GetTileData()`;
 * die Kachel erhält sie beim Aufbau eingebettet und danach per `UpdateVisualizationValue` (`PushTile()`).
 * Statusvariablen gehen über `TileVariable()` in die Daten: fehlt die Variable (in der Konfiguration abgewählt),
 * fehlt auch das Element in der Kachel.
 */
trait Tile
{
    /**
     * Flags für JSON, das in eine Script-Umgebung der Kachel eingebettet wird.
     */
    private static int $TileJsonFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

    /**
     * Texte der gemeinsamen Bausteine (tile.js).
     *
     * @var string[]
     */
    private static array $TileCoreTexts = ['Entries', 'All', 'Cancel', 'OK', 'Show chart', 'Link copied'];

    /**
     * Interne Funktion des SDK.
     * Liefert die Kachel mit den aktuellen Daten.
     */
    public function GetVisualizationTile(): string
    {
        $Config = [
            'instance' => $this->InstanceID,
            'texts'    => $this->GetTileTexts(),
            'data'     => $this->GetTileData()
        ];
        $Dir = __DIR__ . '/tile/';
        return strtr((string) file_get_contents($Dir . 'tile.html'), [
            '/*CONFIG*/null' => json_encode($Config, self::$TileJsonFlags),
            '/*STYLE*/'      => (string) file_get_contents($Dir . 'tile.css'),
            '/*CORE*/'       => (string) file_get_contents($Dir . 'tile.js'),
            '/*MODULE*/'     => (string) file_get_contents($this->GetTileScriptFile())
        ]);
    }

    /**
     * Daten des Moduls für die Kachel.
     */
    abstract protected function GetTileData(): array;

    /**
     * Englische Texte des Modulskripts (Schlüssel für translate() der Kachel, Rückfall ohne Visu).
     *
     * @return string[]
     */
    abstract protected function GetTileTextKeys(): array;

    /**
     * Meldet die Instanz als Kachel an (normal und maximiert). Ob die Kachel oder die Liste der Variablen erscheint,
     * stellt der Nutzer je Kachel in der Visualisierung ein.
     */
    protected function RegisterTile(): void
    {
        $this->SetVisualizationType(INSTANCE_VISUALIZATION_TYPE_HTML_FULLSCREEN);
    }

    /**
     * Sendet die aktuellen Daten an die Kachel.
     */
    protected function PushTile(): void
    {
        $this->UpdateVisualizationValue(json_encode(['type' => 'state', 'data' => $this->GetTileData()], self::$TileJsonFlags));
    }

    /**
     * Zeigt einen Hinweis in der Kachel, z.B. wenn eine Voraussetzung für eine Aktion fehlt.
     * Fehler der FritzBox laufen dagegen über die Fehlerausgabe der Visualisierung (echo/trigger_error).
     *
     * @param string $Text Übersetzter Text.
     */
    protected function PushTileNotice(string $Text): void
    {
        $this->UpdateVisualizationValue(json_encode(['type' => 'notice', 'text' => $Text], self::$TileJsonFlags));
    }

    /**
     * Bedienaktionen, die alle Kacheln kennen.
     *
     * @return bool true, wenn die Aktion zur Kachel gehörte.
     */
    protected function ProcessTileAction(string $Ident, mixed $Value): bool
    {
        if ($Ident == \FritzBox\Tile\Action::Reload) {
            $this->PushTile();
            return true;
        }
        return false;
    }

    /**
     * Bedienbar, wenn die Variable eine Aktion hat (Standard- oder eigene Aktion) und der Nutzer sie nicht deaktiviert hat.
     *
     * @param int $VariableId ID der Statusvariable.
     */
    protected function TileVariableHasAction(int $VariableId): bool
    {
        if (!IPS_VariableExists($VariableId)) {
            return false;
        }
        return HasAction($VariableId) && (IPS_GetVariable($VariableId)['VariableCustomAction'] != VARIABLE_ACTION_DISABLED);
    }

    /**
     * Prüft ein Gerät der Gerätekachel (DECT, Powerline) vor dem Update: bekannt (Kennung aus der FritzBox) und online.
     * Die FritzBox nimmt den Update-Befehl auch für Geräte an, die offline sind, und meldet keinen Fehler.
     *
     * @param array  $Devices Geräte der Kachel (je Eintrag `ident` der Gerätevariable und `key` der FritzBox).
     * @param string $Key     Kennung des Gerätes aus der Kachel.
     */
    protected function TileDeviceReadyForUpdate(array $Devices, string $Key): bool
    {
        $Index = array_search($Key, array_column($Devices, 'key'), true);
        if ($Index === false) {
            trigger_error($this->Translate('Unknown device.'), E_USER_NOTICE);
            return false;
        }
        $VariableId = @$this->GetIDForIdent($Devices[$Index]['ident']);
        if ($VariableId && !GetValueBoolean($VariableId)) {
            trigger_error($this->Translate('The device is offline, the update cannot be started.'), E_USER_NOTICE);
            return false;
        }
        return true;
    }

    /**
     * Beschreibung einer Statusvariable für die Kachel: Wert, formatierter Wert, bedienbar und Farbe/Symbol laut Darstellung.
     *
     * @param string $Ident Ident der Statusvariable.
     *
     * @return array|null null, wenn die Variable nicht existiert (abgewählt).
     */
    protected function TileVariable(string $Ident): ?array
    {
        $VariableId = @$this->GetIDForIdent($Ident);
        if (!$VariableId) {
            return null;
        }
        $Variable = IPS_GetVariable($VariableId);
        $Value = $this->GetValue($Ident);
        $Result = [
            'id'     => $VariableId,
            'ident'  => $Ident,
            'name'   => IPS_GetName($VariableId),
            'value'  => $Value,
            'text'   => GetValueFormatted($VariableId),
            'action' => $this->TileVariableHasAction($VariableId),
            'color'  => -1,
            'icon'   => ''
        ];
        $Presentation = $this->GetEffectivePresentation($Variable);
        if (is_bool($Value)) {
            // Schalter: Leuchtfarbe und Symbol je Zustand
            if ($Value && isset($Presentation['GLOW_COLOR'])) {
                $Result['color'] = (int) $Presentation['GLOW_COLOR'];
            }
            $Result['icon'] = (string) ($Value ? ($Presentation['ICON_TRUE'] ?? '') : (!empty($Presentation['USE_ICON_FALSE']) ? ($Presentation['ICON_FALSE'] ?? '') : ($Presentation['ICON_TRUE'] ?? '')));
        } elseif (isset($Presentation['OPTIONS'])) {
            // Aufzählung: Farbe und Symbol des aktuellen Wertes
            $Options = is_string($Presentation['OPTIONS']) ? json_decode($Presentation['OPTIONS'], true) : $Presentation['OPTIONS'];
            foreach (is_array($Options) ? $Options : [] as $Option) {
                if (isset($Option['Value']) && ($Option['Value'] == $Value)) {
                    if (!empty($Option['ColorActive'])) {
                        $Result['color'] = (int) ($Option['ColorValue'] ?? -1);
                    }
                    if (!empty($Option['IconActive'])) {
                        $Result['icon'] = (string) ($Option['IconValue'] ?? '');
                    }
                    break;
                }
            }
        } else {
            if (isset($Presentation['COLOR'])) {
                $Result['color'] = (int) $Presentation['COLOR'];
            }
            $Result['icon'] = (string) ($Presentation['ICON'] ?? '');
        }
        return $Result;
    }

    /**
     * Ist die Variable im Archiv geloggt? Dann kann die Kachel das Diagramm der Variable öffnen.
     *
     * @param int $VariableId ID der Variable.
     */
    protected function IsTileVariableLogged(int $VariableId): bool
    {
        $Archives = IPS_GetInstanceListByModuleID('{43192F0B-135B-4CE7-A0A7-1475603F3060}');
        if (count($Archives) == 0) {
            return false;
        }
        return (bool) @AC_GetLoggingStatus($Archives[0], $VariableId);
    }

    /**
     * Pfad zum Skript des Moduls (tile.js im Ordner des Moduls); Module mit gemeinsamem Skript überschreiben das.
     */
    protected function GetTileScriptFile(): string
    {
        return dirname((new \ReflectionClass($this))->getFileName()) . '/tile.js';
    }

    /**
     * Darstellung, die gerade wirkt: eigene Darstellung des Nutzers, sonst die der Library.
     */
    private function GetEffectivePresentation(array $Variable): array
    {
        $Custom = $Variable['VariableCustomPresentation'] ?? [];
        if (is_array($Custom) && (count($Custom) > 0)) {
            return $Custom;
        }
        $Standard = $Variable['VariablePresentation'] ?? [];
        return is_array($Standard) ? $Standard : [];
    }

    /**
     * Übersetzte Texte der Kachel (Rückfall, wenn translate() der Visu fehlt).
     *
     * @return string[] Englischer Text => übersetzter Text.
     */
    private function GetTileTexts(): array
    {
        $Texts = [];
        foreach (array_merge(self::$TileCoreTexts, $this->GetTileTextKeys()) as $Text) {
            $Texts[$Text] = $this->Translate($Text);
        }
        return $Texts;
    }
}
