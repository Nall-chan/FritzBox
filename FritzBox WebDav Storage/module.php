<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
class FritzBoxWebDavStorage extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_webdav'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_WebDAVClient:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', -1);
        $this->RegisterPropertyInteger('RefreshInterval', 3600);
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
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
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyString('Username') == '') {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $this->UpdateWebDAVClient();
            $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval')*1000);
            $this->SetStatus(IS_ACTIVE);
        }
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshInfo':
                return $this->UpdateWebDAVClient();
            case 'Enable':
                return $this->UpdateWebDAVClient((bool)$Value);
         }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        
        return false;
    }
    private function UpdateWebDAVClient(bool $NewEnabled = null)
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        if ($NewEnabled !== null) {
            $changeResult = $this->EnableWebDAVClient($NewEnabled, $result['NewHostURL'], $result['NewMountpointName']);
            if ($changeResult) {
                $result['NewEnable']=$NewEnabled;
            }
        }
        $this->setIPSVariable('Enable', 'WebDav client active', (bool)$result['NewEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        
        return true;
    }
    public function GetInfo()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function EnableWebDAVClient(bool $Enable, string $HostURL, string $NewMountpointName)
    {
        $result = $this->Send('SetConfig', [
            'NewEnable'=> $Enable,
            'NewHostURL'=> $HostURL,
            'NewUsername'=> $this->ReadPropertyString('Username'),
            'NewPassword' =>$this->ReadPropertyString('Password'),
            'NewMountpointName'=>$NewMountpointName
        ]);
        if ($result === null) {
            return true;
        }
        return false;
    }
}
