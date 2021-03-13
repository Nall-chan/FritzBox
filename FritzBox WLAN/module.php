<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property int $APEnabledId
 * @property int $HostNumberOfEntriesId
 */

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
        protected static $SecondEventGUID ='{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->APEnabledId=0;
            $this->HostNumberOfEntriesId=0;
            $this->RegisterPropertyInteger('Index', -1);
            $this->RegisterPropertyBoolean('HostAsVariable', true);
            $this->RegisterPropertyBoolean('InfoVariables', true);
            $this->RegisterPropertyBoolean('RenameHostVariables', true);
            $this->RegisterPropertyBoolean('HostAsTable', true);
            $this->RegisterPropertyInteger('RefreshInterval', 60);
            $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
        }

        public function Destroy()
        {
            //Never delete this line!
            parent::Destroy();
        }

        public function ApplyChanges()
        {
            $this->RegisterProfileInteger('FB.MBits', '', '', ' MBit/s', 0, 0, 0);
            $this->APEnabledId = $this->RegisterVariableBoolean('X_AVM_DE_APEnabled', $this->Translate('Wlan active ?'), '~Switch', -10);
            $this->EnableAction('X_AVM_DE_APEnabled');
            $this->UnregisterMessage($this->APEnabledId, VM_UPDATE);
            usleep(5);
            $Index = $this->ReadPropertyInteger('Index');
            if ($Index > -1) {
                if (IPS_GetKernelRunlevel() == KR_READY) {
                    @$this->UpdateInfo();
                }
            }
            usleep(5);
            $this->RegisterMessage($this->APEnabledId, VM_UPDATE);
            $this->HostNumberOfEntriesId = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number of active hosts'), '', -2);
            $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
            parent::ApplyChanges();
            $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval')*1000);
            if (IPS_GetKernelRunlevel() != KR_READY) {
                return;
            }
        }
        public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
        {
            switch ($Message) {
                case VM_UPDATE:
                    if ($SenderID == $this->APEnabledId) {
                        $this->GetTotalAssociations();
                        return;
                    }
                    if ($SenderID == $this->HostNumberOfEntriesId) {
                        $this->RefreshHostList();
                        return;
                    }
                    break;
            }
            parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);
        }
        public function RequestAction($Ident, $Value)
        {
            if (parent::RequestAction($Ident, $Value)) {
                return true;
            }
            switch ($Ident) {
                case 'RefreshState':
                    return $this->UpdateInfo();

                case 'X_AVM_DE_APEnabled':
                    return $this->SetEnable((bool)$Value);
            }
            trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
            return false;
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
        public function RefreshHostList()
        {
            $Table = $this->ReadPropertyBoolean('HostAsTable');
            $Variable = $this->ReadPropertyBoolean('HostAsVariable');
            $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
            if (!($Variable || ($Table))) {
                return true;
            }
            if ($this->ParentID == 0) {
                return false;
            }
            $XMLData = $this->GetFile('Hosts.xml');
            if ($XMLData === false) {
                $this->SendDebug('XML not found', $Url, 0);
                return false;
            }
            $xml = new simpleXMLElement($XMLData);
            if ($xml === false) {
                $this->SendDebug('XML decode error', $XMLData, 0);
            }
            $OnlineCounter=0;
            $TableData=[];
            $pos=0;
            $Hosts = $this->GetValue('HostNumberOfEntries');
            $ChildsOld = IPS_GetChildrenIDs($this->InstanceID);
            $ChildsNew=[];
            for ($i=0;$i< $Hosts; $i++) {
                $result = @$this->GetGenericAssociatedDeviceInfo($i);
                if ($result === false) {
                    continue;
                }
                $Xpath = $xml->xpath('/List/Item[MACAddress="'.(string)$result['NewAssociatedDeviceMACAddress'].'"]/HostName');
                $Ident = 'MAC'.strtoupper($this->ConvertIdent((string)$result['NewAssociatedDeviceMACAddress']));
                if (sizeof($Xpath)==0) {
                    $Hostname =strtoupper((string)$result['NewAssociatedDeviceMACAddress']).' ('.(string)$result['NewAssociatedDeviceIPAddress'].')';
                } else {
                    $Hostname = (string)$Xpath[0];
                }
                if ($Variable) {
                    $this->setIPSVariable($Ident, $Hostname, (int)$result["NewX_AVM-DE_Speed"] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false, $pos);
                    $VarId = $this->GetIDForIdent($Ident);
                    $ChildsNew[]=$VarId;
                    if ($Rename && (IPS_GetName($VarId) != $Hostname)) {
                        IPS_SetName($Ident, $Hostname);
                    }
                
                    $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', $this->Translate('Speed'), VARIABLETYPE_INTEGER, 'FB.MBits');
                    SetValueInteger($SpeedId, (int)$result["NewX_AVM-DE_Speed"]);
                    $SignalId = $this->RegisterSubVariable($VarId, 'Signal', $this->Translate('Signalstrength'), VARIABLETYPE_INTEGER, '~Intensity.100');
                    SetValueInteger($SignalId, (int)$result["NewX_AVM-DE_SignalStrength"]);
                }
            }
            if ($Variable) {
                $OfflineVarIds= array_diff($ChildsOld, $ChildsNew);
                foreach ($OfflineVarIds as $VarId) {
                    $Ident = IPS_GetObject($VarId)['ObjectIdent'];
                    if (strpos($Ident, 'MAC')===0) {
                        $this->SetValue($Ident, false);
                        $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', $this->Translate('Speed'), VARIABLETYPE_INTEGER, 'FB.MBits');
                        SetValueInteger($SpeedId, 0);
                        $SignalId = $this->RegisterSubVariable($VarId, 'Signal', $this->Translate('Signalstrength'), VARIABLETYPE_INTEGER, '~Intensity.100');
                        SetValueInteger($SignalId, 0);
                    }
                }
            }
        }
        public function GetWLANDeviceListPath()
        {
            $result = $this->Send('X_AVM-DE_GetWLANDeviceListPath');
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function ReceiveData($JSONString)
        {
            $Processed = parent::ReceiveData($JSONString);
            if ($Processed !== null) {
                return $Processed;
            }
            $data = json_decode($JSONString, true);
            unset($data['DataID']);
            $this->SendDebug('ReceiveHostData', $data, 0);
            return true;
        }
        private function UpdateInfo()
        {
            $result = $this->GetInfo();
            if ($result === false) {
                return false;
            }
            $this->setIPSVariable('X_AVM_DE_APEnabled', $this->Translate('Wlan active ?'), (int)$result['NewEnable']!==0, VARIABLETYPE_BOOLEAN, '~Switch', false, -10);
            $this->setIPSVariable('SSID', $this->Translate('SSID Name'), (string)$result['NewSSID'], VARIABLETYPE_STRING, '', false, -9);
            if ($this->ReadPropertyBoolean('InfoVariables')) {
                $result = $this->GetWLANExtInfo();
                if ((string)$result['NewX_AVM-DE_APType'] =='guest') {
                    $this->setIPSVariable('TimeoutActive', $this->Translate('Timeout Active'), $result['NewX_AVM-DE_TimeoutActive'], VARIABLETYPE_BOOLEAN, '~Switch', false, -8);
                    $this->setIPSVariable('TimeRemainRaw', $this->Translate('Remain time in minutes'), (int)$result['NewX_AVM-DE_TimeRemain'], VARIABLETYPE_INTEGER, '', false, -7);
                    $this->setIPSVariable('TimeRemain', $this->Translate('Remain time'), $this->ConvertRuntime(((int)$result['NewX_AVM-DE_TimeRemain'])*60), VARIABLETYPE_STRING, '', false, -6);
                    $this->setIPSVariable('OffTime', $this->Translate('Scheduled shutdown'), time()+((int)$result['NewX_AVM-DE_TimeRemain']*60), VARIABLETYPE_INTEGER, '~UnixTimestamp', false, -5);
                    $this->setIPSVariable('ForcedOff', $this->Translate('No shutdown when guest is active'), $result['NewX_AVM-DE_NoForcedOff'], VARIABLETYPE_BOOLEAN, '~Switch', false, -4);
                }
            }
            return true;
        }
        public function GetInfo()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
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
            string $BasicAuthenticationMode
        ) {
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
            string $KeyPassphrase
        ) {
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
            return $result;
        }
        public function SetBasBeaconSecurityProperties(
            string $BasicEncryptionModes,
            string $BasicAuthenticationMode
        ) {
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
            return $result;
        }
        public function GetStatistics()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetPacketStatistics()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetBSSID()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetSSID()
        {
            $result = $this->Send(__FUNCTION__);
            if ($result === false) {
                return false;
            }
            return $result;
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
            return $result;
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
            return $result;
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
            return $result;
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
            $this->setIPSVariable('HostNumberOfEntries', $this->Translate('Number of active hosts'), (int)$result, VARIABLETYPE_INTEGER, '', false, -2);
            return (int)$result;
        }
        public function GetGenericAssociatedDeviceInfo(int $Index)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewAssociatedDeviceIndex'=> $Index
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetSpecificAssociatedDeviceInfo(string $Mac)
        {
            $result = $this->Send(__FUNCTION__, [
                'NewAssociatedDeviceMACAddress'=> $Mac
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetSpecificAssociatedDeviceInfoByIp(string $Ip)
        {
            $result = $this->Send('X_AVM-DE_GetSpecificAssociatedDeviceInfoByIp', [
                'NewAssociatedDeviceIPAddress'=> $Ip
            ]);
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetNightControl()
        {
            $result = $this->Send('X_AVM-DE_GetNightControl');
            if ($result === false) {
                return false;
            }
            return $result;
        }
        public function GetWLANHybridMode()
        {
            $result = $this->Send('X_AVM-DE_GetWLANHybridMode');
            if ($result === false) {
                return false;
            }
            return $result;
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
            int $MaxSpeedUS
        ) {
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
            return $result;
        }
    }
