<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * WAN IP-Verbindung (IGD bzw. IGD2): Verbindungsstatus, Verbindungsdauer, externe Adressen und DNS-Server.
 * Als einziges Modul der Library abonniert sie die UPnP-Ereignisse der FritzBox (eigener WebHook), damit Abbruch,
 * Neuaufbau und neue Adressen sofort ankommen. Erreicht die FritzBox den WebHook nicht, bleibt es bei der Abfrage im Intervall.
 *
 * @property string $SID SID des aktuellen Abos, leer ohne Abo
 * @property string $SubscribedURL EventSubURL, mit der die SID angemeldet wurde (die Eigenschaft Index kann sich ändern)
 * @property bool $EventReceived Ereignis seit dem letzten neuen Abo eingetroffen
 * @property bool $EventsUnavailable Echtzeitereignisse nicht verfügbar (bereits ins Log gemeldet)
 */
class FritzBoxWANIPConnection extends FritzBoxModulBase
{
    /**
     * Stand der Konfigurationsmigration (1 = ab Version 0.93: Index 0 = IGD, 1 = IGD2).
     */
    private const CurrentMigrationVersion = 1;

    /**
     * Event-URLs je Index (IGD, IGD2).
     */
    private const EventSubURLs = [
        '/igdupnp/control/WANIPConn1',
        '/igd2upnp/control/WANIPConn2'
    ];

    protected static $ControlUrlArray = [
        '/igdupnp/control/WANIPConn1',
        '/igd2upnp/control/WANIPConn2'
    ];
    protected static $ServiceTypeArray = [
        'urn:schemas-upnp-org:service:WANIPConnection:1',
        'urn:schemas-upnp-org:service:WANIPConnection:2'
    ];

    /**
     * Stellt die Dienste früherer Versionen auf `IGD` (0) bzw. `IGD2` (1) um.
     * Ohne das Attribut `MigrationVersion` stammt die Instanz aus einer Version vor 0.93:
     * - Index 1 (`IGD2 (< FritzOS 8.10)`, in 0.91/0.92 kurzzeitig `IGD2`) wird `IGD` (0), da IGD auf allen Boxen funktioniert.
     * - Index 2 (`IGD2 (> FritzOS 8.10)`) wird `IGD2` (1).
     *
     * @param string $JSONData
     * @return string
     */
    public function Migrate(string $JSONData): string
    {
        // Als Objekt dekodieren, damit leere Objekte (attributes, buffers) nicht zu [] werden
        $Data = json_decode($JSONData);
        if (!isset($Data->attributes) || !is_object($Data->attributes)) {
            $Data->attributes = new stdClass();
        }
        if (!property_exists($Data->attributes, \FritzBox\WANIPConnection\Attribute::MigrationVersion)) {
            if (isset($Data->configuration->{\FritzBox\Base\Property::Index})) {
                switch ($Data->configuration->{\FritzBox\Base\Property::Index}) {
                    case 1:
                        $Data->configuration->{\FritzBox\Base\Property::Index} = 0;
                        break;
                    case 2:
                        $Data->configuration->{\FritzBox\Base\Property::Index} = 1;
                        break;
                }
            }
            $Data->attributes->{\FritzBox\WANIPConnection\Attribute::MigrationVersion} = self::CurrentMigrationVersion;
        }
        return json_encode($Data);
    }

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterAttributeInteger(\FritzBox\WANIPConnection\Attribute::MigrationVersion, self::CurrentMigrationVersion);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
        $this->RegisterTimer(\FritzBox\WANIPConnection\Timer::RenewSubscription, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\WANIPConnection\Action::Subscribe . '",true);');
        $this->SID = '';
        $this->SubscribedURL = '';
        $this->EventReceived = false;
        $this->EventsUnavailable = false;
        // IPSModuleStrict: flüchtiger WebHook (Symcon setzt /hook/ davor und entfernt ihn beim Löschen bzw. Neustart selbst)
        $this->RegisterHook(\FritzBox\WANIPConnection\Hook::Name . $this->InstanceID);
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        $this->Unsubscribe();
        // EventsUnavailable bleibt bewusst erhalten: Die Meldung im Log erscheint nur einmal, nicht bei jedem Neuverbinden der IO
        $this->EventReceived = false;

        parent::ApplyChanges();
        $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RegisterVariableInteger(\FritzBox\WANIPConnection\Variables::ConnectionAction, $this->Translate('Control connection'), $this->GetPresentation('FB.Connect', VARIABLETYPE_INTEGER, true), 0);
        $this->EnableAction(\FritzBox\WANIPConnection\Variables::ConnectionAction);

        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if ($this->HasActiveParent()) {
            $this->Subscribe();
        }
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        if (($Message == IPS_KERNELSHUTDOWN) && IPS_InstanceExists($this->InstanceID)) {
            $this->Unsubscribe();
            return;
        }
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
    }

    public function GetConnectionTypeInfo(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    public function ForceTermination(): bool
    {
        $result = $this->Send(__FUNCTION__);
        return $result !== false;
    }

    public function RequestTermination(): bool
    {
        $result = $this->Send(__FUNCTION__);
        return $result !== false;
    }

    public function RequestConnection(): bool
    {
        $result = $this->Send(__FUNCTION__);
        return $result !== false;
    }

    public function GetStatusInfo(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetExternalIPAddress(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetDNSServer(): mixed
    {
        $result = $this->Send('X_AVM_DE_GetDNSServer');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetExternalIPv6Address(): mixed
    {
        $result = $this->Send('X_AVM_DE_GetExternalIPv6Address');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetIPv6DNSServer(): mixed
    {
        $result = $this->Send('X_AVM_DE_GetIPv6DNSServer');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetIPv6Prefix(): mixed
    {
        $result = $this->Send('X_AVM_DE_GetIPv6Prefix');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetNATRSIPStatus(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    public function GetWarnDisconnectDelay(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetIdleDisconnectTime(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function GetAutoDisconnectTime(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return (int) $result;
    }

    public function SetWarnDisconnectDelay(int $DelayTime): bool
    {
        $result = $this->Send(__FUNCTION__, ['NewWarnDisconnectDelay'=>$DelayTime]);
        return $result !== false;
    }

    public function SetIdleDisconnectTime(int $Timeout): bool
    {
        $result = $this->Send(__FUNCTION__, ['NewIdleDisconnectTime'=>$Timeout]);
        return $result !== false;
    }

    public function SetAutoDisconnectTime(int $AutoDisconnectTime): bool
    {
        $result = $this->Send(__FUNCTION__, ['NewAutoDisconnectTime'=>$AutoDisconnectTime]);
        return $result !== false;
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
        // Hinweis, wenn die FritzBox den WebHook nicht erreicht (Werte nur im Intervall)
        $Form['elements'][2]['visible'] = $this->EventsUnavailable;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Ereignisse der FritzBox (GENA NOTIFY) an den eigenen WebHook.
     */
    protected function ProcessHookData(): void
    {
        if ($_SERVER['REQUEST_METHOD'] != 'NOTIFY') {
            http_response_code(405);
            return;
        }
        // Der WebHook gehört nur dieser Instanz. Die SID wird nicht erzwungen: Das erste Ereignis kann eintreffen,
        // bevor die SID des neuen Abos gespeichert ist (Antwort auf SUBSCRIBE noch unterwegs).
        if (($_SERVER['HTTP_SID'] ?? '') !== $this->SID) {
            $this->SendDebug('Event SID', ($_SERVER['HTTP_SID'] ?? '') . ' (current: ' . $this->SID . ')', 0);
        }
        $Data = file_get_contents('php://input');
        $this->SendDebug('Event', \FritzBox\DebugMask::Xml((string) $Data), 0);
        try {
            $Event = \FritzBox\EventParser::Parse((string) $Data);
        } catch (\Throwable $th) {
            $this->SendDebug('Event decode error', $th->getMessage(), 0);
            http_response_code(400);
            return;
        }
        $this->EventReceived = true;
        http_response_code(200);
        $this->DecodeEvent($Event);
    }

    protected function IOChangeState(int $State): void
    {
        if ($State != IS_ACTIVE) {
            // IO getrennt: Abo ist auf der FritzBox nicht mehr gültig bzw. nicht erreichbar
            $this->SID = '';
            $this->SubscribedURL = '';
            $this->SetTimerInterval(\FritzBox\WANIPConnection\Timer::RenewSubscription, 0);
        }
        parent::IOChangeState($State);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\WANIPConnection\Action::Subscribe:
                return $this->Subscribe();
            case \FritzBox\WANIPConnection\Variables::ConnectionAction:
                return $this->ForceTermination();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Wertet ein Ereignis aus: Verbindungsstatus sofort setzen, bei bestehender Verbindung oder neuer Adresse alle Werte abfragen.
     *
     * @param array $Event Ereignisvariablen der FritzBox (Name => Wert als Text).
     */
    private function DecodeEvent(array $Event): void
    {
        $this->SendDebug('Event', \FritzBox\DebugMask::Values($Event), 0);
        $Update = array_key_exists('ExternalIPAddress', $Event) || array_key_exists('ExternalIPv6Address', $Event);
        if (array_key_exists('ConnectionStatus', $Event)) {
            $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::ConnectionStatus, 'IP connection status', ($Event['ConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', false, 1);
            $Update = $Update || ($Event['ConnectionStatus'] == 'Connected');
        }
        if ($Update) {
            $this->UpdateInfo();
        }
    }

    /**
     * Meldet die Ereignisse bei der FritzBox an (neu) bzw. verlängert das Abo.
     * Kommt nach einem neuen Abo kein erstes Ereignis, erreicht die FritzBox den WebHook nicht: einmal als Hinweis ins Log,
     * Hinweis im Formular, die Werte kommen dann nur über das Intervall.
     */
    private function Subscribe(): bool
    {
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if (!isset(self::EventSubURLs[$Index]) || !$this->HasActiveParent()) {
            $this->SID = '';
            return false;
        }
        $isNewSubscription = ($this->SID === '');
        if ($isNewSubscription) {
            $this->EventReceived = false;
        }
        $this->SendDebug('Subscribe', $this->SID, 0);
        $Ret = @$this->SendDataToParent(json_encode(
            [
                'DataID'      => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'    => \FritzBox\IOFunction::Subscribe,
                'EventSubURL' => self::EventSubURLs[$Index],
                'SID'         => $this->SID,
                'Callback'    => \FritzBox\WANIPConnection\Hook::Path . $this->InstanceID
            ]
        ));
        $Result = is_string($Ret) && ($Ret !== '') ? \FritzBox\IOResult::Decode($Ret) : false;
        if (!is_array($Result)) {
            $this->SID = '';
            if (!$isNewSubscription) {
                // Verlängerung abgelehnt (z.B. FritzBox neu gestartet): einmal neu anmelden
                return $this->Subscribe();
            }
            $this->EventsNotAvailable();
            return false;
        }
        // SID sofort merken, damit sie auch bei fehlendem Ereignis wieder abgemeldet werden kann
        $this->SID = $Result['SID'];
        $this->SubscribedURL = self::EventSubURLs[$Index];
        if ($isNewSubscription && !$this->WaitForEvent()) {
            $this->Unsubscribe();
            $this->EventsNotAvailable();
            return false;
        }
        $this->SetTimerInterval(\FritzBox\WANIPConnection\Timer::RenewSubscription, max(60, (int) $Result['TIMEOUT'] - 300) * 1000);
        if ($this->EventsUnavailable) {
            $this->EventsUnavailable = false;
            $this->UpdateFormField('EventsUnavailable', 'visible', false);
        }
        return true;
    }

    private function Unsubscribe(): void
    {
        $this->SetTimerInterval(\FritzBox\WANIPConnection\Timer::RenewSubscription, 0);
        $SID = $this->SID;
        $SubscribedURL = $this->SubscribedURL;
        $this->SID = '';
        $this->SubscribedURL = '';
        if (($SID === '') || ($SubscribedURL === '') || !$this->HasActiveParent()) {
            return;
        }
        $this->SendDebug('Unsubscribe', $SID, 0);
        @$this->SendDataToParent(json_encode(
            [
                'DataID'      => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'    => \FritzBox\IOFunction::Unsubscribe,
                'EventSubURL' => $SubscribedURL,
                'SID'         => $SID
            ]
        ));
    }

    /**
     * Wartet bis zu 5 s auf das erste Ereignis nach einem neuen Abo (die FritzBox sendet es sofort).
     */
    private function WaitForEvent(): bool
    {
        for ($i = 0; $i < 1000; $i++) {
            if ($this->EventReceived) {
                return true;
            }
            usleep(5000);
        }
        return false;
    }

    private function EventsNotAvailable(): void
    {
        $this->SetTimerInterval(\FritzBox\WANIPConnection\Timer::RenewSubscription, 0);
        $this->SendDebug('Subscribe', 'Real-time events not available', 0);
        if (!$this->EventsUnavailable) {
            $this->LogMessage($this->Translate('Real-time events not available'), KL_NOTIFY);
        }
        $this->EventsUnavailable = true;
        $this->UpdateFormField('EventsUnavailable', 'visible', true);
    }

    private function UpdateInfo()
    {
        $result = $this->GetStatusInfo();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::ConnectionStatus, 'IP connection status', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', false, 1);
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::UptimeRAW, 'Connection duration in seconds', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::Uptime, 'Connection duration', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);

        $result = $this->GetExternalIPAddress();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::ExternalIPAddress, 'External IPv4 Address', $result, VARIABLETYPE_STRING, '', false, 4);

        $result = $this->GetDNSServer();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::IPv4DNSServer1, 'IPv4 DNS-Server 1', $result['NewIPv4DNSServer1'], VARIABLETYPE_STRING, '', false, 5);
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::IPv4DNSServer2, 'IPv4 DNS-Server 2', $result['NewIPv4DNSServer2'], VARIABLETYPE_STRING, '', false, 6);

        $result = $this->GetExternalIPv6Address();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::ExternalIPv6Address, 'External IPv6 Address', $result['NewExternalIPv6Address'] . '/' . $result['NewPrefixLength'], VARIABLETYPE_STRING, '', false, 10);

        $result = $this->GetIPv6Prefix();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::IPv6Prefix, 'IPv6 Prefix', $result['NewIPv6Prefix'] . '/' . $result['NewPrefixLength'], VARIABLETYPE_STRING, '', false, 16);

        $result = $this->GetIPv6DNSServer();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::IPv6DNSServer1, 'IPv6 DNS-Server 1', $result['NewIPv6DNSServer1'], VARIABLETYPE_STRING, '', false, 14);
        $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::IPv6DNSServer2, 'IPv6 DNS-Server 2', $result['NewIPv6DNSServer2'], VARIABLETYPE_STRING, '', false, 15);
        return true;
    }
}
