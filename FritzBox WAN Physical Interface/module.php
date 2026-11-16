<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * Physikalische WAN-Schnittstelle: Zugangsart, Status und maximale Bitraten.
 * Die Instanz-Kachel (HTML-SDK) zeigt zusätzlich die Auslastung der letzten Messpunkte als Diagramm
 * (aus GetCommonLinkProperties, kein zusätzlicher Abruf).
 *
 * @property array $TileMonitor Auslastung für die Kachel (Messpunkte Down/Up, maximale Bitraten)
 */
class FritzBoxWANPhysicalInterface extends FritzBoxModulBase
{
    use \FritzBoxModul\Tile;

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

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->TileMonitor = [];
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\WANPhysicalInterface\Action::RefreshLinkProperties . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0);
        $this->TileMonitor = [];
        parent::ApplyChanges();
        $this->RegisterTile();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateCommonLinkProperties();
        $this->SetTimerInterval(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
    }

    public function GetActiveProvider(): mixed
    {
        $result = $this->Send('X_AVM-DE_GetActiveProvider');

        if ($result === false) {
            return false;
        }
        return $result;
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

    public function SetWANAccessType(string $WanAccessType): bool
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
    public function GetOnlineMonitor(int $SyncGroupIndex): mixed
    {
        if ($SyncGroupIndex < 0) {
            $this->ReportError($this->Translate('Invalid index.'));
            return false;
        }
        return $this->Send('X_AVM-DE_GetOnlineMonitor', [
            'NewSyncGroupIndex'=> $SyncGroupIndex
        ]);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        if ($this->ProcessTileAction($Ident, $Value)) {
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

    /**
     * Daten der Kachel: Zugangsart, Status und maximale Bitraten (Variablen) sowie die Auslastung der letzten Messpunkte.
     */
    protected function GetTileData(): array
    {
        return [
            'accessType' => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::WANAccessType),
            'linkStatus' => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::PhysicalLinkStatus),
            'maxDown'    => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::DownstreamMaxBitRate),
            'maxUp'      => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::UpstreamMaxBitRate),
            'monitor'    => (object) $this->TileMonitor
        ];
    }

    protected function GetTileTextKeys(): array
    {
        return ['Download', 'Upload', 'Utilization', 'max.', 'No data'];
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
        // Auslastung der letzten Messpunkte (kommagetrennt). Annahme: Byte/s wie beim Online-Monitor
        // (Plausibilität: Newmax_ds = maximale Bitrate / 8, siehe README), nicht von AVM dokumentiert.
        $Split = fn (string $List): array => array_map('intval', array_filter(explode(',', $List), 'strlen'));
        $this->TileMonitor = [
            'down'    => $Split((string) ($result['NewX_AVM-DE_DownstreamCurrentUtilization'] ?? '')),
            'up'      => $Split((string) ($result['NewX_AVM-DE_UpstreamCurrentUtilization'] ?? '')),
            'maxDown' => (int) $result['NewLayer1DownstreamMaxBitRate'],
            'maxUp'   => (int) $result['NewLayer1UpstreamMaxBitRate']
        ];
        $this->PushTile();
        return true;
    }
}
