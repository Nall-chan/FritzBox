<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxPowerline extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_homeplug'
    ];
    protected static $EventSubURLArray = [];
    protected static $LegacySubVariables = [
        \FritzBox\Powerline\Variables::Model,
        \FritzBox\Powerline\Variables::UpdateAvailable
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Homeplug:1'
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterPropertyBoolean(\FritzBox\Powerline\Property::RenameDeviceVariables, false);
        $this->RegisterPropertyBoolean(\FritzBox\Powerline\Property::ModelVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\Powerline\Property::UpdateAvailableVariables, true);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        return json_encode($this->ShowLegacySubVariablesButton($Form));
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
        $this->RemoveDisabledDeviceInfoVariables([
            \FritzBox\Powerline\Variables::Model           => $this->ReadPropertyBoolean(\FritzBox\Powerline\Property::ModelVariables),
            \FritzBox\Powerline\Variables::UpdateAvailable => $this->ReadPropertyBoolean(\FritzBox\Powerline\Property::UpdateAvailableVariables)
        ]);
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }

    public function GetNumberOfDeviceEntries(): int|false
    {
        $Result = $this->Send('GetNumberOfDeviceEntries');
        if ($Result === false) {
            return false;
        }
        return (int) $Result;
    }

    public function GetGenericDeviceEntry(int $Index): mixed
    {
        $Result = $this->Send('GetGenericDeviceEntry', [
            'NewIndex'         => $Index
        ]);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    /**
     * Liefert die Daten eines Powerline-Geräts anhand seiner MAC-Adresse.
     *
     * @param string $MACAddress MAC-Adresse (z.B. 00:11:22:AA:BB:CC)
     *
     * @return array|false
     */
    public function GetSpecificDeviceEntry(string $MACAddress): mixed
    {
        if (!filter_var($MACAddress, FILTER_VALIDATE_MAC)) {
            $this->ReportError($this->Translate('Invalid MAC address.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewMACAddress'=> $MACAddress
        ]);
    }

    public function UpdatePowerlineDevice(string $MACAddress): bool
    {
        $Result = $this->Send('DeviceDoUpdate', [
            'NewMACAddress'         => $MACAddress
        ]);
        return $Result !== false;
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    private function UpdateInfo()
    {
        $NumberOfDeviceEntries = $this->GetNumberOfDeviceEntries();
        if ($NumberOfDeviceEntries === false) {
            return false;
        }
        $ReturnValue = true;
        $Rename = $this->ReadPropertyBoolean(\FritzBox\Powerline\Property::RenameDeviceVariables);
        $ShowModel = $this->ReadPropertyBoolean(\FritzBox\Powerline\Property::ModelVariables);
        $ShowUpdate = $this->ReadPropertyBoolean(\FritzBox\Powerline\Property::UpdateAvailableVariables);
        for ($i = 0; $i < $NumberOfDeviceEntries; $i++) {
            $Result = $this->GetGenericDeviceEntry($i);
            if ($Result === false) {
                $ReturnValue = false;
                continue;
            }
            $Ident = $this->ConvertIdent($Result['NewMACAddress']);
            $Name = $Result['NewName'];
            $this->setIPSVariable($Ident, $Name, (int) $Result['NewActive'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false);
            $VarId = $this->GetIDForIdent($Ident);
            if ($Rename && (IPS_GetName($VarId) != $Name)) {
                IPS_SetName($VarId, $Name);
            }
            $DeviceName = IPS_GetName($VarId);
            $this->SetDeviceInfoVariable($ShowModel, \FritzBox\Powerline\Variables::Model, $Ident, $DeviceName . ' - ' . $this->Translate('Model'), (string) $Result['NewModel'], VARIABLETYPE_STRING, Rename: $Rename);
            $this->SetDeviceInfoVariable($ShowUpdate, \FritzBox\Powerline\Variables::UpdateAvailable, $Ident, $DeviceName . ' - ' . $this->Translate('Update available'), (int) $Result['NewUpdateAvailable'] > 0, VARIABLETYPE_BOOLEAN, Rename: $Rename);
        }
        return $ReturnValue;
    }
}
