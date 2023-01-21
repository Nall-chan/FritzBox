<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWANIPConnection extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            '/igdupnp/control/WANIPConn1',
            '/igd2upnp/control/WANIPConn1'
        ];
        protected static $EventSubURLArray = [
            '/igdupnp/control/WANIPConn1',
            '/igd2upnp/control/WANIPConn1'
        ];
        protected static $ServiceTypeArray = [
            'urn:schemas-upnp-org:service:WANIPConnection:1',
            'urn:schemas-upnp-org:service:WANIPConnection:2'
        ];
        public function Create()
        {
            //Never delete this line!
            parent::Create();

            $this->RegisterPropertyInteger('RefreshInterval', 60);
            $this->RegisterTimer('RefreshInfo', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshInfo",true);');
        }
        public function Destroy()
        {
            if (!IPS_InstanceExists($this->InstanceID)) {
                $this->UnregisterProfile('FB.Connect');
                $this->UnregisterProfile('FB.ConnectionStatus');
            }
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->SetTimerInterval('RefreshInfo', 0);

            $this->RegisterProfileIntegerEx(
                'FB.Connect',
                '',
                '',
                '',
                [
                    [0, $this->Translate('Reconnect'), '', 0xff0000]
                ]
            );
            $this->RegisterProfileBooleanEx(
                'FB.ConnectionStatus',
                '',
                '',
                '',
                [
                    [false, $this->Translate('Disconnected'), '', 0xff0000],
                    [true, $this->Translate('Connected'), '', 0x00ff00]
                ]
            );
            parent::ApplyChanges();
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index == -1) {
                $this->SetStatus(IS_INACTIVE);
                return;
            }
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            //$this->UpdateInfo();
            $this->RegisterVariableInteger('ConnectionAction', $this->Translate('Control connection'), 'FB.Connect', 0);
            $this->EnableAction('ConnectionAction');

            $this->SetTimerInterval('RefreshInfo', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        }

        public function GetConnectionTypeInfo()
        {
            return $this->Send(__FUNCTION__);
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
            return $result;
        }
        public function GetExternalIPAddress()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetDNSServer()
        {
            $result = $this->Send('X_AVM_DE_GetDNSServer');
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetExternalIPv6Address()
        {
            $result = $this->Send('X_AVM_DE_GetExternalIPv6Address');
            if ($result === false) {
                return false;
            }
            return $result;
        }

        public function GetIPv6DNSServer()
        {
            $result = $this->Send('X_AVM_DE_GetIPv6DNSServer');
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetIPv6Prefix()
        {
            $result = $this->Send('X_AVM_DE_GetIPv6Prefix');
            if ($result === false) {
                return false;
            }
            return $result;
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
        /*        public function GetPortMappingNumberOfEntries()
                {
                    return $this->Send(__FUNCTION__);
                }
                public function GetGenericPortMappingEntry(int $Index)
                {
                    return $this->Send('GetGenericPortMappingEntry', ['NewPortMappingIndex'=>$Index]);
                }
         */
        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            switch ($Ident) {
                case 'RefreshInfo':
                    return $this->UpdateInfo();
                case 'ConnectionAction':
/*                    if ($Value) {
                        return $this->RequestConnection();
                    } else {*/
                        return $this->ForceTermination();
                    //}
                break;
            }
            trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        }

        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter != 0) && $this->HasActiveParent()) {
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
            }
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        protected function DecodeEvent($Event)
        {
            if (array_key_exists('ConnectionStatus', $Event)) {
                $this->setIPSVariable('ConnectionStatus', 'IP connection status', ($Event['ConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus');
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
            $this->setIPSVariable('ConnectionStatus', 'IP connection status', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', false, 1);
            $this->setIPSVariable('UptimeRAW', 'Connection duration in seconds', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Connection duration', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);

            $result = $this->GetExternalIPAddress();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('ExternalIPAddress', 'External IPv4 Address', $result, VARIABLETYPE_STRING, '', false, 4);

            $result = $this->GetDNSServer();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv4DNSServer1', 'IPv4 DNS-Server 1', $result['NewIPv4DNSServer1'], VARIABLETYPE_STRING, '', false, 5);
            $this->setIPSVariable('IPv4DNSServer2', 'IPv4 DNS-Server 2', $result['NewIPv4DNSServer2'], VARIABLETYPE_STRING, '', false, 6);

            $result = $this->GetExternalIPv6Address();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('ExternalIPv6Address', 'External IPv6 Address', $result['NewExternalIPv6Address'], VARIABLETYPE_STRING, '', false, 10);

            $result = $this->GetIPv6Prefix();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv6Prefix', 'IPv6 Prefix', $result['NewIPv6Prefix'], VARIABLETYPE_STRING, '', false, 16);

            $result = $this->GetIPv6DNSServer();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('IPv6DNSServer1', 'IPv6 DNS-Server 1', $result['NewIPv6DNSServer1'], VARIABLETYPE_STRING, '', false, 14);
            $this->setIPSVariable('IPv6DNSServer2', 'IPv6 DNS-Server 2', $result['NewIPv6DNSServer2'], VARIABLETYPE_STRING, '', false, 15);
        }
    }
