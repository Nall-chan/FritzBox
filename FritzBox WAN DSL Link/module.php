<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxWANDSLLink extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/igdupnp/control/WANDSLLinkC1',
        '/igd2upnp/control/WANDSLLinkC1'
    ];
    protected static $EventSubURLArray = [
        '/igdupnp/control/WANDSLLinkC1',
        '/igd2upnp/control/WANDSLLinkC1'
    ];
    protected static $ServiceTypeArray = [
        'urn:upnp-org:serviceId:WANDSLLinkC1',
        'urn:upnp-org:serviceId:WANDSLLinkC1'

    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', -1);
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
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshInfo':
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
    }
    public function GetDSLLinkInfo()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetModulationType()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetATMEncapsulation()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetFCSPreserved()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (($Splitter == 0) || !$this->HasActiveParent()) {
            //Parent inactive ausgeben.
            //$Form[];
        }
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'HasIGD2'
            ]
        ));
        $HasIGD2 = unserialize($Ret);
        $this->SendDebug('Use IGD2', $HasIGD2, 0);
        if (!$HasIGD2) {
            unset($Form['elements'][0]['options'][2]);
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function DecodeEvent($Event)
    {
        if (array_key_exists('LinkStatus', $Event)) {
            if (@$this->GetIDForIdent('LinkStatus')) {
                $this->setIPSVariable('LinkStatus', 'DSL link status', $Event['LinkStatus'], VARIABLETYPE_STRING);
            }
            unset($Event['LinkStatus']);
            $this->UpdateInfo();
        }
        parent::DecodeEvent($Event);
    }
    private function UpdateInfo()
    {
        @$this->GetDSLLinkInfo();
        @$this->GetModulationType();
        @$this->GetATMEncapsulation();
        @$this->GetFCSPreserved();
    }
}
