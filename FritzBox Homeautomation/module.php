<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxHomeautomation extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_homeauto'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Homeauto:1'
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
        /*$this->RegisterProfileInteger('FB.Intensity', 'FB.Intensity', '', ' %', 0, 100, 1);
        $this->RegisterProfileStringEx('FB.ActiveInactive', '', '', '', [
            ['active', 'active', '', -1],
            ['inactive', 'inactive', '', -1]
        ]);*/
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
    public function GetInfo()
    {
        $Result = $this->Send('GetInfo');
        if ($Result === false) {
            return false;
        }
        return $Result;
    }
    public function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }

        /*
        NewAllowedCharsAIN out String with all allowed chars for state variable AIN string
        MaxCharsAIN out ui2
        MinCharsAIN out ui2
        MaxCharsDeviceName out ui2
        MinCharsDeviceName out ui2

        $this->setIPSVariable($Ident, $Name, (int) $Result['NewDVBCEnabled'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable($Ident, $Name, (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive', true);
        $this->setIPSVariable($Ident, $Name, (int) $Result['NewSearchProgress'], VARIABLETYPE_INTEGER, 'FB.Intensity', false);
        */

        return true;
    }

    public function GetGenericDeviceInfos(int $Index)
    {
        $Result = $this->Send('GetGenericDeviceInfos', [
            'NewIndex'         => $Index
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    public function GetSpecificDeviceInfos(string $AIN)
    {
        $Result = $this->Send('GetSpecificDeviceInfos', [
            'NewAIN'         => $AIN
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    public function SetDeviceName(string $AIN, string $Name)
    {
        $Result = $this->Send('SetDeviceName', [
            'NewAIN'         => $AIN,
            'NewDeviceName'  => $Name
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    public function SetSwitch(string $AIN, string $State)
    {
        /* Was ist das? String? Int?
        OFF Switch off
        ON Switch On
        TOGGLE Toggle switch state
        UNDEFINED
        */
        $Result = $this->Send('SetSwitch', [
            'NewAIN'         => $AIN,
            'NewSwitchState' => $State
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }
}
