<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxModule.php';

/**
 * Prüft die Übersetzung der Modulnamen.
 * Die Konsole übersetzt den Modulnamen über die locale.json des Moduls, der Konfigurator
 * benennt neue Instanzen über seine eigene locale.json.
 */
class LocaleTest extends TestCase
{
    public function testEveryModuleTranslatesItsName(): void
    {
        $Modules = $this->modules();
        $this->assertNotEmpty($Modules);
        foreach ($Modules as $Module) {
            $this->assertArrayHasKey($Module['name'], $this->germanTranslations($Module['dir']), $Module['dir'] . '/locale.json: module name missing');
        }
    }

    public function testConfiguratorTranslatesCreatableModules(): void
    {
        $Names = array_column($this->modules(), 'name', 'id');
        $Guids = [\FritzBox\GUID::CallMonitor];
        foreach (\FritzBox\Services::$Data as $Service) {
            $Guids = array_merge($Guids, array_keys($Service));
        }
        $Locale = $this->germanTranslations('FritzBox Configurator');
        foreach (array_unique($Guids) as $Guid) {
            $this->assertArrayHasKey($Guid, $Names, 'Unknown module ' . $Guid);
            $this->assertArrayHasKey($Names[$Guid], $Locale, 'FritzBox Configurator/locale.json: ' . $Names[$Guid] . ' missing');
            $this->assertSame($this->germanTranslations($this->dirOf($Guid))[$Names[$Guid]], $Locale[$Names[$Guid]], $Names[$Guid] . ': configurator and module translate differently');
        }
    }

    private function modules(): array
    {
        $Modules = [];
        foreach (glob(dirname(__DIR__) . '/FritzBox*/module.json') as $File) {
            $Json = json_decode(file_get_contents($File), true);
            $Modules[] = ['id' => $Json['id'], 'name' => $Json['name'], 'dir' => basename(dirname($File))];
        }
        return $Modules;
    }

    private function dirOf(string $Guid): string
    {
        return array_column($this->modules(), 'dir', 'id')[$Guid];
    }

    private function germanTranslations(string $Dir): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/' . $Dir . '/locale.json'), true)['translations']['de'] ?? [];
    }
}
