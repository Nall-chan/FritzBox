<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryValidationTest extends TestCaseSymconValidation
{
    public function testValidateLibrary(): void
    {
        $this->validateLibrary(__DIR__ . '/..');
    }

    public function testValidateDiscovery(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Discovery');
    }
    public function testValidateIO(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox IO');
    }
    public function testValidateCabelModem(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Cabel Modem');
    }
    public function testValidateCallerList(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Caller List');
    }
    public function testValidateCallMonitor(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Callmonitor');
    }
    public function testValidateDHCPServer(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DHCP Server');
    }
    public function testValidateDSLModem(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DSL Modem');
    }
    public function testValidateHosts(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Hosts');
    }
    public function testValidateKonfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Konfigurator');
    }
    public function testValidateWLAN(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WLAN');
    }
    public function testValidateWANInterface(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN Interface');
    }
}