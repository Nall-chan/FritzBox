<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

    class FritzBoxWLAN extends FritzBoxModulBase
    {
        protected static $ControlUrlArray = [
            '/upnp/control/wlanconfig1',
            '/upnp/control/wlanconfig2',
            '/upnp/control/wlanconfig3'
        ];
        protected static $EventSubURLArray = [
            '/upnp/control/wlanconfig1',
            '/upnp/control/wlanconfig2',
            '/upnp/control/wlanconfig3'
        ];
        protected static $ServiceTypeArray = [
            'urn:dslforum-org:service:WLANConfiguration:1',
            'urn:dslforum-org:service:WLANConfiguration:2',
            'urn:dslforum-org:service:WLANConfiguration:3'
        ];

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyInteger('Index', -1);
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            //Never delete this line!
            parent::ApplyChanges();
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index > -1) {
                // RequestInfo
            }
        }
        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            if (count(static::$ServiceTypeArray) < 2) {
                return json_encode($Form);
            }
            $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
            if (($Splitter == 0) || !$this->HasActiveParent()) {
                //Parent inactive ausgeben.
                //$Form[];
            }
            $Ret = $this->SendDataToParent(json_encode(
                [
                    'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'Function'   => 'COUNTWLAN'
                ]
            ));
            $NoOfWlan = unserialize($Ret);
            $this->SendDebug('No of WLANs', $NoOfWlan, 0);
            $MaxWLANs = count($Form['elements'][0]['options']) + 1;
            if ($MaxWLANs > $NoOfWlan) {
                array_splice($Form['elements'][0]['options'], $NoOfWlan + 1);
            }
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        public function GetInfo()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetEnable(bool $Enable)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewEnable'=> $Enable
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function SetConfig(
            string $MaxBitRate,
            int $Channel,
             string $SSID,
             string $BeaconType,
             bool $MACAddressControlEnabled,
             string $BasicEncryptionModes,
             string $BasicAuthenticationMode)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewMaxBitRate'              => $MaxBitRate,
                'NewChannel'                 => $Channel,
                'NewSSID'                    => $SSID,
                'NewBeaconType'              => $BeaconType,
                'NewMACAddressControlEnabled'=> $MACAddressControlEnabled,
                'NewBasicEncryptionModes'    => $BasicEncryptionModes,
                'NewBasicAuthenticationMode' => $BasicAuthenticationMode
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function SetSecurityKeys(
            string $WEPKey0,
            string $WEPKey1,
             string $WEPKey2,
             string $WEPKey3,
             bool $PreSharedKey,
             string $KeyPassphrase)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewWEPKey0'                    => $WEPKey0,
                'NewWEPKey1'                    => $WEPKey1,
                'NewWEPKey2'                    => $WEPKey2,
                'NewWEPKey3'                    => $WEPKey3,
                'NewPreSharedKey'               => $PreSharedKey,
                'NewKeyPassphrase'              => $KeyPassphrase
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetSecurityKeys()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetBasBeaconSecurityProperties(
             string $BasicEncryptionModes,
             string $BasicAuthenticationMode)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewBasicEncryptionModes'    => $BasicEncryptionModes,
                'NewBasicAuthenticationMode' => $BasicAuthenticationMode
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetBasBeaconSecurityProperties()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function GetStatistics()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function GetPacketStatistics()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function GetBSSID()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function GetSSID()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetSSID(string $SSID)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewSSID'=> $SSID
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetBeaconType()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetBeaconType(string $BeaconType)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewBeaconType'=> $BeaconType
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetChannelInfo()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetChannel(int $Channel)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewChannel'=> $Channel
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetBeaconAdvertisement()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            /*$this->setIPSVariable('ConnectionStatus', 'Verbindungsstatus', ($result['NewConnectionStatus'] == 'Connected'), VARIABLETYPE_BOOLEAN, 'FB.ConnectionStatus', true, 1);
            $this->setIPSVariable('UptimeRAW', 'Verbindungsdauer', (int) $result['NewUptime'], VARIABLETYPE_INTEGER, '', false, 2);
            $this->setIPSVariable('Uptime', 'Verbindungsdauer', $this->ConvertRunTime((int) $result['NewUptime']), VARIABLETYPE_STRING, '', false, 3);
             */
            return true;
        }
        public function SetBeaconAdvertisement(bool $BeaconAdvertisementEnabled)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewBeaconAdvertisementEnabled'=> $BeaconAdvertisementEnabled
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetTotalAssociations()
        {
            $result = $this->Send(__FUNCTION__, );
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetGenericAssociatedDeviceInfo(int $Index)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewAssociatedDeviceIndex'=> $Index
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetSpecificAssociatedDeviceInfo(string $Mac)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewAssociatedDeviceMACAddress'=> $Mac
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetSpecificAssociatedDeviceInfoByIp(string $Ip)
        {
            $result = $this->Send('X_AVM-DE_GetSpecificAssociatedDeviceInfoByIp', [
                'NewAssociatedDeviceIPAddress'=> $Ip
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetNightControl()
        {
            $result = $this->Send('X_AVM-DE_GetNightControl');
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetWLANHybridMode()
        {
            $result = $this->Send('X_AVM-DE_GetWLANHybridMode');
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function SetWLANHybridMode(
            bool $Enable,
            string $BeaconType,
            string $KeyPassphrase,
            string $SSID,
            string $BSSID,
            string $TrafficMode,
            bool $ManualSpeed,
            int $MaxSpeedDS,
            int $MaxSpeedUS)
        {
            $result = $this->Send('X_AVM-DE_SetWLANHybridMode', [
                'NewEnable'                  => (int) $Enable,
                'NewBeaconType'              => $BeaconType,
                'NewKeyPassphrase'           => $KeyPassphrase,
                'NewSSID'                    => $SSID,
                'NewBSSID'                   => $BSSID,
                'NewTrafficMode'             => $TrafficMode,
                'NewManualSpeed'             => $ManualSpeed,
                'NewMaxSpeedDS'              => $MaxSpeedDS,
                'NewMaxSpeedUS'              => $MaxSpeedUS
            ]);
            if ($result === false) {
                return false;
            }
            return true;
        }
        public function GetWLANExtInfo()
        {
            $result = $this->Send('X_AVM-DE_GetWLANExtInfo');
            if ($result === false) {
                return false;
            }
            return true;
        }
    }