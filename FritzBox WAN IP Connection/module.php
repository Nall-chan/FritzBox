<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

class FritzBoxWANIPConnection extends FritzBoxModulBase
{
    /**
     * Stand der Konfigurationsmigration (1 = ab Version 0.93: Index 0 = IGD, 1 = IGD2).
     */
    private const CurrentMigrationVersion = 1;

    protected static $ControlUrlArray = [
        '/igdupnp/control/WANIPConn1',
        '/igd2upnp/control/WANIPConn2'
    ];
    protected static $EventSubURLArray = [
        '/igdupnp/control/WANIPConn1',
        '/igd2upnp/control/WANIPConn2',
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
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        //$this->UpdateInfo();
        $this->RegisterVariableInteger(\FritzBox\WANIPConnection\Variables::ConnectionAction, $this->Translate('Control connection'), $this->GetPresentation('FB.Connect', VARIABLETYPE_INTEGER, true), 0);
        $this->EnableAction(\FritzBox\WANIPConnection\Variables::ConnectionAction);

        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
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
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\WANIPConnection\Variables::ConnectionAction:
                return $this->ForceTermination();
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
    }

    protected function DecodeEvent(array $Event): void
    {
        if (array_key_exists('ConnectionStatus', $Event)) {
            $this->setIPSVariable(\FritzBox\WANIPConnection\Variables::ConnectionStatus, 'IP connection status', ($Event['ConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus');
            unset($Event['ConnectionStatus']);
            $this->UpdateInfo();
        }
        parent::DecodeEvent($Event);
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
    }
}
