<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * @property array $TileStations Senderlisten für die Kachel (Liste => [['name', 'url'], …])
 * @property bool $TileStationsLoaded Senderlisten erfolgreich geladen (sonst neuer Versuch beim nächsten Abruf)
 */
class FritzBoxDVBC extends FritzBoxModulBase
{
    use \FritzBoxModul\Tile;

    protected static $ControlUrlArray = [
        '/upnp/control/x_media'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Media:1'
    ];

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->TileStations = [];
        $this->TileStationsLoaded = false;
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        $this->TileStations = [];
        $this->TileStationsLoaded = false;
        parent::ApplyChanges();
        $this->RegisterTile();
        $this->RegisterVariableString(\FritzBox\DVBC\Variables::StationSearchMode, $this->Translate('Station search'), $this->GetPresentation('FB.StartStop', VARIABLETYPE_STRING, true), 0);
        $this->EnableAction(\FritzBox\DVBC\Variables::StationSearchMode);
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
        $this->LoadStations();
    }

    public function GetInfo(): mixed
    {
        $Result = $this->Send('GetInfo');
        if ($Result === false) {
            return false;
        }
        return $Result;
    }

    public function SetDVBCEnable(bool $State): bool
    {
        $Result = $this->Send('SetDVBCEnable', [
            'NewDVBCEnabled'         => (int) $State
        ]);
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DVBC\Variables::DVBCEnabled, 'DVBC enabled', $State, VARIABLETYPE_BOOLEAN, '~Switch', true);
        return true;
    }

    public function StationSearch(string $Mode): bool
    {
        $Result = $this->Send('StationSearch', [
            'NewStationSearchMode'         => $Mode
        ]);
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DVBC\Variables::StationSearchStatus, 'Station search status', (string) $Result, VARIABLETYPE_STRING, 'FB.ActiveInactive');
        return true;
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
            case \FritzBox\Tile\Action::Stations:
                return $this->LoadStations();
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\DVBC\Variables::DVBCEnabled:
                return $this->SetDVBCEnable((bool) $Value);
            case \FritzBox\DVBC\Variables::StationSearchMode:
                return $this->StationSearch((string) $Value);
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: Senderlisten der FritzBox.
     */
    protected function GetTileData(): array
    {
        return ['lists' => $this->TileStations, 'loaded' => $this->TileStationsLoaded];
    }

    protected function GetTileTextKeys(): array
    {
        return ['TV HD', 'TV SD', 'Radio', 'Stations', 'Search', 'Copy link', 'Link copied', 'No stations', 'Reload station list', 'Station list not available.'];
    }

    /**
     * Lädt die Senderlisten (m3u) vom Webserver der FritzBox und sendet sie an die Kachel.
     * Die Adressen sind RTSP-URLs der FritzBox (Wiedergabe nur im eigenen Netz, RTSP über UDP).
     */
    private function LoadStations(): bool
    {
        $Lists = [];
        $Loaded = false;
        foreach (\FritzBox\DVBC\Lists::Paths as $Key => $Path) {
            $Content = $this->LoadWebData($Path);
            if ($Content === false) {
                $Lists[$Key] = [];
                continue;
            }
            $Loaded = true;
            $Lists[$Key] = \FritzBox\M3U::Parse($Content);
            $this->SendDebug('Stations ' . $Key, count($Lists[$Key]), 0);
        }
        $this->TileStations = $Lists;
        $this->TileStationsLoaded = $Loaded;
        $this->PushTile();
        return $Loaded;
    }

    private function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }
        // Nach einem Sendersuchlauf (Status ändert sich) oder fehlgeschlagenem Laden die Senderlisten neu laden
        $OldStatus = @$this->GetValue(\FritzBox\DVBC\Variables::StationSearchStatus);
        if (!$this->TileStationsLoaded || (($OldStatus !== false) && ($OldStatus !== (string) $Result['NewStationSearchStatus']))) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'' . \FritzBox\Tile\Action::Stations . '\',true);');
        }
        $this->setIPSVariable(\FritzBox\DVBC\Variables::DVBCEnabled, 'DVBC enabled', (int) $Result['NewDVBCEnabled'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', true);
        $this->setIPSVariable(\FritzBox\DVBC\Variables::StationSearchStatus, 'Station search status', (string) $Result['NewStationSearchStatus'], VARIABLETYPE_STRING, 'FB.ActiveInactive');
        $this->setIPSVariable(\FritzBox\DVBC\Variables::SearchProgress, 'Search progress', (int) $Result['NewSearchProgress'], VARIABLETYPE_INTEGER, 'FB.Intensity');
        return true;
    }
}
