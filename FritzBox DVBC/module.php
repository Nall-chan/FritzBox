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

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
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
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
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
        $this->RegisterVariableString(\FritzBox\DVBC\Variables::StationSearchMode, $this->Translate('Station search'), 'FB.StartStop', 0);
        $this->EnableAction(\FritzBox\DVBC\Variables::StationSearchMode);
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\DVBC\Variables::DVBCEnabled:
                return $this->SetDVBCEnable((bool) $Value);
            case \FritzBox\DVBC\Variables::StationSearchMode:
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
        $this->setIPSVariable(\FritzBox\DVBC\Variables::DVBCEnabled, 'DVBC enabled', $State, VARIABLETYPE_BOOLEAN, '~Switch', true);
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
        $this->setIPSVariable(\FritzBox\DVBC\Variables::StationSearchStatus, 'Station search status', (string) $Result, VARIABLETYPE_STRING, 'FB.ActiveInactive');
        return true;
    }

    private function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DVBC\Variables::DVBCEnabled, 'DVBC enabled', (int) $Result['NewDVBCEnabled'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\DVBC\Variables::StationSearchStatus, 'Station search status', (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive');
        $this->setIPSVariable(\FritzBox\DVBC\Variables::SearchProgress, 'Search progress', (int) $Result['NewSearchProgress'], VARIABLETYPE_INTEGER, 'FB.Intensity');
        return true;
    }
}
