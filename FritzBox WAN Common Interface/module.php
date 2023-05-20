<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxWANCommonInterface extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/igdupnp/control/WANCommonIFC1',
        '/igd2upnp/control/WANCommonIFC1'
    ];
    protected static $EventSubURLArray = [
        '/igdupnp/control/WANCommonIFC1',
        '/igd2upnp/control/WANCommonIFC1'
    ];
    protected static $ServiceTypeArray = [
        'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1',
        'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1'
    ];
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->UnregisterProfile('FB.MByte');
        $this->RegisterPropertyInteger('RefreshInterval', 5);
        $this->RegisterPropertyInteger('RefreshLinkPropertiesInterval', 60);
        $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
        $this->RegisterTimer('RefreshLinkProperties', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshLinkProperties",true);');
        $this->RegisterAttributeInteger('Upstream', 0);
        $this->RegisterAttributeInteger('Downstream', 0);
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.kBit');
            $this->UnregisterProfile('FB.Speed');
            $this->UnregisterProfile('FB.MByte');
            $this->UnregisterProfile('FB.kbs');
            $this->UnregisterProfile('FB.AccessType');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshInfo', 0);
        $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
        $this->RegisterProfileFloat('FB.Speed', '', '', '%', 0, 100, 0, 2);
        $this->RegisterProfileFloat('FB.MByte', '', '', ' MB', 0, 0, 0, 2);
        $this->RegisterProfileFloat('FB.kbs', '', '', ' kb/s', 0, 0, 0, 2);
        $this->RegisterProfileStringEx('FB.AccessType', '', '', '', [
            ['DSL', 'DSL', '', -1],
            ['Ethernet', 'Ethernet', '', -1],
            ['X_AVM-DE_Fiber', 'Fiber', '', -1],
            ['X_AVMDE_UMTS', 'UMTS', '', -1],
            ['X_AVM-DE_Cable', 'Cable', '', -1],
            ['X_AVM-DE_LTE', 'LTE', '', -1],
            ['unknown', 'unknown', '', -1]
        ]);
        parent::ApplyChanges();

        $Index = $this->ReadPropertyInteger('Index');
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateAddonInfos();
        $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        $this->SetTimerInterval('RefreshLinkProperties', $this->ReadPropertyInteger('RefreshLinkPropertiesInterval') * 1000);
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshInfo':
                return $this->UpdateAddonInfos();

            case 'RefreshLinkProperties':
                return $this->UpdateCommonLinkProperties();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
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
        return $this->Send(__FUNCTION__);
    }
    public function GetTotalBytesReceived()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetTotalPacketsSent()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetTotalPacketsReceived()
    {
        return $this->Send(__FUNCTION__);
    }
    public function GetAddonInfos()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetDsliteStatus()
    {
        return $this->Send('X_AVM_DE_GetDsliteStatus');
    }
    public function GetIPTVInfos()
    {
        return $this->Send('X_AVM_DE_GetIPTVInfos');
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
        if (array_key_exists('PhysicalLinkStatus', $Event)) {
            $this->setIPSVariable('PhysicalLinkStatus', 'Physical Link Status', (string) $Event['PhysicalLinkStatus'], VARIABLETYPE_STRING);
            unset($Event['PhysicalLinkStatus']);
            $this->UpdateCommonLinkProperties();
        }

        parent::DecodeEvent($Event);
    }

    private function UpdateCommonLinkProperties()
    {
        $result = $this->GetCommonLinkProperties();
        if ($result === false) {
            return false;
        }

        $this->setIPSVariable('WANAccessType', 'WAN Access type', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING, 'FB.AccessType');
        $this->setIPSVariable('PhysicalLinkStatus', 'Physical Link Status', (string) $result['NewPhysicalLinkStatus'], VARIABLETYPE_STRING);
        $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
        $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
        $this->WriteAttributeInteger('Upstream', $Upstream);
        $this->WriteAttributeInteger('Downstream', $Downstream);
        $this->setIPSVariable('UpstreamMaxBitRate', 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->setIPSVariable('DownstreamMaxBitRate', 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        /* todo
        NewX_AVM-DE_DownstreamCurrentUtilization out X_AVM-DE_DownStreamCurrentUtilization
NewX_AVM-DE_UpstreamCurrentUtilization out X_AVM-DE_UpstreamCurrentUtilization
NewX_AVM-DE_DownstreamCurrentMaxSpeed out X_AVM-DE_DownstreamCurrentMaxSpeed
NewX_AVM-DE_UpstreamCurrentMaxSpeed out X_AVM-DE_UpstreamCurrentMaxSpeed
         */
        return true;
    }
    /* todo
    function AVM-DE_SetWANAccessType
    function X_AVM-DE_GetActiveProvider
     */
    private function UpdateAddonInfos()
    {
        $result = $this->GetAddonInfos();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('KByteSendRate', 'Sending rate', $result['NewByteSendRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs');
        $this->setIPSVariable('KByteReceiveRate', 'Receive rate', $result['NewByteReceiveRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs');
        $Downstream = $this->ReadAttributeInteger('Downstream');
        if ($Downstream > 0) {
            $this->setIPSVariable('LevelReceiveRate', 'Load download', (100 / ($Downstream / 8) * ($result['NewByteReceiveRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed');
        }
        $Upstream = $this->ReadAttributeInteger('Upstream');
        if ($Upstream > 0) {
            $this->setIPSVariable('LevelSendRate', 'Load upload', (100 / ($Upstream / 8) * ($result['NewByteSendRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed');
        }
        if (array_key_exists('NewX_AVM_DE_TotalBytesReceived64', $result)) {
            $send = $result['NewX_AVM_DE_TotalBytesSent64'];
            $recv = $result['NewX_AVM_DE_TotalBytesReceived64'];
        } else {
            $send = $result['NewTotalBytesSent'];
            $recv = $result['NewTotalBytesReceived'];
        }
        $this->setIPSVariable('TotalMBytesSent', 'Sent since connected', $send / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte');
        $this->setIPSVariable('TotalMBytesReceived', 'Received since connected', $recv / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte');

        if (array_key_exists('NewDNSServer1', $result)) {
            $this->setIPSVariable('UpnpControlEnabled', 'Allow automatic port forwarding via UPnP', $result['NewUpnpControlEnabled'], VARIABLETYPE_BOOLEAN, '~Switch');
        }
        if (array_key_exists('NewDNSServer1', $result)) {
            $this->setIPSVariable('DNSServer1', 'DNS-Server 1', (string) $result['NewDNSServer1'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewDNSServer2', $result)) {
            $this->setIPSVariable('DNSServer2', 'DNS-Server 2', (string) $result['NewDNSServer2'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewX_AVM_DE_WANAccessType', $result)) {
            $this->setIPSVariable('WANAccessType', 'WAN Access type', (string) $result['NewX_AVM_DE_WANAccessType'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewVoipDNSServer1', $result)) {
            $this->setIPSVariable('VoipDNSServer1', 'VoIP DNS-Server 1', (string) $result['NewVoipDNSServer1'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewVoipDNSServer2', $result)) {
            $this->setIPSVariable('VoipDNSServer2', 'VoIP DNS-Server 2', (string) $result['NewVoipDNSServer2'], VARIABLETYPE_STRING);
        }
        return true;
    }
}
