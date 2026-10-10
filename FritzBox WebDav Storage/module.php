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

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 3600);
        $this->RegisterPropertyString(\FritzBox\WebDavStorage\Property::Username, '');
        $this->RegisterPropertyString(\FritzBox\WebDavStorage\Property::Password, '');
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
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyString(\FritzBox\WebDavStorage\Property::Username) == '') {
            $this->SetStatus(IS_INACTIVE);
        } else {
            $this->UpdateWebDAVClient();
            $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
            $this->SetStatus(IS_ACTIVE);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateWebDAVClient();
            case \FritzBox\WebDavStorage\Variables::Enable:
                return $this->UpdateWebDAVClient((bool) $Value);
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

    public function EnableWebDAVClient(bool $Enable, string $HostURL, string $NewMountpointName)
    {
        $result = $this->Send('SetConfig', [
            'NewEnable'        => $Enable,
            'NewHostURL'       => $HostURL,
            'NewUsername'      => $this->ReadPropertyString(\FritzBox\WebDavStorage\Property::Username),
            'NewPassword'      => $this->ReadPropertyString(\FritzBox\WebDavStorage\Property::Password),
            'NewMountpointName'=> $NewMountpointName
        ]);
        if ($result === null) {
            return true;
        }
        return false;
    }

    private function UpdateWebDAVClient(?bool $NewEnabled = null)
    {
        $result = $this->GetInfo();
        if ($result === false) {
            return false;
        }
        if ($NewEnabled !== null) {
            $changeResult = $this->EnableWebDAVClient($NewEnabled, $result['NewHostURL'], $result['NewMountpointName']);
            if ($changeResult) {
                $result['NewEnable'] = $NewEnabled;
            }
        }
        $this->setIPSVariable(\FritzBox\WebDavStorage\Variables::Enable, 'WebDav client active', (bool) $result['NewEnable'], VARIABLETYPE_BOOLEAN, '~Switch', true);
        return true;
    }
}
