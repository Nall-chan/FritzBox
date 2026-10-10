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
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\WANPhysicalInterface\Action::RefreshLinkProperties . '",true);');
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
        $this->SetTimerInterval(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0);
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
        $this->SetTimerInterval(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\WANPhysicalInterface\Variables::WANAccessType:
                return $this->SetWANAccessType($Value);
            case \FritzBox\WANPhysicalInterface\Action::RefreshLinkProperties:
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

    /**
     * Liefert den Online-Monitor (Verlauf der Auslastung) einer Synchronisationsgruppe.
     * Die Werte *_bps sind kommagetrennte Listen der letzten Messpunkte.
     *
     * @param int $SyncGroupIndex Index der Synchronisationsgruppe, beginnend bei 0 (Anzahl steht in NewTotalNumberSyncGroups)
     *
     * @return array|false
     */
    public function GetOnlineMonitor(int $SyncGroupIndex)
    {
        if ($SyncGroupIndex < 0) {
            $this->ReportError($this->Translate('Invalid index.'));
            return false;
        }
        return $this->Send('X_AVM-DE_GetOnlineMonitor', [
            'NewSyncGroupIndex'=> $SyncGroupIndex
        ]);
    }

    private function UpdateCommonLinkProperties()
    {
        $result = $this->GetCommonLinkProperties();
        if ($result === false) {
            return false;
        }

        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::WANAccessType, 'WAN Access type', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING, 'FB.AccessType', true);
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::PhysicalLinkStatus, 'Physical Link Status', (string) $result['NewPhysicalLinkStatus'], VARIABLETYPE_STRING);
        $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
        $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::UpstreamMaxBitRate, 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::DownstreamMaxBitRate, 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        return true;
    }
}
