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
        'urn:schemas-upnp-org:service:WANDSLLinkConfig:1',
        'urn:schemas-upnp-org:service:WANDSLLinkConfig:1'

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
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
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

    public function GetAutoConfig()
    {
        return $this->Send(__FUNCTION__);
    }

    public function GetModulationType()
    {
        return $this->Send(__FUNCTION__);
    }

    public function GetDestinationAddress()
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

    public function GetStatistics()
    {
        return $this->Send(__FUNCTION__);
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (($Splitter > 1) && $this->HasActiveParent()) {
            $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'HasIGD2'
            ]
        ));
            $HasIGD2 = unserialize($Ret);
            $this->SendDebug('Use IGD2', $HasIGD2, 0);
            if (!$HasIGD2) {
                unset($Form['elements'][0]['options'][2]);
            }
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function DecodeEvent($Event)
    {
        if (array_key_exists('LinkStatus', $Event)) {
            $this->setIPSVariable('LinkStatus', 'DSL Link Status', (string) $Event['LinkStatus'], VARIABLETYPE_STRING);
            unset($Event['LinkStatus']);
            $this->UpdateInfo();
        }
        parent::DecodeEvent($Event);
    }

    private function UpdateInfo()
    {
        $result = $this->GetDSLLinkInfo();
        if ($result !== false) {
            $this->setIPSVariable('LinkType', 'DSL Link Type', $result['NewLinkType'], VARIABLETYPE_STRING);
            $this->setIPSVariable('LinkStatus', 'DSL Link Status', (string) $result['NewLinkStatus'], VARIABLETYPE_STRING);
        }
        $result = $this->GetAutoConfig();
        if ($result !== false) {
            $this->setIPSVariable('AutoConfig', 'DSL Auto Config', (bool) $result, VARIABLETYPE_BOOLEAN);
        }

        $result = $this->GetModulationType();
        if ($result !== false) {
            $this->setIPSVariable('ModulationType', 'Modulation Type', $result, VARIABLETYPE_STRING);
        }
        $result = $this->GetDestinationAddress();
        if ($result !== false) {
            $this->setIPSVariable('DestinationAddress', 'Destination Address', $result, VARIABLETYPE_STRING);
        }
        $result = $this->GetATMEncapsulation();
        if ($result !== false) {
            $this->setIPSVariable('ATMEncapsulation', 'ATM Encapsulation', $result, VARIABLETYPE_STRING);
        }
        $result = $this->GetFCSPreserved();
        if ($result !== false) {
            $this->setIPSVariable('FCSPreserved', 'FCS Preserved', (bool) $result, VARIABLETYPE_BOOLEAN);
        }
        // todo
        /*$result = $this->GetStatistics();
        if ($result !== false) {
            $this->setIPSVariable('ATMTransmittedBlocks', 'ATMTransmittedBlocks', (int) $result['NewATMTransmittedBlocks'], VARIABLETYPE_INTEGER);
            $this->setIPSVariable('ATMReceivedBlocks', 'ATMReceivedBlocks', (int) $result['NewATMReceivedBlocks'], VARIABLETYPE_INTEGER);
            $this->setIPSVariable('AAL5CRCErrors', 'AAL5CRCErrors', (int) $result['NewAAL5CRCErrors'], VARIABLETYPE_INTEGER);
            $this->setIPSVariable('ATMCRCErrors', 'ATMCRCErrors', (int) $result['NewATMCRCErrors'], VARIABLETYPE_INTEGER);
        }*/
    }
}
