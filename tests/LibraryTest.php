<?php

declare(strict_types=1);

include_once __DIR__ . '/stubs/Validator.php';

class LibraryTest extends TestCaseSymconValidation
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

    public function testValidateCallMonitor(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Callmonitor');
    }

    public function testValidateDeviceInfo(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Device Info');
    }

    public function testValidateDHCPServer(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DHCP Server');
    }

    public function testValidateDVBC(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DVBC');
    }

    public function testValidateDynDNS(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox DynDNS');
    }

    public function testValidateFileShare(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox File Share');
    }

    public function testValidateFirmwareInfo(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Firmware Info');
    }

    public function testValidateHomeautomation(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Homeautomation');
    }

    public function testValidateHomeautomationConfigurator(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Homeautomation Configurator');
    }

    public function testValidateHostFilter(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Host Filter');
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

    public function testValidatePowerline(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Powerline');
    }

    public function testValidateTelephony(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox Telephony');
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

    public function testValidateWANPhysicalInterface(): void
    {
        $this->validateModule(__DIR__ . '/../FritzBox WAN Physical Interface');
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
