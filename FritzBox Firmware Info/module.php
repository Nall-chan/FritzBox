<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxFirmwareInfo extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/userif'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:UserInterface:1'
    ];
    protected static $DefaultIndex = 0;
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }
    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.UpdateState');
            $this->UnregisterProfile('FB.BuildType');
            $this->UnregisterProfile('FB.AutoUpdateMode');
            $this->UnregisterProfile('FB.UpdateSuccessful');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->RegisterProfileStringEx('FB.UpdateState', '', '', '', [
            ['Started', $this->Translate('Started'), '', -1],
            ['Stopped', $this->Translate('Stopped'), '', -1],
            ['Error', $this->Translate('Error'), '', -1],
            ['NoUpdate', $this->Translate('No Update Available'), '', -1],
            ['UpdateAvailable', $this->Translate('Update Available'), '', -1],
            ['Unknown', $this->Translate('Unknown'), '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.BuildType', '', '', '', [
            ['Release', $this->Translate('Release'), '', -1],
            ['Intern', $this->Translate('Intern'), '', -1],
            ['Work', $this->Translate('Work'), '', -1],
            ['Personal', $this->Translate('Personal'), '', -1],
            ['Modified', $this->Translate('Modified'), '', -1],
            ['Inhaus', $this->Translate('Inhaus'), '', -1],
            ['Labor_Beta', $this->Translate('Labor (Beta)'), '', -1],
            ['Labor_RC', $this->Translate('Labor (RC)'), '', -1],
            ['Labor_DSL', $this->Translate('Labor (DSL)'), '', -1],
            ['Labor_Phone', $this->Translate('Labor (Phone)'), '', -1],
            ['Labor', $this->Translate('Labor'), '', -1],
            ['Labor_Test', $this->Translate('Labor (Test)'), '', -1],
            ['Labor_Plus', $this->Translate('Labor (Plus)'), '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.AutoUpdateMode', '', '', '', [
            ['off', $this->Translate('Off'), '', -1],
            ['all', $this->Translate('All'), '', -1],
            ['important', $this->Translate('Important'), '', -1]
        ]);
        $this->RegisterProfileStringEx('FB.UpdateSuccessful', '', '', '', [
            ['unknown', $this->Translate('Unknown'), '', -1],
            ['failed', $this->Translate('Failed'), '', -1],
            ['succeeded', $this->Translate('Succeeded'), '', -1]
        ]);
        $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
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
            case 'AutoUpdateMode':
                return $this->SetAutoUpdateMode((string) $Value);
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
    public function GetInfoEx()
    {
        $result = $this->Send('X_AVM-DE_GetInfo');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetAutoUpdateMode(string $Mode)
    {
        $result = $this->Send('X_AVM-DE_SetConfig', [
            'NewX_AVM-DE_AutoUpdateMode' => $Mode
        ]);
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('AutoUpdateMode', 'Autoupdate Mode', $Mode, VARIABLETYPE_STRING, 'FB.AutoUpdateMode', true);
        return true;
    }
    public function GetInternationalConfig()
    {
        $result = $this->Send('X_AVM-DE_GetInternationalConfig');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function CheckUpdate()
    {
        $result = $this->Send('X_AVM-DE_CheckUpdate');
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function DoUpdate()
    {
        $result = $this->Send('X_AVM-DE_DoUpdate');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('UpgradeAvailable', 'Upgrade Available', ((int) $result['NewUpgradeAvailable'] == 1), VARIABLETYPE_BOOLEAN);
        $VarTime = new DateTime((string) $result['NewWarrantyDate']);
        $Timestamp = $VarTime->getTimestamp();
        if ($Timestamp < 6900) {
            $Timestamp = 0;
        }
        $this->setIPSVariable('WarrantyDate', 'Warranty Date', $Timestamp, VARIABLETYPE_INTEGER, '~UnixTimestampDate');
        $this->setIPSVariable('UpdateState', 'Update State', (string) $result['NewX_AVM-DE_UpdateState'], VARIABLETYPE_STRING, 'FB.UpdateState');
        $this->setIPSVariable('BuildType', 'Build Type', (string) $result['NewX_AVM-DE_BuildType'], VARIABLETYPE_STRING, 'FB.BuildType');
        $result = $this->GetInfoEx();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('AutoUpdateMode', 'Autoupdate Mode', (string) $result['NewX_AVM-DE_AutoUpdateMode'], VARIABLETYPE_STRING, 'FB.AutoUpdateMode', true);
        $VarTime = new DateTime((string) $result['NewX_AVM-DE_UpdateTime']);
        $this->setIPSVariable('UpdateTime', 'Update Time', $VarTime->getTimestamp(), VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable('LastFwVersion', 'Last Firmware Version', (string) $result['NewX_AVM-DE_LastFwVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable('CurrentFwVersion', 'Current Firmware Version', (string) $result['NewX_AVM-DE_CurrentFwVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable('UpdateSuccessful', 'Update Successful', (string) $result['NewX_AVM-DE_UpdateSuccessful'], VARIABLETYPE_STRING, 'FB.UpdateSuccessful');
        return true;
    }
}
