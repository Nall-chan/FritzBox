<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property int $Downstream
 * @property int $Upstream
 * @todo Timer für allgemeine Infos und timer für Datenrate
 */
    class FritzBoxWANCommonInterface extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            //'/upnp/control/wancommonifconfig1',
            '/igdupnp/control/WANCommonIFC1',
            '/igd2upnp/control/WANCommonIFC1'
        ];
        protected static $EventSubURLArray = [
            //'/upnp/control/wancommonifconfig1',
            '/igdupnp/control/WANCommonIFC1',
            '/igd2upnp/control/WANCommonIFC1'
        ];
        protected static $ServiceTypeArray = [
            //'urn:dslforum-org:service:WANCommonInterfaceConfig:1',
            'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1',
            'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1'
        ];
        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', -1);
            $this->Downstream = 0;
            $this->Upstream = 0;
        }
        public function ApplyChanges()
        {
            //Never delete this line!
            $this->RegisterProfileIntegerEx(
                'FB.LinkState',
                '',
                '',
                '',
                [
                [0, $this->Translate('Up'), '', 0x00ff00],
                [1, $this->Translate('Down'), '', 0xff0000],
                [2, $this->Translate('Initializing'), '', 0xff00ff],
                [3, $this->Translate('Unavailable'), '', 0xff0000],
            ]
            );
   
            $this->RegisterProfileInteger('FB.kBit', '', '', ' kBit/s', 0, 0, 0);
            $this->RegisterProfileFloat('FB.Speed', '', '', '%', 0, 100, 0, 2);
            $this->RegisterProfileFloat('FB.MByte', '', '', ' MB', 0, 0, 0, 2);
            $this->RegisterProfileFloat('FB.kbs', '', '', ' kb/s', 0, 0, 0, 2);
            parent::ApplyChanges();
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index > -1) {
                $this->GetCommonLinkProperties();
                if ($Index == 0) {
                    $this->GetOnlineMonitor();
                } else {
                    $this->GetAddonInfos();
                }
            }
        }
        // todo
        public function GetCommonLinkProperties(): bool
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }

            $this->setIPSVariable('WANAccessType', 'Verbindungstyp', (string) $result['NewWANAccessType'], VARIABLETYPE_STRING, '', false, 2);
            $this->setIPSVariable('PhysicalLinkStatus', 'Status', $this->LinkStateToInt((string) $result['NewPhysicalLinkStatus']), VARIABLETYPE_INTEGER, 'FB.LinkState', false, 1);
            $Downstream = (int) ((int) $result['NewLayer1DownstreamMaxBitRate'] / 1000);
            $Upstream = (int) ((int) $result['NewLayer1UpstreamMaxBitRate'] / 1000);
            $this->Downstream = $Downstream;
            $this->Upstream = $Upstream;
            $this->setIPSVariable('UpstreamMaxBitRate', 'Upstream Max kBitrate', $Upstream, VARIABLETYPE_INTEGER, 'FB.kBit', false, 9);
            $this->setIPSVariable('DownstreamMaxBitRate', 'Downstream Max kBitrate', $Downstream, VARIABLETYPE_INTEGER, 'FB.kBit', false, 4);

            return true;
        }
        public function GetTotalBytesSent()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalBytesReceived()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalPacketsSent()
        {
            return $this->Send(__FUNCTION__);
        }
        public function GetTotalPacketsReceived()
        {
            return $this->Send(__FUNCTION__);
        }

        public function GetAddonInfos()
        {
            // nur bei igd
            if (($this->ReadPropertyInteger('Index') != 1) && ($this->ReadPropertyInteger('Index') != 2)) {
                trigger_error('Service does not support this action', E_USER_NOTICE);
                return false;
            }
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('KByteSendRate', 'Senderate', $result['NewByteSendRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs', false, 10);
            $this->setIPSVariable('KByteReceiveRate', 'Empfangsrate', $result['NewByteReceiveRate'] / 1024, VARIABLETYPE_FLOAT, 'FB.kbs', false, 5);
            $Downstream = $this->Downstream;
            if ($Downstream > 0) {
                $this->setIPSVariable('LevelReceiveRate', 'Last Downstream', (100 / ($Downstream / 8) * ($result['NewByteReceiveRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed', false, 6);
            }
            $Upstream = $this->Upstream;
            if ($Upstream > 0) {
                $this->setIPSVariable('LevelSendRate', 'Last Upstream', (100 / ($Upstream / 8) * ($result['NewByteSendRate'] / 1024)), VARIABLETYPE_FLOAT, 'FB.Speed', false, 11);
            }
            if (array_key_exists('NewX_AVM_DE_TotalBytesReceived64', $result)) {
                $send = $result['NewX_AVM_DE_TotalBytesSent64'];
                $recv = $result['NewX_AVM_DE_TotalBytesReceived64'];
            } else {
                $send = $result['NewTotalBytesSent'];
                $recv = $result['NewTotalBytesReceived'];
            }
            $this->setIPSVariable('TotalMBytesSent', 'Gesendet seit Reconnect', $send / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte', false, 12);
            $this->setIPSVariable('TotalMBytesReceived', 'Empfangen seit Reconnect', $recv / 1024 / 1024, VARIABLETYPE_FLOAT, 'FB.MByte', false, 7);

            if (array_key_exists('NewX_AVM_DE_WANAccessType', $result)) {
                $this->setIPSVariable('WANAccessType', 'Verbindungstyp', (string) $result['NewX_AVM_DE_WANAccessType'], VARIABLETYPE_STRING, '', false, 2);
            }
            if (array_key_exists('NewVoipDNSServer1', $result)) {
                $this->setIPSVariable('VoipDNSServer1', 'Voip DNS-Server 1', (string) $result['NewVoipDNSServer1'], VARIABLETYPE_STRING, '', false, 21);
            }
            if (array_key_exists('NewVoipDNSServer2', $result)) {
                $this->setIPSVariable('VoipDNSServer2', 'Voip DNS-Server 2', (string) $result['NewVoipDNSServer2'], VARIABLETYPE_STRING, '', false, 21);
            }
            /*  ["NewAutoDisconnectTime"]=>
              string(1) "0"
              ["NewIdleDisconnectTime"]=>
              string(1) "0"
              ["NewDNSServer1"]=>
              string(0) ""
              ["NewDNSServer2"]=>
              string(0) ""
              ["NewUpnpControlEnabled"]=>
              string(1) "1"
              ["NewRoutedBridgedModeBoth"]=>
              string(1) "1"
             */
        }
        public function GetDsliteStatus()
        {
            // nur bei igd
            if (($this->ReadPropertyInteger('Index') != 1) && ($this->ReadPropertyInteger('Index') != 2)) {
                trigger_error('Service does not support this action', E_USER_NOTICE);
                return false;
            }
            return $this->Send('X_AVM_DE_GetDsliteStatus');
        }
        public function GetIPTVInfos()
        {
            // nur bei igd
            if (($this->ReadPropertyInteger('Index') != 1) && ($this->ReadPropertyInteger('Index') != 2)) {
                trigger_error('Service does not support this action', E_USER_NOTICE);
                return false;
            }
            return $this->Send('X_AVM_DE_GetIPTVInfos');
        }

        public function GetOnlineMonitor()
        {
            // nur bei tr64
            if ($this->ReadPropertyInteger('Index') != 0) {
                trigger_error('Service does not support this action', E_USER_NOTICE);
                return false;
            }
            return $this->Send('X_AVM-DE_GetOnlineMonitor');
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
            if (array_key_exists('PhysicalLinkStatus', $Event)) {
                $this->setIPSVariable('PhysicalLinkStatus', 'Status', $this->LinkStateToInt((string) $Event['PhysicalLinkStatus']), VARIABLETYPE_INTEGER, 'FB.LinkState', false, 1);
                unset($Event['PhysicalLinkStatus']);
                //Todo
                // GetCommonLinkProperties über runScriptText und RequestAction starten
            }

            parent::DecodeEvent($Event);
        }
        private function LinkStateToInt(string $Linkstate): int
        {
            switch ($Linkstate) {

                case 'Up':
                    return 0;
                break;
                        case 'Down':
                            return 1;
                break;
                        case 'Initializing':
                            return 2;
                break;
            }
            return 3;
        }
    }
