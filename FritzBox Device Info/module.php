<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDeviceInfo extends FritzBoxModulBase
{
    protected static $ControlUrlArray = ['/upnp/control/deviceinfo'];
    protected static $EventSubURLArray = ['/upnp/control/deviceinfo'];
    protected static $ServiceTypeArray = ['urn:dslforum-org:service:DeviceInfo:1'];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval')*1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->UpdateInfo();
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('Hersteller', 'Manufacturer', (string) $result['NewManufacturerName'], VARIABLETYPE_STRING);
        $this->setIPSVariable('Model', 'Model', (string) $result['NewModelName'], VARIABLETYPE_STRING);
        $this->setIPSVariable('Seriennummer', 'SerialNumber', (string) $result['NewSerialNumber'], VARIABLETYPE_STRING);
        $this->setIPSVariable('SoftwareVersion', 'Software-Version', (string) $result['NewSoftwareVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable('LetzterNeustart', 'last reboot', time() - (int) $result['NewUpTime'], VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable('RunTimeRAW', 'UpTime seconds', (int) $result['NewUpTime'], VARIABLETYPE_INTEGER);
        $this->setIPSVariable('Laufzeit', 'Uptime', $this->ConvertRunTime((int) $result['NewUpTime']), VARIABLETYPE_STRING);
        $this->setIPSVariable('DeviceLog', 'Logfile', (string) $result['NewDeviceLog'], VARIABLETYPE_STRING, '~TextBox');
        return true;
    }
    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
}
