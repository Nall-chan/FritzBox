<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWANIPInterface extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            // '/upnp/control/wanipconnection1',
            '/igdupnp/control/WANIPConn1',
            '/igd2upnp/control/WANIPConn1'
        ];
        protected static $EventSubURLArray = [
            // '/upnp/control/wanipconnection1',
            '/igdupnp/control/WANIPConn1',
            '/igd2upnp/control/WANIPConn1'
        ];
        protected static $ServiceTypeArray = [
            // 'urn:dslforum-org:service:WANIPConnection:1',
            'urn:schemas-upnp-org:service:WANIPConnection:1',
            'urn:schemas-upnp-org:service:WANIPConnection:2'
        ];
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', -1);
        }
        public function ApplyChanges()
        {
            //Never delete this line!

            $this->RegisterProfileBooleanEx('FB.ConnectionStatus', '', '', '', [
                [false, $this->Translate('Disconnected'), '', 0xff0000],
                [true, $this->Translate('Connected'), '', 0x00ff00]
            ]
            );
            parent::ApplyChanges();
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index > -1) {
                $this->GetStatusInfo();
                $this->GetExternalIPAddress();
                $this->GetDNSServer();
                $this->GetExternalIPv6Address();
                $this->GetIPv6Prefix();
                $this->GetIPv6DNSServer();
            }
        }

        public function ForceTermination()
        {
            return $this->Send(__FUNCTION__);
        }
        public function RequestTermination()
        {
            return $this->Send(__FUNCTION__);
        }
        public function RequestConnection()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetStatusInfo()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
            return true;
        }
        public function GetExternalIPAddress()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('ExternalIPAddress', 'Externe IPv4 Adresse', $result, VARIABLETYPE_STRING, '', false, 4);
            return true;
        }
        public function GetDNSServer()
        {
            $result = $this->Send('X_AVM_DE_GetDNSServer');
            if ($result === false) {
                return false;
            }

            $this->setIPSVariable('IPv4DNSServer1', 'IPv4 DNS-Server 1', $result['NewIPv4DNSServer1'], VARIABLETYPE_STRING, '', false, 5);
            $this->setIPSVariable('IPv4DNSServer2', 'IPv4 DNS-Server 2', $result['NewIPv4DNSServer2'], VARIABLETYPE_STRING, '', false, 6);
            return true;
        }
        public function GetExternalIPv6Address()
        {
            $result = $this->Send('X_AVM_DE_GetExternalIPv6Address');
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv6Address', 'Externe IPv6 Adresse', $result['NewExternalIPv6Address'], VARIABLETYPE_STRING, '', false, 10);
            return true;
        }

        public function GetIPv6DNSServer()
        {
            $result = $this->Send('X_AVM_DE_GetIPv6DNSServer');
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv6DNSServer1', 'IPv6 DNS-Server 1', $result['NewIPv6DNSServer1'], VARIABLETYPE_STRING, '', false, 14);
            $this->setIPSVariable('IPv6DNSServer2', 'IPv6 DNS-Server 2', $result['NewIPv6DNSServer2'], VARIABLETYPE_STRING, '', false, 15);
            return true;
        }
        public function GetIPv6Prefix()
        {
            $result = $this->Send('X_AVM_DE_GetIPv6Prefix');
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv6Prefix', 'IPv6 Prefix', $result['NewIPv6Prefix'], VARIABLETYPE_STRING, '', false, 16);
            return true;
        }
        /*
        GetNATRSIPStatus
        GetWarnDisconnectDelay
        GetIdleDisconnectTime
        GetAutoDisconnectTime
        GetConnectionTypeInfo

        SetWarnDisconnectDelay
            NewWarnDisconnectDelay
        SetIdleDisconnectTime
            NewIdleDisconnectTime
        SetAutoDisconnectTime
            NewAutoDisconnectTime
         */
        public function SetIdleDisconnectTime()
        {
            return $this->Send(__FUNCTION__);
        }
        /*public function GetPortMappingNumberOfEntries()
        {
            return $this->Send('GetGenericPortMappingEntry', ['NewPortMappingIndex'=>0]);
        }*/

        public function RequestAction($Ident, $Value)
        {
            switch ($Ident) {
                case 'ConnectionStatus':
                    if ($Value) {
                        $this->RequestConnection();
                    } else {
                        $this->RequestTermination();
                    }
                break;
            }
        }

        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter == 0) || !$this->HasActiveParent()) {
                //Parent inactive ausgeben.
                //$Form[];
            }
            $Ret = $this->SendDataToParent(json_encode(
                [
                    'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'Function'   => 'HasIGD2'
                ]
            ));
            $HasIGD2 = unserialize($Ret);
            $this->SendDebug('Use IGD2', $HasIGD2, 0);
            if (!$HasIGD2) {
                unset($Form['elements'][0]['options'][2]);
            }
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        protected function DecodeEvent($Event)
        {
            if (array_key_exists('ConnectionStatus', $Event)) {
                $this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($Event['ConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus');
                unset($Event['ConnectionStatus']);
                //Todo
                 // Über RunscriptText und RequestActiob starten:
                 /*
                $this->GetStatusInfo();
                $this->GetExternalIPAddress();
                $this->GetDNSServer();
                $this->GetExternalIPv6Address();
                $this->GetIPv6Prefix();
                $this->GetIPv6DNSServer();
                  */
            }

            parent::DecodeEvent($Event);
        }
    }