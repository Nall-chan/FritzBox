<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * DSL-Schnittstelle der FritzBox (nur Boxen mit DSL-Anschluss).
 * Synchronisationsraten, Störabstand, Dämpfung und Fehlerzähler.
 *
 * Skalierung: Störabstand (NoiseMargin) und Dämpfung (Attenuation) liefert die FritzBox
 * laut TR-064 (WANDSLInterfaceConfig, angelehnt an TR-098) in 0,1 dB. Nicht live geprüft,
 * da keine DSL-Box zum Testen vorhanden war.
 * Die Instanz-Kachel (HTML-SDK) zeigt Synchronisationsrate gegen Maximum, Störabstand, Dämpfung und Fehlerzähler.
 */
class FritzBoxDSLInterface extends FritzBoxModulBase
{
    use \FritzBoxModul\Tile;

    protected static $ControlUrlArray = [
        '/upnp/control/wandslifconfig1'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:WANDSLInterfaceConfig:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
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
        parent::ApplyChanges();
        $this->RegisterTile();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }

    /**
     * Liefert Status, Raten, Störabstand, Dämpfung und Sendeleistung.
     *
     * @return array|false
     */
    public function GetInfo(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die Fehlerzähler seit dem Start der FritzBox.
     *
     * @return array|false
     */
    public function GetStatisticsTotal(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert erweiterte DSL-Werte (u.a. Störabstand je Band, Profil, Modulation).
     *
     * @return array|false
     */
    public function GetDSLInfo(): mixed
    {
        return $this->Send('X_AVM-DE_GetDSLInfo');
    }

    /**
     * Liefert das Ergebnis der letzten DSL-Diagnose.
     *
     * @return array|false
     */
    public function GetDSLDiagnoseInfo(): mixed
    {
        return $this->Send('X_AVM-DE_GetDSLDiagnoseInfo');
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
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: die Statusvariablen der Schnittstelle (fehlende Variablen fehlen auch in der Kachel).
     */
    protected function GetTileData(): array
    {
        $Data = [];
        foreach ([
            'status'      => \FritzBox\DSLInterface\Variables::Status,
            'downRate'    => \FritzBox\DSLInterface\Variables::DownstreamCurrRate,
            'upRate'      => \FritzBox\DSLInterface\Variables::UpstreamCurrRate,
            'downMax'     => \FritzBox\DSLInterface\Variables::DownstreamMaxRate,
            'upMax'       => \FritzBox\DSLInterface\Variables::UpstreamMaxRate,
            'downMargin'  => \FritzBox\DSLInterface\Variables::DownstreamNoiseMargin,
            'upMargin'    => \FritzBox\DSLInterface\Variables::UpstreamNoiseMargin,
            'downAtt'     => \FritzBox\DSLInterface\Variables::DownstreamAttenuation,
            'upAtt'       => \FritzBox\DSLInterface\Variables::UpstreamAttenuation,
            'fec'         => \FritzBox\DSLInterface\Variables::FECErrors,
            'crc'         => \FritzBox\DSLInterface\Variables::CRCErrors,
            'erroredSecs' => \FritzBox\DSLInterface\Variables::ErroredSecs,
            'severeSecs'  => \FritzBox\DSLInterface\Variables::SeverelyErroredSecs
        ] as $Key => $Ident) {
            $Data[$Key] = $this->TileVariable($Ident);
        }
        return $Data;
    }

    protected function GetTileTextKeys(): array
    {
        return ['Download', 'Upload', 'of', 'Noise margin', 'Attenuation', 'Errors', 'No data'];
    }

    private function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::Status, 'DSL Status', (string) $Result['NewStatus'], VARIABLETYPE_STRING, '', false, 1);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::DownstreamCurrRate, 'Downstream current rate', (int) $Result['NewDownstreamCurrRate'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 2);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::UpstreamCurrRate, 'Upstream current rate', (int) $Result['NewUpstreamCurrRate'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 3);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::DownstreamMaxRate, 'Downstream max rate', (int) $Result['NewDownstreamMaxRate'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 4);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::UpstreamMaxRate, 'Upstream max rate', (int) $Result['NewUpstreamMaxRate'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 5);
        // 0,1 dB laut TR-064
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::DownstreamNoiseMargin, 'Downstream noise margin', (int) $Result['NewDownstreamNoiseMargin'] / 10, VARIABLETYPE_FLOAT, 'FB.dB', false, 6);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::UpstreamNoiseMargin, 'Upstream noise margin', (int) $Result['NewUpstreamNoiseMargin'] / 10, VARIABLETYPE_FLOAT, 'FB.dB', false, 7);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::DownstreamAttenuation, 'Downstream attenuation', (int) $Result['NewDownstreamAttenuation'] / 10, VARIABLETYPE_FLOAT, 'FB.dB', false, 8);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::UpstreamAttenuation, 'Upstream attenuation', (int) $Result['NewUpstreamAttenuation'] / 10, VARIABLETYPE_FLOAT, 'FB.dB', false, 9);

        // Fehlerzähler sind optional, Fehler nur im Debug
        $Stats = @$this->GetStatisticsTotal();
        if ($Stats === false) {
            $this->SendDebug('GetStatisticsTotal', 'not available', 0);
            $this->PushTile();
            return true;
        }
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::FECErrors, 'FEC errors', (int) $Stats['NewFECErrors'], VARIABLETYPE_INTEGER, '', false, 10);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::CRCErrors, 'CRC errors', (int) $Stats['NewCRCErrors'], VARIABLETYPE_INTEGER, '', false, 11);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::ErroredSecs, 'Errored seconds', (int) $Stats['NewErroredSecs'], VARIABLETYPE_INTEGER, '', false, 12);
        $this->setIPSVariable(\FritzBox\DSLInterface\Variables::SeverelyErroredSecs, 'Severely errored seconds', (int) $Stats['NewSeverelyErroredSecs'], VARIABLETYPE_INTEGER, '', false, 13);
        $this->PushTile();
        return true;
    }
}
