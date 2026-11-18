<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/autoload.php';

use PHPUnit\Framework\TestCase;

/**
 * Prüft die einmalige Migration der alten Untervariablen (bis 0.95) in FritzBoxModulBase::Migrate
 * an einer WLAN-Instanz der Symcon-Stubs: Untervariablen `Speed`/`Signal` unter der Gerätevariable werden zu
 * `Speed_<Gerät>`/`Signal_<Gerät>` der Instanz (gleiche ID), nur einmal und nur, wenn gewünscht und noch nicht vorhanden.
 */
class LegacySubVariablesMigrateTest extends TestCase
{
    private const WLAN = '{B3D72623-556E-B6C6-25E0-B3DEFE41F031}';

    public function setUp(): void
    {
        IPS\Kernel::reset();
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/../library.json');
        IPS\ModuleLoader::loadLibrary(__DIR__ . '/stubs/CoreStubs/library.json');
        parent::setUp();
    }

    public function testMigratesOldSubVariables(): void
    {
        [$Iid, $Device, $Speed, $Signal] = $this->oldStructure('Geschwindigkeit');
        $Result = json_decode(IPS\InstanceManager::getInstanceInterface($Iid)->Migrate($this->persistence([])), true);

        $this->assertTrue($Result['attributes']['LegacySubVariablesMigrated']);
        // Gleiche Objekte, jetzt an der Instanz mit neuem Ident
        $this->assertSame($Speed, IPS_GetObjectIDByIdent('Speed_MAC001122AABBCC', $Iid));
        $this->assertSame($Signal, IPS_GetObjectIDByIdent('Signal_MAC001122AABBCC', $Iid));
        $this->assertSame($Iid, IPS_GetParent($Speed));
        $this->assertSame([], IPS_GetChildrenIDs($Device));
    }

    public function testRenamesOnlyDefaultNames(): void
    {
        [$Iid, , $Speed, $Signal] = $this->oldStructure('Mein Tempo');
        $Intf = IPS\InstanceManager::getInstanceInterface($Iid);
        $Intf->Migrate($this->persistence([]));
        // Eigener Name bleibt, Standardname wird zu „<Gerät> - <Name>“
        $this->assertSame('Mein Tempo', IPS_GetName($Speed));
        $this->assertSame('Laptop - ' . $Intf->Translate('Signalstrength'), IPS_GetName($Signal));
    }

    public function testRunsOnlyOnce(): void
    {
        [$Iid, $Device, $Speed] = $this->oldStructure('Geschwindigkeit');
        $Result = IPS\InstanceManager::getInstanceInterface($Iid)->Migrate($this->persistence(['LegacySubVariablesMigrated' => true]));
        $this->assertSame('', $Result);
        $this->assertSame($Device, IPS_GetParent($Speed));
        $this->assertSame('Speed', IPS_GetObject($Speed)['ObjectIdent']);
    }

    public function testSkipsDisabledAndExisting(): void
    {
        [$Iid, $Device, $Speed, $Signal] = $this->oldStructure('Geschwindigkeit');
        // Neue Signal-Variable gibt es schon (z.B. von einer Vorabversion), Speed ist abgewählt
        $Existing = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($Existing, $Iid);
        IPS_SetIdent($Existing, 'Signal_MAC001122AABBCC');
        $Result = json_decode(IPS\InstanceManager::getInstanceInterface($Iid)->Migrate($this->persistence([], ['SpeedVariables' => false])), true);

        $this->assertTrue($Result['attributes']['LegacySubVariablesMigrated']);
        $this->assertSame($Device, IPS_GetParent($Speed));
        $this->assertSame($Device, IPS_GetParent($Signal));
        $this->assertSame('Signal', IPS_GetObject($Signal)['ObjectIdent']);
        $this->assertSame($Existing, IPS_GetObjectIDByIdent('Signal_MAC001122AABBCC', $Iid));
    }

    public function testNewInstanceIsMarkedAsMigrated(): void
    {
        $Iid = IPS_CreateInstance(self::WLAN);
        $Attributes = IPS\InstanceManager::getInstanceInterface($Iid)->GetPersistence()['attributes'] ?? [];
        $this->assertTrue($Attributes['LegacySubVariablesMigrated'] ?? false);
    }

    /**
     * WLAN-Instanz mit Gerätevariable `MAC001122AABBCC` („Laptop“) und den Untervariablen `Speed` und `Signal` wie bis 0.95.
     *
     * @return int[] Instanz, Gerätevariable, Speed, Signal
     */
    private function oldStructure(string $SpeedName): array
    {
        $Iid = IPS_CreateInstance(self::WLAN);
        $Device = IPS_CreateVariable(VARIABLETYPE_BOOLEAN);
        IPS_SetParent($Device, $Iid);
        IPS_SetIdent($Device, 'MAC001122AABBCC');
        IPS_SetName($Device, 'Laptop');
        $Speed = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($Speed, $Device);
        IPS_SetIdent($Speed, 'Speed');
        IPS_SetName($Speed, $SpeedName);
        $Signal = IPS_CreateVariable(VARIABLETYPE_INTEGER);
        IPS_SetParent($Signal, $Device);
        IPS_SetIdent($Signal, 'Signal');
        IPS_SetName($Signal, IPS\InstanceManager::getInstanceInterface($Iid)->Translate('Signalstrength'));
        return [$Iid, $Device, $Speed, $Signal];
    }

    /**
     * Gespeicherte Daten wie beim Laden einer Instanz bis 0.95 (ohne das neue Attribut).
     */
    private function persistence(array $Attributes, array $Configuration = []): string
    {
        return json_encode(['configuration' => (object) $Configuration, 'attributes' => (object) $Attributes]);
    }
}
