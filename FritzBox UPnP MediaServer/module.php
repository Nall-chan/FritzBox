<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxUPnPMediaServer extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_upnp'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_UPnP:1'
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
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
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
            case \FritzBox\UPnPMediaServer\Variables::Enable:
                return $this->EnableUPnPServer((bool) $Value, $this->GetValue(\FritzBox\UPnPMediaServer\Variables::UPnPMediaServer));
            case \FritzBox\UPnPMediaServer\Variables::UPnPMediaServer:
                return $this->EnableUPnPServer($this->GetValue(\FritzBox\UPnPMediaServer\Variables::Enable), (bool) $Value);
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

    public function EnableUPnPServer(bool $Enable, bool $UPnPMediaServer)
    {
        $result = $this->Send('SetConfig', [
            'NewEnable'         => $Enable,
            'NewUPnPMediaServer'=> $UPnPMediaServer
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }

    private function UpdateInfo()
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\UPnPMediaServer\Variables::Enable, 'UPnP protocol active', (bool) $result['NewEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\UPnPMediaServer\Variables::UPnPMediaServer, 'UPnP Mediaserver active', (bool) $result['NewUPnPMediaServer'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        return true;
    }
}
