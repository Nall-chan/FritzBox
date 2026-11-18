<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxFirmwareInfo extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/userif'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:UserInterface:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        //Never delete this line!
        parent::ApplyChanges();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }

        $this->UpdateInfo();
    }

    public function GetInfo(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetInfoEx(): mixed
    {
        $result = $this->Send('X_AVM-DE_GetInfo');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function SetAutoUpdateMode(string $Mode): bool
    {
        $result = $this->Send('X_AVM-DE_SetConfig', [
            'NewX_AVM-DE_AutoUpdateMode' => $Mode
        ]);
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::AutoUpdateMode, 'Autoupdate Mode', $Mode, VARIABLETYPE_STRING, 'FB.AutoUpdateMode', true);
        return true;
    }

    public function GetInternationalConfig(): mixed
    {
        $result = $this->Send('X_AVM-DE_GetInternationalConfig');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function CheckUpdate(): bool
    {
        $result = $this->Send('X_AVM-DE_CheckUpdate');
        if ($result === false) {
            return false;
        }
        return true;
    }

    public function DoUpdate(): bool
    {
        $result = $this->Send('X_AVM-DE_DoUpdate');
        return $result !== false;
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshState:
                return $this->UpdateInfo();
            case \FritzBox\FirmwareInfo\Variables::AutoUpdateMode:
                return $this->SetAutoUpdateMode((string) $Value);
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
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::UpgradeAvailable, 'Upgrade Available', ((int) $result['NewUpgradeAvailable'] == 1), VARIABLETYPE_BOOLEAN);
        $VarTime = new DateTime((string) $result['NewWarrantyDate']);
        $Timestamp = $VarTime->getTimestamp();
        if ($Timestamp < 6900) {
            $Timestamp = 0;
        }
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::WarrantyDate, 'Warranty Date', $Timestamp, VARIABLETYPE_INTEGER, '~UnixTimestampDate');
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::UpdateState, 'Update State', (string) $result['NewX_AVM-DE_UpdateState'], VARIABLETYPE_STRING, 'FB.UpdateState');
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::BuildType, 'Build Type', (string) $result['NewX_AVM-DE_BuildType'], VARIABLETYPE_STRING, 'FB.BuildType');
        $result = $this->GetInfoEx();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::AutoUpdateMode, 'Autoupdate Mode', (string) $result['NewX_AVM-DE_AutoUpdateMode'], VARIABLETYPE_STRING, 'FB.AutoUpdateMode', true);
        $VarTime = new DateTime((string) $result['NewX_AVM-DE_UpdateTime']);
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::UpdateTime, 'Update Time', $VarTime->getTimestamp(), VARIABLETYPE_INTEGER, '~UnixTimestamp');
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::LastFwVersion, 'Last Firmware Version', (string) $result['NewX_AVM-DE_LastFwVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::CurrentFwVersion, 'Current Firmware Version', (string) $result['NewX_AVM-DE_CurrentFwVersion'], VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\FirmwareInfo\Variables::UpdateSuccessful, 'Update Successful', (string) $result['NewX_AVM-DE_UpdateSuccessful'], VARIABLETYPE_STRING, 'FB.UpdateSuccessful');
        return true;
    }
}
