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

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
        $this->RegisterProfileFloat('FB.dBm', '', '', ' dBm', 0, 0, 0, 2);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.dBm');
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        parent::ApplyChanges();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
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
     * Liefert Betriebsart, optische Pegel samt Schwellwerten und Daten des optischen Moduls (SFP).
     * Pegel und Schwellwerte in dBm/1000.
     *
     * @return array|false
     */
    public function GetInfo()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert zusätzliche Daten des GPON-Anschlusses. Nur bei Boxen, die GPON unterstützen.
     *
     * @return array|false
     */
    public function GetInfoGPON()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die Zähler und Verbindungsdaten des Glasfaser-Anschlusses.
     *
     * @return array|false
     */
    public function GetStatistics()
    {
        return $this->Send(__FUNCTION__);
    }

    private function UpdateInfo()
    {
        $Info = $this->GetInfo();
        if ($Info === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::FiberMode, 'Fiber mode', (string) $Info['NewFiberMode'], VARIABLETYPE_STRING, '', false, 1);
        // dBm/1000 laut AVM
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::OpticalSignalLevel, 'Optical signal level', (int) $Info['NewOpticalSignalLevel'] / 1000, VARIABLETYPE_FLOAT, 'FB.dBm', false, 2);
        $this->setIPSVariable(\FritzBox\WANFiber\Variables::TransmitOpticalLevel, 'Transmit optical level', (int) $Info['NewTransmitOpticalLevel'] / 1000, VARIABLETYPE_FLOAT, 'FB.dBm', false, 3);
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
