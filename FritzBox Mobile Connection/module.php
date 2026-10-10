<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * Mobilfunk-Verbindung der FritzBox (LTE/5G-Boxen oder USB-Mobilfunkstick).
 * Nur lesend; PIN/PUK sowie Zugangstechnik und Frequenzbänder werden bewusst nicht gesetzt.
 * Nicht live geprüft, da keine Box mit Mobilfunk zum Testen vorhanden war.
 * Annahme: SignalRSRP0/1 (i4) sind in dBm (übliche Einheit für RSRP, ca. -140 bis -44).
 */
class FritzBoxMobileConnection extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_wanmobileconn'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_WANMobileConnection:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterProfileInteger('FB.dBm', '', '', ' dBm', 0, 0, 0);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy()
    {
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
     * Liefert Aktivierung, SIM-Status und Fehlversuche für PIN/PUK.
     *
     * @return array|false
     */
    public function GetInfo()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert erweiterte Daten (Zugangstechnik, Roaming, Signalstärke, Laufzeit, APN, IMSI, Seriennummer).
     *
     * @return array|false
     */
    public function GetInfoEx()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die eingestellte, die möglichen und die aktuelle Zugangstechnik.
     *
     * @return array|false
     */
    public function GetAccessTechnology()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die bevorzugte und die möglichen bevorzugten Zugangstechniken.
     *
     * @return array|false
     */
    public function GetPreferredAccessTechnology()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die freigegebenen Frequenzbänder (LTE, 5G NSA, 5G SA).
     *
     * @return array|false
     */
    public function GetEnabledBandCapabilities()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert die vom Modem unterstützten Frequenzbänder (LTE, 5G NSA, 5G SA).
     *
     * @return array|false
     */
    public function GetBandCapabilities()
    {
        return $this->Send(__FUNCTION__);
    }

    private function UpdateInfo()
    {
        $Result = $this->GetInfo();
        if ($Result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\MobileConnection\Variables::Enabled, 'Mobile connection active', (bool) $Result['NewEnabled'], VARIABLETYPE_BOOLEAN, '~Switch', false, 1);
        $this->setIPSVariable(\FritzBox\MobileConnection\Variables::Status, 'SIM status', (string) $Result['NewStatus'], VARIABLETYPE_STRING, '', false, 2);

        // Erweiterte Daten sind optional, Fehler nur im Debug
        $Ex = @$this->GetInfoEx();
        if ($Ex === false) {
            $this->SendDebug('GetInfoEx', 'not available', 0);
            return true;
        }
        $this->setIPSVariable(\FritzBox\MobileConnection\Variables::CurrentAccessTechnology, 'Current access technology', (string) $Ex['NewCurrentAccessTechnology'], VARIABLETYPE_STRING, '', false, 3);
        $this->setIPSVariable(\FritzBox\MobileConnection\Variables::Roaming, 'Roaming', (bool) $Ex['NewRoaming'], VARIABLETYPE_BOOLEAN, '~Switch', false, 4);
        // 0 ist kein gültiger RSRP-Wert, sondern 'keine Verbindung': letzten Wert behalten
        if ((int) $Ex['NewSignalRSRP0'] != 0) {
            $this->setIPSVariable(\FritzBox\MobileConnection\Variables::SignalRSRP0, 'Signal RSRP antenna 1', (int) $Ex['NewSignalRSRP0'], VARIABLETYPE_INTEGER, 'FB.dBm', false, 5);
        }
        if ((int) $Ex['NewSignalRSRP1'] != 0) {
            $this->setIPSVariable(\FritzBox\MobileConnection\Variables::SignalRSRP1, 'Signal RSRP antenna 2', (int) $Ex['NewSignalRSRP1'], VARIABLETYPE_INTEGER, 'FB.dBm', false, 6);
        }
        return true;
    }
}
