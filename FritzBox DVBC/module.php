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
        $this->RegisterProfileStringEx('FB.StartStop', '', '', '', [
            ['start', 'Start', '', -1],
            ['stop', 'Stop', '', -1]
        ]);
        $this->RegisterVariableString('StationSearchMode', $this->Translate('Station Search Mode'), 'FB.StartStop', 0);
        $this->EnableAction('StationSearchMode');
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
            case 'DVBCEnabled':
                return $this->SetDVBCEnable((bool) $Value);
            case 'StationSearchMode':
                return $this->StationSearch((string) $Value);
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

    public function SetDVBCEnable(bool $State)
    {
        $Result = $this->Send('SetDVBCEnable', [
            'NewDVBCEnabled'         => (int) $State
        ]);
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable('DVBCEnabled', 'DVBC enabled', $State, VARIABLETYPE_BOOLEAN, '~Switch', true);
        return true;
    }

    public function StationSearch(string $Mode)
    {
        $Result = $this->Send('StationSearch', [
            'NewStationSearchMode'         => $Mode
        ]);
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable('StationSearchStatus', 'Station search Status', (string) $Result, VARIABLETYPE_STRING, 'FB.ActiveInactive');
        return true;
    }
    private function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable('DVBCEnabled', 'DVBC enabled', (int) $Result['NewDVBCEnabled'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('StationSearchStatus', 'Station search Status', (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive');
        $this->setIPSVariable('SearchProgress', 'Search progress', (int) $Result['NewSearchProgress'], VARIABLETYPE_INTEGER, 'FB.Intensity');
        return true;
    }
}
