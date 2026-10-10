<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDeviceInfo extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/deviceinfo'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:DeviceInfo:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
            case \FritzBox\Base\Action::RefreshState:
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);

        return false;
    }

    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Liefert den Port der TR-064-Schnittstelle für verschlüsselte Verbindungen (https).
     *
     * @return int|false
     */
    public function GetSecurityPort()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    /**
     * Liefert den Pfad (URL) zum vollständigen Ereignisprotokoll der FritzBox.
     *
     * @return string|false
     */
    public function GetDeviceLogPath()
    {
        return $this->Send('X_AVM-DE_GetDeviceLogPath');
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::Manufacturer, 'Manufacturer', (string) $result['NewManufacturerName'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::Model, 'Model', (string) $result['NewModelName'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::SerialNumber, 'SerialNumber', (string) $result['NewSerialNumber'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::SoftwareVersion, 'Software-Version', (string) $result['NewSoftwareVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::LastReboot, 'Last reboot', time() - (int) $result['NewUpTime'], VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::RunTimeRAW, 'Runtime (seconds)', (int) $result['NewUpTime'], VARIABLETYPE_INTEGER);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::Runtime, 'Runtime', $this->ConvertRunTime((int) $result['NewUpTime']), VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\DeviceInfo\Variables::DeviceLog, 'Last events', (string) $result['NewDeviceLog'], VARIABLETYPE_STRING, '~TextBox');
        return true;
    }
}
