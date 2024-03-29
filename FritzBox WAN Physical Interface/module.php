<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxWANPhysicalInterface extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/wancommonifconfig1'
    ];
    protected static $EventSubURLArray = [
        //'/upnp/control/wancommonifconfig1'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:WANCommonInterfaceConfig:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterTimer('RefreshLinkProperties', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshLinkProperties",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.kBit');
            $this->UnregisterProfile('FB.AccessType');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshLinkProperties', 0);
        $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
        $this->RegisterProfileStringEx('FB.AccessType', '', '', '', [
            ['DSL', 'DSL', '', -1],
            ['Ethernet', 'Ethernet', '', -1],
            ['X_AVM-DE_Fiber', 'Fiber', '', -1],
            ['X_AVM-DE_UMTS', 'UMTS', '', -1],
            ['X_AVM-DE_Cable', 'Cable', '', -1],
            ['X_AVM-DE_LTE', 'LTE', '', -1],
            ['unknown', 'unknown', '', -1]
        ]);
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateCommonLinkProperties();
        $this->SetTimerInterval('RefreshLinkProperties', $this->ReadPropertyInteger('RefreshInterval') * 1000);
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'WANAccessType':
                return $this->SetWANAccessType($Value);
            case 'RefreshLinkProperties':
                return $this->UpdateCommonLinkProperties();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    public function GetActiveProvider()
    {
        $result = $this->Send('X_AVM-DE_GetActiveProvider');

        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetCommonLinkProperties()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetTotalBytesSent()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalBytesReceived()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalPacketsSent()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalPacketsReceived()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function SetWANAccessType(string $WanAccessType)
    {
        $result = $this->Send('X_AVM-DE_SetWANAccessType', ['NewAccessType'=>$WanAccessType]);
        return $result !== false;
    }

    private function UpdateCommonLinkProperties()
    {
        $result = $this->GetCommonLinkProperties();
        if ($result === false) {
            return false;
        }

        $this->setIPSVariable('WANAccessType', 'WAN Access type', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING, 'FB.AccessType', true);
        $this->setIPSVariable('PhysicalLinkStatus', 'Physical Link Status', (string) $result['NewPhysicalLinkStatus'], VARIABLETYPE_STRING);
        $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
        $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
        $this->setIPSVariable('UpstreamMaxBitRate', 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->setIPSVariable('DownstreamMaxBitRate', 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        return true;
    }
}
