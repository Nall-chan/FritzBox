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
    public function testValidateConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Configurator');
    }
    public function testValidateCallList(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Call List');
    }
    public function testValidateCallMonitor(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Callmonitor');
    }
    public function testValidateDynDNS(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DynDNS');
    }
    public function testValidateDeviceInfo(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Device Info');
    }
    public function testValidateDHCPServer(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DHCP Server');
    }
    public function testValidateFileShare(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox File Share');
    }
    public function testValidateHosts(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Hosts');
    }
    public function testValidateMyFritz(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox MyFritz');
    }
    public function testValidateNASStorage(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox NAS Storage');
    }

    public function testValidateTime(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Time');
    }

    public function testValidateUPnPMediaServer(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox UPnP MediaServer');
    }
    public function testValidateWANCommonInterface(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN Common Interface');
    }
    public function testValidateWANDSLLink(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN DSL Link');
    }
    public function testValidateWANIPConnection(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN IP Connection');
    }
    public function testValidateWANPortMapping(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN PortMapping');
    }
    public function testValidateWebDavStorage(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WebDav Storage');
    }
    public function testValidateWLAN(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WLAN');
    }
}
