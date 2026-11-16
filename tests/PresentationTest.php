<?php

declare(strict_types=1);

use FritzBox\Presentation;
use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxConstants.php';
include_once __DIR__ . '/../libs/FritzBoxPresentations.php';

/**
 * Prüft die Darstellungen der Statusvariablen (libs/FritzBoxPresentations.php)
 * gegen die Parameterlisten aus IPS_GetPresentations (tests/presentations.json).
 */
class PresentationTest extends TestCase
{
    private static array $Presentations = [];

    public static function setUpBeforeClass(): void
    {
        $Data = json_decode(file_get_contents(__DIR__ . '/presentations.json'), true);
        foreach ($Data['presentations'] as $Presentation) {
            self::$Presentations[$Presentation['id']] = $Presentation;
        }
    }

    public function testNoProfilesRegistered(): void
    {
        foreach ($this->sourceFiles() as $File) {
            $this->assertDoesNotMatchRegularExpression('/->(Register|Unregister)Profile\w*\(|IPS_CreateVariableProfile\(/', file_get_contents($File), $File);
        }
    }

    public function testAllUsedProfilesAreKnown(): void
    {
        $Usages = $this->usages();
        $this->assertNotEmpty($Usages);
        foreach ($Usages as [$Module, $Profile]) {
            $this->assertContains($Profile, Presentation::KnownProfiles(), $Module . ' uses unknown profile ' . $Profile);
        }
    }

    public function testParametersMatchPresentation(): void
    {
        foreach ($this->usages() as [$Module, $Profile, $Type, $Action]) {
            $Result = Presentation::Get($Profile, $Type, $Action, fn ($Text) => $Text);
            $this->assertIsArray($Result, $Profile);
            $Context = $Module . ' ' . $Profile . ' (type ' . $Type . ')';
            $this->assertArrayHasKey($Result['PRESENTATION'], self::$Presentations, $Context);
            $Definition = self::$Presentations[$Result['PRESENTATION']];
            if (count($Definition['variableType']) > 0) {
                $this->assertContains($Type, $Definition['variableType'], $Context . ': presentation not allowed for type');
            }
            $Allowed = $Definition['parameters'];
            foreach ($Definition['groups'] ?? [] as $Types => $GroupParameters) {
                if (in_array($Type, array_map('intval', explode(',', (string) $Types)), true)) {
                    $Allowed = array_merge($Allowed, $GroupParameters);
                }
            }
            foreach ($Result as $Name => $Value) {
                if ($Name === 'PRESENTATION') {
                    continue;
                }
                $this->assertArrayHasKey($Name, $Allowed, $Context . ': unknown parameter ' . $Name);
                $this->assertValueType($Allowed[$Name]['type'], $Type, $Value, $Context . ' ' . $Name);
                if (isset($Allowed[$Name]['parameters'])) {
                    $List = json_decode($Value, true);
                    $this->assertIsArray($List, $Context . ' ' . $Name);
                    foreach ($List as $Entry) {
                        foreach ($Entry as $Key => $EntryValue) {
                            $this->assertArrayHasKey($Key, $Allowed[$Name]['parameters'], $Context . ': unknown ' . $Name . ' key ' . $Key);
                            $this->assertValueType($Allowed[$Name]['parameters'][$Key], $Type, $EntryValue, $Context . ' ' . $Name . '.' . $Key);
                        }
                    }
                }
            }
        }
    }

    public function testCaptionsAreTranslated(): void
    {
        $Missing = [];
        foreach ($this->usages() as [$Module, $Profile]) {
            $Captions = Presentation::Captions($Profile);
            if (count($Captions) === 0) {
                continue;
            }
            $Locale = json_decode(file_get_contents(dirname(__DIR__) . '/' . $Module . '/locale.json'), true)['translations']['de'] ?? [];
            foreach ($Captions as $Caption) {
                if (!array_key_exists($Caption, $Locale)) {
                    $Missing[$Module . ': "' . $Caption . '" (' . $Profile . ')'] = true;
                }
            }
        }
        $this->assertSame([], array_keys($Missing), 'Captions not translated');
    }

    private function assertValueType(int|string $Expected, int $VarType, mixed $Value, string $Context): void
    {
        if ($Expected === 'variableType') {
            $Expected = $VarType;
        }
        switch ($Expected) {
            case 0:
                $this->assertIsBool($Value, $Context);
                break;
            case 1:
                $this->assertIsInt($Value, $Context);
                break;
            case 2:
                $this->assertTrue(is_int($Value) || is_float($Value), $Context);
                break;
            case 3:
                $this->assertIsString($Value, $Context);
                break;
        }
    }

    /**
     * @return string[]
     */
    private function sourceFiles(): array
    {
        return array_merge(glob(dirname(__DIR__) . '/*/module.php'), [dirname(__DIR__) . '/libs/FritzBoxBase.php']);
    }

    /**
     * Verwendete Profilnamen je Modul: [Modulordner, Profil, Variablentyp, Aktion].
     *
     * @return array<int, array{0: string, 1: string, 2: int, 3: bool}>
     */
    private function usages(): array
    {
        $Types = ['BOOLEAN' => 0, 'INTEGER' => 1, 'FLOAT' => 2, 'STRING' => 3];
        $Profile = '(\'(?:~|FB\.)[^\']+\'|\\\\FritzBox\\\\\w+\\\\Profile::\w+)';
        $Patterns = [
            // setIPSVariable(ident, name, value, VARIABLETYPE_X, 'Profil'[, action
            '/setIPSVariable\(.*VARIABLETYPE_(\w+), ' . $Profile . '(?:, (true|false))?/',
            // GetPresentation('Profil', VARIABLETYPE_X[, true
            '/GetPresentation\(' . $Profile . ', VARIABLETYPE_(\w+)(?:, (true|false))?/',
            // SetDeviceInfoVariable(enabled, info, ident, name, value, VARIABLETYPE_X, 'Profil'
            '/SetDeviceInfoVariable\(.*VARIABLETYPE_(\w+), ' . $Profile . '/'
        ];
        $Result = [];
        foreach (glob(dirname(__DIR__) . '/*/module.php') as $File) {
            $Module = basename(dirname($File));
            foreach (file($File) as $Line) {
                foreach ($Patterns as $Index => $Pattern) {
                    if (!preg_match($Pattern, $Line, $M)) {
                        continue;
                    }
                    if ($Index === 1) {
                        [$Name, $Type, $Action] = [$M[1], $M[2], $M[3] ?? 'false'];
                    } else {
                        [$Type, $Name, $Action] = [$M[1], $M[2], $M[3] ?? 'false'];
                    }
                    $Name = $Name[0] === '\'' ? trim($Name, '\'') : constant(ltrim($Name, '\\'));
                    $Result[] = [$Module, $Name, $Types[$Type], $Action === 'true'];
                }
            }
        }
        return $Result;
    }
}
