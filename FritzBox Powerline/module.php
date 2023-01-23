<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxPowerline extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_homeplug'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Homeplug:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshInfo', 0);
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
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
            case 'RefreshInfo':
                return $this->UpdateInfo();
         }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function UpdateInfo()
    {
        $NumberOfDeviceEntries = $this->GetNumberOfDeviceEntries();
        if ($NumberOfDeviceEntries === false) {
            return false;
        }
        $ReturnValue = true;
        $Rename = true; //todo Property
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

            $ModelId = $this->RegisterSubVariable($VarId, 'Model', 'Model', VARIABLETYPE_STRING, '');
            SetValueString($ModelId, (string) $Result['NewModel']);
            $UpdateAvailableId = $this->RegisterSubVariable($VarId, 'UpdateAvailable', $this->Translate('Update available'), VARIABLETYPE_BOOLEAN, '');
            SetValueBoolean($UpdateAvailableId, (int) $Result['NewUpdateAvailable'] > 0);
        }
        return $ReturnValue;
    }
    public function GetGenericDeviceEntry(int $Index)
    {
        $Result = $this->Send('GetGenericDeviceEntry', [
            'NewIndex'         => $Index
        ]);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }
    public function GetNumberOfDeviceEntries()
    {
        $Result = $this->Send('GetNumberOfDeviceEntries');
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    public function UpdatePowerlineDevice(string $MACAddress)
    {
        $Result = $this->Send('DeviceDoUpdate', [
            'NewMACAddress'         => $MACAddress
        ]);
        if ($Result === false) {
            return false;
        }
        return $Result;
    }
}
