<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * Glasfaser-Anschluss der FritzBox (X_AVM-DE_WANFiber, nur Boxen mit Glasfaser-Anschluss, z.B. FRITZ!Box 5690 Pro).
 * Betriebsart, optische Pegel und Zähler.
 *
 * Skalierung: Die optischen Pegel liefert die FritzBox laut AVM-Beschreibung in dBm/1000,
 * das Modul rechnet sie in dBm um. Die Einheit der Verbindungsraten (ConnectionRateDown/Up) nennt
 * die Beschreibung nicht, angenommen wird kBit/s wie bei den DSL-Raten (nicht geprüft).
 * Das Modul wurde nach der Beschreibung erstellt und nicht an einer Glasfaser-Box getestet.
 */
class FritzBoxWANFiber extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_wanfiber'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_WANFiber:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
        $this->RegisterProfileFloat(\FritzBox\WANFiber\Profile::Dbm, '', '', ' dBm', 0, 0, 0, 2);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy(): void
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile(\FritzBox\WANFiber\Profile::Dbm);
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        $this->MigrateDbmProfile();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }

    /**
     * Liefert Betriebsart, optische Pegel samt Schwellwerten und Daten des optischen Moduls (SFP).
     * Pegel und Schwellwerte in dBm/1000.
     *
     * @return array|false
     */
    public function GetInfo(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert zusätzliche Daten des GPON-Anschlusses. Nur bei Boxen, die GPON unterstützen.
     *
     * @return array|false
     */
    public function GetInfoGPON(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die Zähler und Verbindungsdaten des Glasfaser-Anschlusses.
     *
     * @return array|false
     */
    public function GetStatistics(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
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
     * Bis 0.93 nutzten die Pegel das Float-Profil FB.dBm, das mit dem gleichnamigen
     * Integer-Profil der Mobilfunk-Instanz kollidierte. Stellt die eigenen Pegel-Variablen auf
     * FB.dBm.Float um und löscht das alte Profil, wenn es vom Typ Float ist und keine andere
     * Variable es nutzt. Ein Integer-Profil FB.dBm gehört der Mobilfunk-Instanz und bleibt.
     */
    private function MigrateDbmProfile(): void
    {
        $Legacy = \FritzBox\WANFiber\Profile::LegacyDbm;
        if (!IPS_VariableProfileExists($Legacy)) {
            return;
        }
        if (IPS_GetVariableProfile($Legacy)['ProfileType'] != VARIABLETYPE_FLOAT) {
            return;
        }
        $Levels = [
            \FritzBox\WANFiber\Variables::OpticalSignalLevel   => ['Optical signal level', 2],
            \FritzBox\WANFiber\Variables::TransmitOpticalLevel => ['Transmit optical level', 3]
        ];
        foreach ($Levels as $Ident => [$Name, $Position]) {
            $VariableID = @$this->GetIDForIdent($Ident);
            if (($VariableID === false) || ($VariableID === 0)) {
                continue;
            }
            if (IPS_GetVariable($VariableID)['VariableProfile'] != $Legacy) {
                continue;
            }
            $this->MaintainVariable($Ident, $this->Translate($Name), VARIABLETYPE_FLOAT, \FritzBox\WANFiber\Profile::Dbm, $Position, true);
        }
        $this->UnregisterProfile($Legacy);
    }

    private function UpdateInfo()
    {
        $Info = $this->GetInfo();
        if ($Info === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::FiberMode, 'Fiber mode', (string) $Info['NewFiberMode'], VARIABLETYPE_STRING, '', false, 1);
        // dBm/1000 laut AVM
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::OpticalSignalLevel, 'Optical signal level', (int) $Info['NewOpticalSignalLevel'] / 1000, VARIABLETYPE_FLOAT, \FritzBox\WANFiber\Profile::Dbm, false, 2);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::TransmitOpticalLevel, 'Transmit optical level', (int) $Info['NewTransmitOpticalLevel'] / 1000, VARIABLETYPE_FLOAT, \FritzBox\WANFiber\Profile::Dbm, false, 3);
        // Zähler sind optional, Fehler nur im Debug
        $Stats = @$this->GetStatistics();
        if ($Stats === false) {
            $this->SendDebug('GetStatistics', 'not available', 0);
            return true;
        }
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::ConnectionRateDown, 'Downstream current rate', (int) $Stats['NewConnectionRateDown'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 4);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::ConnectionRateUp, 'Upstream current rate', (int) $Stats['NewConnectionRateUp'], VARIABLETYPE_INTEGER, 'FB.kBit', false, 5);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::PacketErrorsSent, 'Packet errors sent', (int) $Stats['NewPacketErrorsSent'], VARIABLETYPE_INTEGER, '', false, 6);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::PacketErrorsReceived, 'Packet errors received', (int) $Stats['NewPacketErrorsReceived'], VARIABLETYPE_INTEGER, '', false, 7);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::Resyncs, 'Resyncs', (int) $Stats['NewResyncs'], VARIABLETYPE_INTEGER, '', false, 8);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::MinutesInShowtime, 'Minutes in showtime', (int) $Stats['NewMinutesInShowtime'], VARIABLETYPE_INTEGER, '', false, 9);
        return true;
    }
}
