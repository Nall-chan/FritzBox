<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/stubs/GlobalStubs.php';
include_once __DIR__ . '/stubs/KernelStubs.php';
include_once __DIR__ . '/stubs/ModuleStubs.php';
include_once __DIR__ . '/stubs/ConstantStubs.php';
include_once __DIR__ . '/../FritzBox WAN IP Connection/module.php';

/**
 * Prüft die Migration der Eigenschaft Index (IGD/IGD2) von FritzBox WAN IP-Verbindung.
 */
class WANIPConnectionMigrateTest extends TestCase
{
    public function testOldIndex1BecomesIGD(): void
    {
        $Result = $this->migrate(['configuration' => ['Index' => 1], 'attributes' => new stdClass()]);
        $this->assertSame(0, $Result['configuration']['Index']);
        $this->assertSame(1, $Result['attributes']['MigrationVersion']);
    }

    public function testOldIndex2BecomesIGD2(): void
    {
        $Result = $this->migrate(['configuration' => ['Index' => 2], 'attributes' => new stdClass()]);
        $this->assertSame(1, $Result['configuration']['Index']);
    }

    public function testIndex0Unchanged(): void
    {
        $Result = $this->migrate(['configuration' => ['Index' => 0], 'attributes' => new stdClass()]);
        $this->assertSame(0, $Result['configuration']['Index']);
    }

    public function testMigratedInstanceIsNotChanged(): void
    {
        $Result = $this->migrate(['configuration' => ['Index' => 1], 'attributes' => ['MigrationVersion' => 1]]);
        $this->assertSame(1, $Result['configuration']['Index']);
    }

    public function testMissingAttributesObject(): void
    {
        $Result = $this->migrate(['configuration' => ['Index' => 1]]);
        $this->assertSame(0, $Result['configuration']['Index']);
        $this->assertSame(1, $Result['attributes']['MigrationVersion']);
    }
    private function migrate(array $Data): array
    {
        $Module = (new ReflectionClass('FritzBoxWANIPConnection'))->newInstanceWithoutConstructor();
        return json_decode($Module->Migrate(json_encode($Data)), true);
    }
}
