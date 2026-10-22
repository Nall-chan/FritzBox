<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * IPv6-Firewall der FritzBox (WANIPv6FirewallControl, nur IGD2).
 * Status der Firewall und Verwaltung von Pinholes (IPv6-Freigaben für eingehende Verbindungen).
 *
 * Anders als bei den Port-Weiterleitungen (IPv4) gibt es keine Aktion zum Auflisten der Pinholes.
 * Ein Pinhole ist nur über die UniqueID erreichbar, die AddPinhole zurückliefert.
 */
class FritzBoxWANIPv6Firewall extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/igd2upnp/control/WANIPv6Firewall1'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:schemas-upnp-org:service:WANIPv6FirewallControl:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
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
     * Liefert, ob die Firewall aktiv ist und ob eingehende Pinholes erlaubt sind.
     *
     * @return array|false Schlüssel FirewallEnabled und InboundPinholeAllowed
     */
    public function GetFirewallStatus()
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Legt ein Pinhole an (erlaubt eingehende IPv6-Verbindungen zu einem Gerät).
     *
     * @param string $RemoteHost     IPv6-Adresse des Absenders, leer = beliebig
     * @param int    $RemotePort     Port des Absenders, 0 = beliebig
     * @param string $InternalClient IPv6-Adresse des Geräts im Heimnetz
     * @param int    $InternalPort   Port am Gerät, 0 = beliebig
     * @param int    $Protocol       IP-Protokollnummer, z.B. 6 = TCP, 17 = UDP, 65535 = alle
     * @param int    $LeaseTime      Gültigkeit in Sekunden (1 bis 86400)
     *
     * @return int|false UniqueID des Pinholes
     */
    public function AddPinhole(string $RemoteHost, int $RemotePort, string $InternalClient, int $InternalPort, int $Protocol, int $LeaseTime)
    {
        if (($RemoteHost !== '') && !filter_var($RemoteHost, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->ReportError($this->Translate('Invalid IPv6 address.'));
            return false;
        }
        if (!filter_var($InternalClient, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $this->ReportError($this->Translate('Invalid IPv6 address.'));
            return false;
        }
        if (($RemotePort < 0) || ($RemotePort > 65535) || ($InternalPort < 0) || ($InternalPort > 65535)) {
            $this->ReportError($this->Translate('Invalid port.'));
            return false;
        }
        if (($Protocol < 0) || ($Protocol > 65535)) {
            $this->ReportError($this->Translate('Invalid protocol.'));
            return false;
        }
        if (($LeaseTime < 1) || ($LeaseTime > 86400)) {
            $this->ReportError($this->Translate('Invalid lease time.'));
            return false;
        }
        $result = $this->Send(__FUNCTION__, [
            'RemoteHost'    => $RemoteHost,
            'RemotePort'    => $RemotePort,
            'InternalClient'=> $InternalClient,
            'InternalPort'  => $InternalPort,
            'Protocol'      => $Protocol,
            'LeaseTime'     => $LeaseTime
        ]);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    /**
     * Ändert die Gültigkeit eines Pinholes.
     *
     * @param int $UniqueID  UniqueID, wie von AddPinhole geliefert
     * @param int $LeaseTime Neue Gültigkeit in Sekunden (1 bis 86400)
     *
     * @return bool
     */
    public function UpdatePinhole(int $UniqueID, int $LeaseTime)
    {
        if (($UniqueID < 0) || ($UniqueID > 65535)) {
            $this->ReportError($this->Translate('Invalid ID.'));
            return false;
        }
        if (($LeaseTime < 1) || ($LeaseTime > 86400)) {
            $this->ReportError($this->Translate('Invalid lease time.'));
            return false;
        }
        $result = $this->Send(__FUNCTION__, [
            'UniqueID'    => $UniqueID,
            'NewLeaseTime'=> $LeaseTime
        ]);
        return $result !== false;
    }

    /**
     * Löscht ein Pinhole.
     *
     * @param int $UniqueID UniqueID, wie von AddPinhole geliefert
     *
     * @return bool
     */
    public function DeletePinhole(int $UniqueID)
    {
        if (($UniqueID < 0) || ($UniqueID > 65535)) {
            $this->ReportError($this->Translate('Invalid ID.'));
            return false;
        }
        $result = $this->Send(__FUNCTION__, [
            'UniqueID'=> $UniqueID
        ]);
        return $result !== false;
    }

    private function UpdateInfo()
    {
        $Status = $this->GetFirewallStatus();
        if ($Status === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPv6Firewall\Variables::FirewallEnabled, 'IPv6 firewall enabled', (bool) $Status['FirewallEnabled'], VARIABLETYPE_BOOLEAN, '', false, 1);
        $this->setIPSVariable(\FritzBox\WANIPv6Firewall\Variables::InboundPinholeAllowed, 'Inbound pinholes allowed', (bool) $Status['InboundPinholeAllowed'], VARIABLETYPE_BOOLEAN, '', false, 2);
        return true;
    }
}
