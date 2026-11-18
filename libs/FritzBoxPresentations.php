<?php

declare(strict_types=1);

/**
 * Darstellungen (Presentations) der Statusvariablen.
 * Ersetzt die bis 0.94 genutzten Variablenprofile. Die Module übergeben weiterhin den früheren
 * Profilnamen (z.B. 'FB.kBit', '~Switch'), Presentation::Get liefert die passende Darstellung.
 * Ohne Abhängigkeit von Symcon, geprüft in tests/PresentationTest.php gegen tests/presentations.json.
 */

namespace FritzBox;

class Presentation
{
    // GUIDs der Darstellungen (IPS_GetPresentations)
    public const ValuePresentation = '{3319437D-7CDE-699D-750A-3C6A3841FA75}';
    public const Enumeration = '{52D9E126-D7D2-2CBB-5E62-4CF7BA7C5D82}';
    public const Switch = '{60AE6B26-B3E2-BDB1-A3A1-BE232940664B}';
    public const DateTime = '{497C4845-27FA-6E4F-AE37-5D951D3BDBF9}';
    public const WebContent = '{9DE1D610-5106-97FB-714D-1AADEDF8377A}';

    /**
     * Eigene Profile bis 0.94. Werden gelöscht, sobald keine Variable sie mehr nutzt.
     */
    public const LegacyProfiles = [
        'FB.kBit', 'FB.dB', 'FB.dBm', 'FB.dBm.Float', 'FB.Intensity', 'FB.Speed', 'FB.MByte', 'FB.kbs', 'FB.MBits',
        'FB.ActiveInactive', 'FB.StartStop', 'FB.DynDnyState', 'FB.UpdateState', 'FB.BuildType', 'FB.AutoUpdateMode',
        'FB.UpdateSuccessful', 'FB.AHA.Present', 'FB.AHA.Valid', 'FB.AHA.Mode', 'FB.AHA.State', 'FB.AHA.VentilState',
        'FB.AvmAccessType', 'FB.AccessType', 'FB.Connect', 'FB.ConnectionStatus',
        // bis ca. 0.76 von WAN Common Interface und WAN DSL Link genutzt
        'FB.LinkState'
    ];

    /**
     * Zahlenwerte: Profilname => [Suffix, Nachkommastellen, Min, Max]. Min = Max = null: ohne Bereich.
     */
    private const Values = [
        'FB.kBit'         => [' kBit/s', 0, null, null],
        'FB.dB'           => [' dB', 1, null, null],
        'FB.dBm'          => [' dBm', 0, null, null],
        'FB.dBm.Float'    => [' dBm', 2, null, null],
        'FB.Intensity'    => [' %', 0, 0, 100],
        'FB.Speed'        => ['%', 2, 0, 100],
        'FB.MByte'        => [' MB', 2, null, null],
        'FB.kbs'          => [' kb/s', 2, null, null],
        'FB.MBits'        => [' MBit/s', 0, null, null],
        // ab 1.0, ohne früheres Profil
        'FB.MBits.Float'  => [' Mbit/s', 2, null, null],
        'FB.GByte'        => [' GB', 2, null, null],
        '~Intensity.100'  => [' %', 0, 0, 100],
        '~Temperature'    => [' °C', 1, null, null],
        '~Watt'           => [' W', 1, null, null],
        '~Electricity.Wh' => [' Wh', 2, null, null],
    ];

    /**
     * Aufzählungen: Profilname => Liste [Wert, Beschriftung (englisch, wird übersetzt), Farbe oder -1].
     */
    private const Options = [
        'FB.ActiveInactive' => [['active', 'active', -1], ['inactive', 'inactive', -1]],
        'FB.StartStop'      => [['start', 'Start', -1], ['stop', 'Stop', -1]],
        'FB.DynDnyState'    => [
            ['offline', 'offline', -1], ['checking', 'checking', -1], ['updating', 'updating', -1], ['updated', 'updated', -1],
            ['verifying', 'verifying', -1], ['complete', 'complete', -1], ['new-address', 'new address', -1], ['new address', 'new address', -1],
            ['account-disabled', 'account disabled', -1], ['internet-not-connected', 'internet not connected', -1], ['undefined', 'undefined', -1]
        ],
        'FB.UpdateState' => [
            ['Started', 'Started', -1], ['Stopped', 'Stopped', -1], ['Error', 'Error', -1], ['NoUpdate', 'No Update Available', -1],
            ['UpdateAvailable', 'Update Available', -1], ['Unknown', 'Unknown', -1]
        ],
        'FB.BuildType' => [
            ['Release', 'Release', -1], ['Intern', 'Intern', -1], ['Work', 'Work', -1], ['Personal', 'Personal', -1], ['Modified', 'Modified', -1],
            ['Inhaus', 'Inhaus', -1], ['Labor_Beta', 'Labor (Beta)', -1], ['Labor_RC', 'Labor (RC)', -1], ['Labor_DSL', 'Labor (DSL)', -1],
            ['Labor_Phone', 'Labor (Phone)', -1], ['Labor', 'Labor', -1], ['Labor_Test', 'Labor (Test)', -1], ['Labor_Plus', 'Labor (Plus)', -1]
        ],
        // FRITZ!OS 8.25 kennt nur noch all/important/notify, ältere Versionen auch off
        'FB.AutoUpdateMode'   => [['off', 'Off', -1], ['all', 'All', -1], ['important', 'Important', -1], ['notify', 'Notify only', -1]],
        'FB.UpdateSuccessful' => [['unknown', 'Unknown', -1], ['failed', 'Failed', -1], ['succeeded', 'Succeeded', -1]],
        'FB.AHA.Present'      => [['DISCONNECTED', 'disconnected', -1], ['REGISTRERED', 'registrered', -1], ['CONNECTED', 'connected', -1], ['UNKNOWN', 'unknown', -1]],
        'FB.AHA.Valid'        => [['INVALID', 'invalid', -1], ['VALID', 'valid', -1], ['UNDEFINED', 'undefined', -1]],
        'FB.AHA.Mode'         => [['AUTO', 'automatic', -1], ['MANUAL', 'manual', -1], ['UNDEFINED', 'undefined', -1]],
        'FB.AHA.State'        => [['OFF', 'off', -1], ['ON', 'on', -1], ['TOGGLE', 'toggle', -1]],
        'FB.AHA.VentilState'  => [['CLOSED', 'closed', -1], ['OPEN', 'open', -1], ['TEMP', 'temp controlled', -1]],
        'FB.AvmAccessType'    => [
            ['DSL', 'DSL', -1], ['ATA', 'ATA', -1], ['USB-Tethering', 'USB-Tethering', -1], ['Cable', 'Cable', -1], ['LTE', 'LTE', -1],
            ['Serial', 'Serial', -1], ['WLAN', 'WLAN', -1], ['ISDN', 'ISDN', -1], ['IP-Client', 'IP-Client', -1], ['Fiber', 'Fiber', -1], ['Other', 'Other', -1]
        ],
        // ab 1.0, ohne früheres Profil: GetCommonLinkProperties `NewPhysicalLinkStatus` (Werte laut AVM)
        'FB.PhysicalLinkStatus' => [
            ['Up', 'Up', 0x00ff00], ['Down', 'Down', 0xff0000], ['Initializing', 'Initializing', -1], ['Unavailable', 'Unavailable', -1]
        ],
        'FB.AccessType' => [
            ['DSL', 'DSL', -1], ['Ethernet', 'Ethernet', -1], ['X_AVM-DE_Fiber', 'Fiber', -1], ['X_AVM-DE_UMTS', 'UMTS', -1],
            ['X_AVM-DE_Cable', 'Cable', -1], ['X_AVM-DE_LTE', 'LTE', -1], ['unknown', 'unknown', -1]
        ],
        'FB.Connect'          => [[0, 'Reconnect', 0xff0000]],
        'FB.ConnectionStatus' => [[false, 'Disconnected', 0xff0000], [true, 'Connected', 0x00ff00]],
    ];

    /**
     * Liefert die Darstellung zum früheren Profilnamen.
     *
     * @param string   $Profile   Früherer Profilname, '' = keine Darstellung.
     * @param int      $VarType   Variablentyp (0 bool, 1 int, 2 float, 3 string).
     * @param bool     $Action    Variable hat eine Aktion (Aufzählungen werden dann zur Auswahl).
     * @param callable $Translate Übersetzung der Beschriftungen (string): string.
     *
     * @return array|string Darstellung als Array, '' ohne Profil, der Profilname selbst, wenn er unbekannt ist.
     */
    public static function Get(string $Profile, int $VarType, bool $Action, callable $Translate): array|string
    {
        if ($Profile === '') {
            return '';
        }
        switch ($Profile) {
            case '~Switch':
                // Der Schalter ist nur für Variablen mit Aktion zulässig, sonst Wertanzeige mit An/Aus
                if ($Action) {
                    return ['PRESENTATION' => self::Switch];
                }
                $Options = [];
                foreach ([[false, 'Off'], [true, 'On']] as [$Value, $Caption]) {
                    $Options[] = [
                        'Value'              => $Value,
                        'Caption'            => $Translate($Caption),
                        'IconActive'         => false,
                        'IconValue'          => '',
                        'ColorActive'        => false,
                        'ColorValue'         => -1,
                        'ContentColorActive' => false,
                        'ContentColorValue'  => -1
                    ];
                }
                return ['PRESENTATION' => self::ValuePresentation, 'OPTIONS' => json_encode($Options)];
            case '~HTMLBox':
                return ['PRESENTATION' => self::WebContent];
            case '~TextBox':
                return ['PRESENTATION' => self::ValuePresentation, 'MULTILINE' => true];
            case '~UnixTimestamp':
                return ['PRESENTATION' => self::DateTime, 'DATE' => 2, 'TIME' => 1];
            case '~UnixTimestampDate':
                return ['PRESENTATION' => self::DateTime, 'DATE' => 2, 'TIME' => 0];
        }
        if (isset(self::Values[$Profile])) {
            [$Suffix, $Digits, $Min, $Max] = self::Values[$Profile];
            $Result = ['PRESENTATION' => self::ValuePresentation, 'SUFFIX' => $Suffix];
            if ($VarType === 2) {
                $Result['DIGITS'] = $Digits;
            }
            if ($Min !== null) {
                $Result['MIN'] = $Min;
                $Result['MAX'] = $Max;
            }
            return $Result;
        }
        if (isset(self::Options[$Profile])) {
            $Options = [];
            foreach (self::Options[$Profile] as [$Value, $Caption, $Color]) {
                if ($Action) {
                    $Options[] = [
                        'Value'      => $Value,
                        'Caption'    => $Translate($Caption),
                        'IconActive' => false,
                        'IconValue'  => '',
                        'Color'      => $Color
                    ];
                } else {
                    $Options[] = [
                        'Value'              => $Value,
                        'Caption'            => $Translate($Caption),
                        'IconActive'         => false,
                        'IconValue'          => '',
                        'ColorActive'        => $Color !== -1,
                        'ColorValue'         => $Color,
                        'ContentColorActive' => false,
                        'ContentColorValue'  => -1
                    ];
                }
            }
            return [
                'PRESENTATION' => $Action ? self::Enumeration : self::ValuePresentation,
                'OPTIONS'      => json_encode($Options)
            ];
        }
        return $Profile;
    }

    /**
     * Alle bekannten Profilnamen (für Tests).
     *
     * @return string[]
     */
    public static function KnownProfiles(): array
    {
        return array_merge(
            ['~Switch', '~HTMLBox', '~TextBox', '~UnixTimestamp', '~UnixTimestampDate'],
            array_keys(self::Values),
            array_keys(self::Options)
        );
    }

    /**
     * Beschriftungen einer Aufzählung (für Tests der Übersetzungen).
     *
     * @return string[]
     */
    public static function Captions(string $Profile): array
    {
        return array_map(fn ($Option) => $Option[1], self::Options[$Profile] ?? []);
    }
}
