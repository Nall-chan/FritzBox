<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxDeviceConfig extends FritzBoxModulBase
{
    protected static $ControlUrlArray = ['/upnp/control/deviceconfig'];
    protected static $EventSubURLArray = ['/upnp/control/deviceconfig'];
    protected static $ServiceTypeArray = ['urn:dslforum-org:service:DeviceConfig:1'];
    public function Create()
    {
        //Never delete this line!
		parent::Create();
		$this->RegisterPropertyInteger('Index', 0);
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