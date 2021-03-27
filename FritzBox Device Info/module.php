<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDeviceInfo extends FritzBoxModulBase
{
    protected static $ControlUrlArray = ['/upnp/control/deviceinfo'];
    protected static $EventSubURLArray = [];
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
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshState':
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);

        return false;
    }
    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('Manufacturer', 'Manufacturer', (string) $result['NewManufacturerName'], VARIABLETYPE_STRING);
        $this->setIPSVariable('Model', 'Model', (string) $result['NewModelName'], VARIABLETYPE_STRING);
        $this->setIPSVariable('SerialNumber', 'SerialNumber', (string) $result['NewSerialNumber'], VARIABLETYPE_STRING);
        $this->setIPSVariable('SoftwareVersion', 'Software-Version', (string) $result['NewSoftwareVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable('LastReboot', 'Last reboot', time() - (int) $result['NewUpTime'], VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable('RunTimeRAW', 'Runtime (seconds)', (int) $result['NewUpTime'], VARIABLETYPE_INTEGER);
        $this->setIPSVariable('Runtime', 'Runtime', $this->ConvertRunTime((int) $result['NewUpTime']), VARIABLETYPE_STRING);
        $this->setIPSVariable('DeviceLog', 'Last events', (string) $result['NewDeviceLog'], VARIABLETYPE_STRING, '~TextBox');
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
