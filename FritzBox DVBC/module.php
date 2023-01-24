<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxDVBC extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_media'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Media:1'
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
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.Intensity');
            $this->UnregisterProfile('FB.ActiveInactive');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshInfo', 0);
        parent::ApplyChanges();
        $this->RegisterProfileInteger('FB.Intensity', 'FB.Intensity', '', ' %', 0, 100, 1);
        $this->RegisterProfileStringEx('FB.ActiveInactive', '', '', '', [
            ['active', 'active', '', -1],
            ['inactive', 'inactive', '', -1]
        ]);
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
            case 'NewDVBCEnabled':
                return $this->SetDVBCEnable((bool)$Value);
            case 'NewStationSearchStatus':
                return $this->StationSearch(($State == 'active' ? true : false));
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
        $this->setIPSVariable($Ident, $Name, (int) $Result['NewDVBCEnabled'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable($Ident, $Name, (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive', true);
        $this->setIPSVariable($Ident, $Name, (int) $Result['NewSearchProgress'], VARIABLETYPE_INTEGER, 'FB.Intensity', false);
        return true;
    }

    public function SetDVBCEnable(bool $State)
    {
        $Result = $this->Send('SetDVBCEnable', [
            'NewDVBCEnabled'         => (int)$State
        ]);
        if ($Result === false) {
            return false;
        }
        return true;
    }

    public function StationSearch(bool $State)
    {
        $Result = $this->Send('StationSearch', [
            'NewStationSearchMode'         => ($State ? 'active' : 'inactive')
        ]);
        if ($Result === false) {
            return false;
        }
        if ($Result['NewStationSearchStatus'] == ($State ? 'active' : 'inactive')) {
            $this->setIPSVariable($Ident, $Name, (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive', true);
            return true;
        }
        return false;
    }
}
