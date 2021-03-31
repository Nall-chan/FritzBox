<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * @property int $APEnabledId
 * @property int $HostNumberOfEntriesId
 */

class FritzBoxWLAN extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;

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
        $this->RegisterPropertyBoolean('HostAsVariable', false);
        $this->RegisterPropertyBoolean('InfoVariables', false);
        $this->RegisterPropertyBoolean('RenameHostVariables', true);
        $this->RegisterPropertyBoolean('ShowWLanKeyAsVariable', false);
        $this->RegisterPropertyBoolean('ShowWLanKeyAsQRCode', false);
        $this->RegisterPropertyInteger('QRCodeSize', 20);
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterPropertyBoolean('HostAsTable', false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
    
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.MBits');
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            if ($QRCodeID > 0) {
                @IPS_DeleteMedia($QRCodeID, true);
            }
        }
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshState', 0);
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        if ($Table) {
            $this->RegisterVariableString('HostTable', $this->Translate('Host Table'), '~HTMLBox', -1);
        } else {
            $this->UnregisterVariable('HostTable');
        }
        $this->RegisterProfileInteger('FB.MBits', '', '', ' MBit/s', 0, 0, 0);
        $this->APEnabledId = $this->RegisterVariableBoolean('X_AVM_DE_APEnabled', $this->Translate('WLAN state'), '~Switch', -11);
        $this->EnableAction('X_AVM_DE_APEnabled');
        $this->UnregisterMessage($this->APEnabledId, VM_UPDATE);
        usleep(5);
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        $this->SetStatus(IS_ACTIVE);
        if (IPS_GetKernelRunlevel() == KR_READY) {
            @$this->UpdateInfo();
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
        if (($Splitter != 0) && $this->HasActiveParent()) {
            $this->GetWLANForm($Form['elements'][0]['options']);
        }
        $Index = $this->ReadPropertyInteger('Index');
        $Summary = $Form['elements'][0]['options'][$Index+1]['caption'];
        if ($Index != -1) {
            $Summary = 'WLAN '.$Summary;
        }
        $this->SetSummary($Summary);
        if (!$this->GetFile('Hosts.xml')) {
            $Form['actions'][2]['visible']=true;
            $Form['actions'][2]['popup']['items'][0]['caption']='Hostnames not available!';
            $Form['actions'][2]['popup']['items'][1]['caption']='The \'FritzBox Host\' instance is required to display hostnames.';
            $Form['actions'][2]['popup']['items'][1]['width']='200px';
        }
        if ($this->ReadPropertyBoolean('ShowWLanKeyAsQRCode')) {
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            if ($QRCodeID > 0) {
                $Image =
                    [
                        'type'=> 'Image',
                        'mediaID'=>$QRCodeID
                    ];
                $Form['actions'][1]['items'][] = $Image;
            }
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
    private function GetWLANForm(array &$Options)
    {
        $result = $this->SendDataToParent(json_encode(
            [
                    'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                    'Function'   => 'COUNTWLAN'
                ]
        ));
        if ($result === false) {
            return;
        }
        $NoOfWlan = unserialize($result);
        for ($i=1; $i <= $NoOfWlan; $i++) {
            $Index = $Options[$i]['value'];
            $result = $this->SendDataToParent(json_encode(
                [
                        'DataID'    => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                        'ServiceTyp'=> static::$ServiceTypeArray[$Index],
                        'ControlUrl'=> static::$ControlUrlArray[$Index],
                        'Function'  => 'X_AVM-DE_GetWLANExtInfo',
                        'Parameter' => []
                    ]
            ));
            $guest = unserialize($result);
            if ($guest === false) {
                continue;
            }
            if ($guest['NewX_AVM-DE_APType'] == 'guest') {
                $Options[$i]['caption'] =$i.$this->Translate(' (Guest)');
                continue;
            }
            $result = $this->SendDataToParent(json_encode(
                [
                        'DataID'    => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                        'ServiceTyp'=> static::$ServiceTypeArray[$Index],
                        'ControlUrl'=> static::$ControlUrlArray[$Index],
                        'Function'  => 'GetInfo',
                        'Parameter' => []
                    ]
            ));
            $info = unserialize($result);
            if ($info === false) {
                continue;
            }
            if ($info['NewStandard'] == 'ac') {
                $Options[$i]['caption'] =$i.$this->Translate(' (5 Ghz)');
            } else {
                $Options[$i]['caption'] =$i.$this->Translate(' (2,4 Ghz)');
            }
        }
    }
    //private
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
            $this->SendDebug('XML not found', 'Hosts.xml', 0);
        } else {
            $xml = new simpleXMLElement($XMLData);
            if ($xml === false) {
                $this->SendDebug('XML decode error', $XMLData, 0);
            }
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
            $Hostname =strtoupper((string)$result['NewAssociatedDeviceMACAddress']).' ('.(string)$result['NewAssociatedDeviceIPAddress'].')';
            $Ident = 'MAC'.strtoupper($this->ConvertIdent((string)$result['NewAssociatedDeviceMACAddress']));
            if (isset($xml)) {
                $Xpath = $xml->xpath('/List/Item[MACAddress="'.(string)$result['NewAssociatedDeviceMACAddress'].'"]/HostName');
                if (sizeof($Xpath)>0) {
                    $Hostname = (string)$Xpath[0];
                }
            }
            if ($Variable) {
                $this->setIPSVariable($Ident, $Hostname, (int)$result["NewX_AVM-DE_Speed"] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false, $pos);
                $VarId = $this->GetIDForIdent($Ident);
                $ChildsNew[]=$VarId;
                if ($Rename && (IPS_GetName($VarId) != $Hostname)) {
                    IPS_SetName($VarId, $Hostname);
                }
                
                $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', 'Speed', VARIABLETYPE_INTEGER, 'FB.MBits');
                SetValueInteger($SpeedId, (int)$result["NewX_AVM-DE_Speed"]);
                $SignalId = $this->RegisterSubVariable($VarId, 'Signal', 'Signalstrength', VARIABLETYPE_INTEGER, '~Intensity.100');
                SetValueInteger($SignalId, (int)$result["NewX_AVM-DE_SignalStrength"]);
            }
            if ($Table) {
                $TableData[$Hostname]=[
                    'Hostname' => $Hostname,
                    'IPAddress'=> (string)$result['NewAssociatedDeviceIPAddress'],
                    'MACAddress'=> (string)$result['NewAssociatedDeviceMACAddress'],
                    'Speed'=>(string)$result["NewX_AVM-DE_Speed"].' MBit/s',
                    'Signalstrength'=> (string)$result["NewX_AVM-DE_SignalStrength"].' %'
                ];
            }
        }
        if ($Variable) {
            $OfflineVarIds= array_diff($ChildsOld, $ChildsNew);
            foreach ($OfflineVarIds as $VarId) {
                $Ident = IPS_GetObject($VarId)['ObjectIdent'];
                if (strpos($Ident, 'MAC')===0) {
                    $this->SetValue($Ident, false);
                    $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', 'Speed', VARIABLETYPE_INTEGER, 'FB.MBits');
                    SetValueInteger($SpeedId, 0);
                    $SignalId = $this->RegisterSubVariable($VarId, 'Signal', 'Signalstrength', VARIABLETYPE_INTEGER, '~Intensity.100');
                    SetValueInteger($SignalId, 0);
                }
            }
        }
        if ($Table) {
            ksort($TableData);
            $HTML = $this->GetTable($TableData);
            $this->SetValue('HostTable', $HTML);
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
        $resultState = $this->GetInfo();
        if ($resultState === false) {
            return false;
        }
        $this->setIPSVariable('X_AVM_DE_APEnabled', 'WLAN state', (int)$resultState['NewEnable']!==0, VARIABLETYPE_BOOLEAN, '~Switch', false, -11);
        $this->setIPSVariable('SSID', 'SSID Name', (string)$resultState['NewSSID'], VARIABLETYPE_STRING, '', false, -10);
        //todo
        // Typ cachen
        if ($this->ReadPropertyBoolean('InfoVariables')) {
            $result = $this->GetWLANExtInfo();
            if ($result === false) {
                return false;
            }
            if ((string)$result['NewX_AVM-DE_APType'] =='guest') {
                $this->setIPSVariable('TimeoutActive', 'Timeout active', $result['NewX_AVM-DE_TimeoutActive'], VARIABLETYPE_BOOLEAN, '~Switch', false, -7);
                $this->setIPSVariable('TimeRemainRaw', 'Remain time in minutes', (int)$result['NewX_AVM-DE_TimeRemain'], VARIABLETYPE_INTEGER, '', false, -6);
                $this->setIPSVariable('TimeRemain', 'Remain time', $this->ConvertRuntime(((int)$result['NewX_AVM-DE_TimeRemain'])*60), VARIABLETYPE_STRING, '', false, -5);
                $this->setIPSVariable('OffTime', 'Scheduled shutdown', time()+((int)$result['NewX_AVM-DE_TimeRemain']*60), VARIABLETYPE_INTEGER, '~UnixTimestamp', false, -4);
                $this->setIPSVariable('ForcedOff', 'No shutdown when guest is active', $result['NewX_AVM-DE_NoForcedOff'], VARIABLETYPE_BOOLEAN, '~Switch', false, -3);
            }
        }
        $useVariable = $this->ReadPropertyBoolean('ShowWLanKeyAsVariable');
        $useQRCode = $this->ReadPropertyBoolean('ShowWLanKeyAsQRCode');

        if ($useQRCode || $useVariable) {
            $resultKeys = $this->GetSecurityKeys();
            if ($resultKeys === false) {
                return false;
            }
            if ($useVariable) {
                $this->setIPSVariable('KeyPassphrase', 'Password', (string)$resultKeys['NewKeyPassphrase'], VARIABLETYPE_STRING, '', false, -9);
            }
            if ($useQRCode) {
                $QRData = $this->GenerateQRCodeData((string)$resultState['NewSSID'], (string)$resultKeys['NewKeyPassphrase']);
                    
                $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
                if ($QRCodeID === false) {
                    $QRCodeID = IPS_CreateMedia(1);
                    IPS_SetParent($QRCodeID, $this->InstanceID);
                    IPS_SetIdent($QRCodeID, 'QRCodeIMG');
                    IPS_SetName($QRCodeID, 'QR-Code');
                    IPS_SetPosition($QRCodeID, -8);
                    IPS_SetMediaCached($QRCodeID, true);
                    $filename = 'media' . DIRECTORY_SEPARATOR . 'QRCode_' . $this->InstanceID . '.png';
                    IPS_SetMediaFile($QRCodeID, $filename, false);
                    $this->SendDebug('Create Media', $filename, 0);
                }

                IPS_SetMediaContent($QRCodeID, base64_encode($QRData));
            }
        }
        return true;
    }
    private function GenerateQRCodeData(string $SSID, string $KeyPassphrase, int $size=0)
    {
        $CodeText= 'WIFI:S:'.$SSID.';T:WPA;P:'.$KeyPassphrase.';;';
        $Size = $this->ReadPropertyInteger('QRCodeSize');
        include(__DIR__.'/../libs/phpqrcode/qrlib.php');
        ob_start();
        QRcode::png($CodeText, null, QR_ECLEVEL_L, $Size);
        $QRImage = ob_get_contents();
        ob_end_clean();
        return $QRImage;
    }
    public function GetHTMLQRCode()
    {
        $useVariable = $this->ReadPropertyBoolean('ShowWLanKeyAsVariable');
        $useQRCode = $this->ReadPropertyBoolean('ShowWLanKeyAsQRCode');
        $SSID = $this->GetValue('SSID');
        if ($useQRCode && $useVariable) {
            $KeyPassphrase =  @$this->GetValue('KeyPassphrase');
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            $QRData = IPS_GetMediaContent($QRCodeID);
        } else {
            $resultKeys = $this->GetSecurityKeys();
            if ($resultKeys === false) {
                return false;
            }
            $KeyPassphrase=(string)$resultKeys['NewKeyPassphrase'];
            $QRData = base64_encode($this->GenerateQRCodeData($SSID, $KeyPassphrase));
        }

        $HTMLData ='<center><h1 style="color:red">'.$this->Translate('Credentials').'</h1><h2>WLAN: '.$SSID.'</h2><h2>'.$this->Translate('Password').': '.$KeyPassphrase.'</h2></center>';
        $HTMLData.= '<center><img src="data:image/png;base64,'.$QRData.'"></span></center>';
        return $HTMLData;
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
    public function SetWLANConfig(
        string $MaxBitRate,
        int $Channel,
        string $SSID,
        string $BeaconType,
        bool $MACAddressControlEnabled,
        string $BasicEncryptionModes,
        string $BasicAuthenticationMode
    ) {
        $result = $this->Send('SetConfig', [
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
        $this->setIPSVariable('HostNumberOfEntries', 'Number of active hosts', (int)$result, VARIABLETYPE_INTEGER, '', false, -2);
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
        
    private function GenerateHTMLStyleProperty()
    {
        $NewTableConfig = [
            [
                'tag'   => '<table>',
                'style' => 'margin:0 auto; font-size:0.8em;'
            ],
            [
                'tag'   => '<thead>',
                'style' => ''
            ],
            [
                'tag'   => '<tbody>',
                'style' => ''
            ]
        ];
        $NewColumnsConfig = [
            [
                'index' => 0,
                'key'   => 'Hostname',
                'name'  => $this->Translate('Hostname'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],
            [
                'index' => 1,
                'key'   => 'IPAddress',
                'name'  => $this->Translate('IP-Address'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 2,
                'key'   => 'MACAddress',
                'name'  => $this->Translate('MAC-Address'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 3,
                'key'   => 'Speed',
                'name'  => $this->Translate('Speed'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 4,
                'key'   => 'Signalstrength',
                'name'  => $this->Translate('Signalstrength'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ]
        ];
        $NewRowsConfig = [
            [
                'row'     => 'odd',
                'name'    => $this->Translate('odd'),
                'bgcolor' => 0x000000,
                'color'   => 0xffffff,
                'style'   => ''
            ],
            [
                'row'     => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => 0x080808,
                'color'   => 0xffffff,
                'style'   => ''
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
    }
}
