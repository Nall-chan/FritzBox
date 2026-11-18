<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * WAN-Schnittstelle über TR-064 (WANCommonInterfaceConfig): Zugangsart, Status, maximale Bitraten,
 * aktuelle Raten, Auslastung und Zähler sowie (sofern die FritzBox es kann) der Status des Fallback-Zugangs.
 *
 * Raten und Auslastung kommen aus dem Online-Monitor (`X_AVM-DE_GetOnlineMonitor`), je Synchronisationsgruppe
 * (Zugang, z.B. `sync_cable`, `sync_lan0`). Die FritzBox liefert die letzten 20 Messpunkte in Byte/s, den neuesten zuerst
 * (live bestätigt: erster Messpunkt = aktuelle Rate des IGD-Dienstes, `max_ds` = Layer1-Rate / 8).
 *
 * @property array $TileMonitor Online-Monitor für die Kachel (Gruppen mit Messpunkten, aktive Gruppe)
 * @property bool $FallbackUnsupported Die FritzBox kennt `X_AVM-DE_GetFallbackProtectionInfo` nicht (nicht erneut fragen)
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
        $this->FallbackUnsupported = false;
        // RefreshInterval wie bis 0.95 für Zugangsart, Status und Bitraten; neu (sofort aktiv nach dem Update) Raten und Auslastung
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterPropertyInteger(\FritzBox\WANPhysicalInterface\Property::RefreshMonitorInterval, 5);
        $this->RegisterPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::RateVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::LoadVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::TotalVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::FallbackVariables, true);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
        $this->RegisterTimer(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\WANPhysicalInterface\Action::RefreshLinkProperties . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        $this->SetTimerInterval(\FritzBox\WANPhysicalInterface\Timer::RefreshLinkProperties, 0);
        $this->TileMonitor = [];
        $this->FallbackUnsupported = false;
        parent::ApplyChanges();
        $this->RegisterTile();
        // Abgewählte Variablen entfernen (Entscheidung des Nutzers in der Konfiguration)
        foreach (\FritzBox\WANPhysicalInterface\Variables::ByProperty as $Property => $Idents) {
            if (!$this->ReadPropertyBoolean($Property)) {
                foreach ($Idents as $Ident) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateCommonLinkProperties();
        $this->UpdateOnlineMonitor();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\WANPhysicalInterface\Property::RefreshMonitorInterval) * 1000);
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

    /**
     * Liefert manuelle, Sync-, Brutto-, Vertrags- und L2-Raten des aktiven Zugangs in Bit/s (nicht jede FritzBox, z.B. 5690/7690).
     *
     * @return array|false
     */
    public function GetAddonInfos(): mixed
    {
        return $this->Send('X_AVM-DE_GetAddonInfos');
    }

    /**
     * Liefert den Status des Fallback-Zugangs (Modus, primärer und sekundärer Zugang, aktiv, Ausfall- und Rückkehrzeit).
     * Nicht jede FritzBox kennt die Funktion (z.B. 5690/7690, nicht 6690).
     *
     * @return array|false
     */
    public function GetFallbackProtectionInfo(): mixed
    {
        return $this->Send('X_AVM-DE_GetFallbackProtectionInfo');
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
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateOnlineMonitor();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: Zugangsart, Status und maximale Bitraten (Variablen) sowie der Online-Monitor je Zugang.
     */
    protected function GetTileData(): array
    {
        return [
            'accessType' => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::WANAccessType),
            'linkStatus' => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::PhysicalLinkStatus),
            'maxDown'    => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::DownstreamMaxBitRate),
            'maxUp'      => $this->TileVariable(\FritzBox\WANPhysicalInterface\Variables::UpstreamMaxBitRate),
            'monitor'    => (object) $this->TileMonitor,
            // Anzeige folgt der Auswahl der Variablen: Rate als Text, Auslastung als Balken, Summen mit Verlauf
            'show'       => [
                'rate'    => $this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::RateVariables),
                'load'    => $this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::LoadVariables),
                'history' => $this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::TotalVariables)
            ]
        ];
    }

    protected function GetTileTextKeys(): array
    {
        return ['Download', 'Upload', 'Utilization', 'max.', 'No data', 'Cable', 'DSL', 'LAN', 'LTE', 'UMTS', 'Fiber', 'Mobile'];
    }

    /**
     * Zugangsart, Status und maximale Bitraten; bei Bedarf der Status des Fallback-Zugangs.
     */
    private function UpdateCommonLinkProperties(): bool
    {
        $result = $this->GetCommonLinkProperties();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::WANAccessType, 'WAN Access type', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING, 'FB.AccessType', true);
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::PhysicalLinkStatus, 'Physical Link Status', (string) $result['NewPhysicalLinkStatus'], VARIABLETYPE_STRING, 'FB.PhysicalLinkStatus');
        $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
        $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::UpstreamMaxBitRate, 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::DownstreamMaxBitRate, 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit');
        $this->UpdateFallbackInfo();
        $this->PushTile();
        return true;
    }

    /**
     * Status des Fallback-Zugangs (optional): Kennt die FritzBox die Funktion nicht, nur Debug und nicht erneut fragen.
     * Annahme: `NewX_AVM-DE_FallbackActive` 0/1, Zugänge als Text (nicht live getestet, die Testbox 6690 kennt die Funktion nicht).
     */
    private function UpdateFallbackInfo(): void
    {
        if ($this->FallbackUnsupported || !$this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::FallbackVariables)) {
            return;
        }
        $Result = $this->SendExQuiet('X_AVM-DE_GetFallbackProtectionInfo', 0);
        if (!is_array($Result)) {
            $this->FallbackUnsupported = true;
            $this->SendDebug('Fallback', 'not supported', 0);
            return;
        }
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::FallbackActive, 'Fallback active', (int) ($Result['NewX_AVM-DE_FallbackActive'] ?? 0) == 1, VARIABLETYPE_BOOLEAN, '~Switch');
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::PrimaryAccess, 'Primary access', (string) ($Result['NewX_AVM-DE_PrimaryAccess'] ?? ''), VARIABLETYPE_STRING);
        $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::SecondaryAccess, 'Secondary access', (string) ($Result['NewX_AVM-DE_SecondaryAccess'] ?? ''), VARIABLETYPE_STRING);
    }

    /**
     * Online-Monitor aller Synchronisationsgruppen: Raten und Auslastung des aktiven Zugangs, Zähler, Kachel.
     */
    private function UpdateOnlineMonitor(): bool
    {
        $First = $this->GetOnlineMonitor(0);
        if (!is_array($First)) {
            return false;
        }
        $Results = [$First];
        $Count = (int) ($First['NewTotalNumberSyncGroups'] ?? 1);
        for ($Index = 1; $Index < $Count; $Index++) {
            $Result = $this->GetOnlineMonitor($Index);
            if (is_array($Result)) {
                $Results[] = $Result;
            }
        }
        $Groups = \FritzBox\OnlineMonitor::Groups($Results);
        $Active = \FritzBox\OnlineMonitor::ActiveGroup($Groups);
        $this->TileMonitor = ['groups' => $Groups, 'active' => $Active];
        $Group = $Groups[$Active] ?? null;
        if ($Group !== null) {
            $Down = $Group['down'][0] ?? 0;
            $Up = $Group['up'][0] ?? 0;
            if ($this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::RateVariables)) {
                $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::RateDownstream, 'Download rate', $Down * 8 / 1e6, VARIABLETYPE_FLOAT, 'FB.MBits.Float');
                $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::RateUpstream, 'Upload rate', $Up * 8 / 1e6, VARIABLETYPE_FLOAT, 'FB.MBits.Float');
            }
            // Ohne Maximalwert (z.B. Ethernet ohne eingestellte Rate) keine Auslastung, Variablen behalten ihren Wert
            if ($this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::LoadVariables)) {
                if ($Group['maxDown'] > 0) {
                    $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::LoadDownstream, 'Load download', min(100, $Down * 100 / $Group['maxDown']), VARIABLETYPE_FLOAT, 'FB.Speed');
                }
                if ($Group['maxUp'] > 0) {
                    $this->setIPSVariable(\FritzBox\WANPhysicalInterface\Variables::LoadUpstream, 'Load upload', min(100, $Up * 100 / $Group['maxUp']), VARIABLETYPE_FLOAT, 'FB.Speed');
                }
            }
        }
        if ($this->ReadPropertyBoolean(\FritzBox\WANPhysicalInterface\Property::TotalVariables)) {
            $this->UpdateTotals();
        }
        $this->PushTile();
        return true;
    }

    /**
     * Gesamtzähler seit Verbindungsaufbau (64 Bit). Laut AVM liefert die FritzBox 0, wenn „Statusinformationen über UPnP
     * übertragen“ aus ist; dann behalten die Variablen ihren letzten Wert.
     */
    private function UpdateTotals(): void
    {
        foreach ([
            [\FritzBox\WANPhysicalInterface\Variables::TotalReceived, 'Received since connected', 'GetTotalBytesReceived'],
            [\FritzBox\WANPhysicalInterface\Variables::TotalSent, 'Sent since connected', 'GetTotalBytesSent']
        ] as [$Ident, $Name, $Function]) {
            $Bytes = $this->Send($Function);
            if (($Bytes === false) || ((int) $Bytes == 0)) {
                continue;
            }
            $this->setIPSVariable($Ident, $Name, (int) $Bytes / 1024 / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.GByte');
        }
    }
}
