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
            case 'Enable':
                return $this->EnableUPnPServer((bool) $Value, $this->GetValue('UPnPMediaServer'));
            case 'UPnPMediaServer':
                return $this->EnableUPnPServer($this->GetValue('Enable'), (bool) $Value);
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
        $this->setIPSVariable('Enable', 'UPnP protocol active', (bool) $result['NewEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable('UPnPMediaServer', 'UPnP Mediaserver active', (bool) $result['NewUPnPMediaServer'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        return true;
    }
}
