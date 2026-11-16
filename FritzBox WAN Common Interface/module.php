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

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 5);
        $this->RegisterPropertyInteger(\FritzBox\WANCommonInterface\Property::RefreshLinkPropertiesInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
        $this->RegisterTimer(\FritzBox\WANCommonInterface\Timer::RefreshLinkProperties, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\WANCommonInterface\Action::RefreshLinkProperties . '",true);');
        $this->RegisterAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Upstream, 0);
        $this->RegisterAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Downstream, 0);
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();

        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateAddonInfos();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        $this->SetTimerInterval(\FritzBox\WANCommonInterface\Timer::RefreshLinkProperties, $this->ReadPropertyInteger(\FritzBox\WANCommonInterface\Property::RefreshLinkPropertiesInterval) * 1000);
    }

    public function GetCommonLinkProperties(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetTotalBytesSent(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalBytesReceived(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalPacketsSent(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetTotalPacketsReceived(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetAddonInfos(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetDsliteStatus(): bool
    {
        $result = $this->Send('X_AVM_DE_GetDsliteStatus');
        if ($result === false) {
            return false;
        }
        return (bool) $result;
    }

    public function GetIPTVInfos(): mixed
    {
        $result = $this->Send('X_AVM_DE_GetIPTVInfos');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetConfigurationForm(): string
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
                    'Function'   => \FritzBox\IOFunction::HasIGD2
                ]
            ));
            $HasIGD2 = \FritzBox\IOResult::Decode($Ret);
            $this->SendDebug('Use IGD2', $HasIGD2, 0);
            if (!$HasIGD2) {
                unset($Form['elements'][0]['options'][2]);
            }
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateAddonInfos();
            case \FritzBox\WANCommonInterface\Action::RefreshLinkProperties:
                return $this->UpdateCommonLinkProperties();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    protected function DecodeEvent(array $Event): void
    {
        if (array_key_exists('PhysicalLinkStatus', $Event)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::PhysicalLinkStatus, 'Physical Link Status', (string) $Event['PhysicalLinkStatus'], VARIABLETYPE_STRING);
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

        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::PhysicalLinkStatus, 'Physical Link Status', (string) $result['NewPhysicalLinkStatus'], VARIABLETYPE_STRING);
        $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
        $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
        $this->WriteAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Upstream, $Upstream);
        $this->WriteAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Downstream, $Downstream);
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::UpstreamMaxBitRate, 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::DownstreamMaxBitRate, 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        /* todo
        NewX_AVM-DE_DownstreamCurrentUtilization out X_AVM-DE_DownStreamCurrentUtilization
NewX_AVM-DE_UpstreamCurrentUtilization out X_AVM-DE_UpstreamCurrentUtilization
NewX_AVM-DE_DownstreamCurrentMaxSpeed out X_AVM-DE_DownstreamCurrentMaxSpeed
NewX_AVM-DE_UpstreamCurrentMaxSpeed out X_AVM-DE_UpstreamCurrentMaxSpeed
         */
        return true;
    }

    private function UpdateAddonInfos()
    {
        $result = $this->GetAddonInfos();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::KByteSendRate, 'Sending rate', $result['NewByteSendRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs');
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::KByteReceiveRate, 'Receive rate', $result['NewByteReceiveRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs');
        $Downstream = $this->ReadAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Downstream);
        if ($Downstream > 0) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::LevelReceiveRate, 'Load download', (100 / ($Downstream / 8) * ($result['NewByteReceiveRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed');
        }
        $Upstream = $this->ReadAttributeInteger(\FritzBox\WANCommonInterface\Attribute::Upstream);
        if ($Upstream > 0) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::LevelSendRate, 'Load upload', (100 / ($Upstream / 8) * ($result['NewByteSendRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed');
        }
        if (array_key_exists('NewX_AVM_DE_TotalBytesReceived64', $result)) {
            $send = $result['NewX_AVM_DE_TotalBytesSent64'];
            $recv = $result['NewX_AVM_DE_TotalBytesReceived64'];
        } else {
            $send = $result['NewTotalBytesSent'];
            $recv = $result['NewTotalBytesReceived'];
        }
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::TotalMBytesSent, 'Sent since connected', $send / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte');
        $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::TotalMBytesReceived, 'Received since connected', $recv / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte');

        if (array_key_exists('NewDNSServer1', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::UpnpControlEnabled, 'Allow automatic port forwarding via UPnP', $result['NewUpnpControlEnabled'], VARIABLETYPE_BOOLEAN, '~Switch');
        }
        if (array_key_exists('NewDNSServer1', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::DNSServer1, 'DNS-Server 1', (string) $result['NewDNSServer1'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewDNSServer2', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::DNSServer2, 'DNS-Server 2', (string) $result['NewDNSServer2'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewX_AVM_DE_WANAccessType', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::WANAccessType, 'WAN Access type', (string) $result['NewX_AVM_DE_WANAccessType'], VARIABLETYPE_STRING, 'FB.AvmAccessType');
        }
        if (array_key_exists('NewVoipDNSServer1', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::VoipDNSServer1, 'VoIP DNS-Server 1', (string) $result['NewVoipDNSServer1'], VARIABLETYPE_STRING);
        }
        if (array_key_exists('NewVoipDNSServer2', $result)) {
            $this->setIPSVariable(\FritzBox\WANCommonInterface\Variables::VoipDNSServer2, 'VoIP DNS-Server 2', (string) $result['NewVoipDNSServer2'], VARIABLETYPE_STRING);
        }
        return true;
    }
}
