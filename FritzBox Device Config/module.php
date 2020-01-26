<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDeviceConfig extends FritzBoxModulBase
{
    protected static $ControlUrl = '/upnp/control/deviceconfig';
    protected static $EventSubURL = '/upnp/control/deviceconfig';
    protected static $ServiceType = 'urn:dslforum-org:service:DeviceConfig:1';
    public function Create()
    {
        //Never delete this line!
        parent::Create();
    }
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function CreateUrlSID()
    {
        return $this->Send('X_AVM-DE_CreateUrlSID');
    }
    public function Reboot()
    {
        return $this->Send(__FUNCTION__);
    }
}