<?php

declare(strict_types=1);

use JetBrains\PhpStorm\Internal\ReturnTypeContract;

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * @property int $APEnabledId
 * @property int $HostNumberOfEntriesId
 * @property bool $APisGuest
 * @property int $Channel
 * @property bool $ShowVariableWarning
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
    protected static $SecondEventGUID = \FritzBox\GUID::NewHostListEvent;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->APEnabledId = 0;
        $this->HostNumberOfEntriesId = 0;
        $this->APisGuest = false;

        $this->RegisterPropertyBoolean('HostAsVariable', false);
        $UsedVariableIdents = array_map(function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if (substr($Ident, 0, 3) == 'MAC') {
                return [
                    'ident'=> $Ident,
                    'use'  => true
                ];
            }
        }, IPS_GetChildrenIDs($this->InstanceID));
        $UsedVariableIdents = array_filter($UsedVariableIdents, function ($Line)
        {
            if ($Line === null) {
                return false;
            }
            return true;
        });
        $this->RegisterPropertyString('HostVariables', json_encode(array_values($UsedVariableIdents)));
        $this->RegisterPropertyBoolean('InfoVariables', false);
        $this->RegisterPropertyBoolean('AutoAddHostVariables', true);
        $this->RegisterPropertyBoolean('RenameHostVariables', false);
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
        if (!$this->ReadPropertyBoolean('ShowWLanKeyAsVariable')) {
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            if ($QRCodeID !== false) {
                IPS_DeleteMedia($QRCodeID, true);
            }
        }
        if (!$this->ReadPropertyBoolean('ShowWLanKeyAsQRCode')) {
            $this->UnregisterVariable('KeyPassphrase');
        }
        if (!$this->ReadPropertyBoolean('InfoVariables')) {
            $this->UnregisterVariable('TimeoutActive');
            $this->UnregisterVariable('TimeRemainRaw');
            $this->UnregisterVariable('TimeRemain');
            $this->UnregisterVariable('OffTime');
            $this->UnregisterVariable('ForcedOff');
        }
        if ($this->ReadPropertyBoolean('HostAsTable')) {
            $this->RegisterVariableString('HostTable', $this->Translate('Host Table'), '~HTMLBox', -1);
        } else {
            $this->UnregisterVariable('HostTable');
        }
        $this->RegisterProfileInteger('FB.MBits', '', '', ' MBit/s', 0, 0, 0);
        $this->APEnabledId = $this->RegisterVariableBoolean('X_AVM_DE_APEnabled', $this->Translate('WLAN state'), '~Switch', -11);
        $this->EnableAction('X_AVM_DE_APEnabled');
        $this->UnregisterMessage($this->APEnabledId, VM_UPDATE);
        usleep(5);
        $HostVariables = json_decode($this->ReadPropertyString('HostVariables'), true);
        foreach ($HostVariables as $HostVariable) {
            if (!$HostVariable['use']) {
                $Ident = $HostVariable['ident'];
                $VarId = @$this->GetIDForIdent($Ident);
                if ($VarId > 0) {
                    $this->DelSubObjects($VarId);
                    $this->UnregisterVariable($Ident);
                }
            }
        }
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() == KR_READY) {
            @$this->UpdateInfo();
            if (!$this->ReadPropertyBoolean('InfoVariables')) {
                $result = $this->GetWLANExtInfo();
                if ($result !== false) {
                    $this->APisGuest = ((string) $result['NewX_AVM-DE_APType'] == 'guest');
                }
            }
        }
        $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        $this->RegisterMessage($this->APEnabledId, VM_UPDATE);
        $this->HostNumberOfEntriesId = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number of active WLAN devices'), '', -2);
        $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
        usleep(5);
        parent::ApplyChanges();
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
            case 'ReloadForm':
                IPS_Sleep(2000);
                $this->ReloadForm();
                return;
            case 'RefreshState':
                return $this->UpdateInfo();
            case 'X_AVM_DE_APEnabled':
                return $this->SetEnable((bool) $Value);
            case 'ShowWLanKeyAsQRCode':
                $this->UpdateFormField('QRCodeSize', 'enabled', (bool) $Value);
                return;
            case 'HostAsTable':
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostAsTablePanel', 'expanded', (bool) $Value);
                return;
            case 'HostAsVariable':
                $this->UpdateFormField('AutoAddHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('RenameHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostvariablesPanel', 'expanded', (bool) $Value);
                $this->UpdateFormField('HostVariables', 'enabled', (bool) $Value);
                return;
            case 'HostvariablesPanel':
                if ($this->ShowVariableWarning) {
                    $this->UpdateFormField('ErrorPopup', 'visible', true);
                    $this->UpdateFormField('ErrorTitle', 'caption', 'Attention!');
                    $this->UpdateFormField('ErrorText', 'caption', 'Deselecting a host causes the associated status variable to be deleted.');
                    $this->ShowVariableWarning = false;
                }
                return;
            case 'DelHostVariable':
                $ObjectId = @$this->GetIDForIdent($Value);
                if ($ObjectId > 0) {
                    $this->DelSubObjects($ObjectId);
                    $this->UnregisterVariable($Value);
                }
                return;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if (count(static::$ServiceTypeArray) < 2) {
            return json_encode($Form);
        }
        $Splitter = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if (($Splitter > 1) && $this->HasActiveParent()) {
            $this->GetWLANForm($Form['elements'][0]['options']);
        }
        $Index = $this->ReadPropertyInteger('Index');
        $Summary = $Form['elements'][0]['options'][$Index + 1]['caption'];
        if ($Index != -1) {
            $Summary = 'WLAN ' . $Summary;
        }
        $this->SetSummary($Summary);
        if (!$this->GetFile('Hosts')) {
            $Form['actions'][2]['visible'] = true;
            $Form['actions'][2]['popup']['items'][0]['caption'] = 'Hostnames not available!';
            $Form['actions'][2]['popup']['items'][1]['caption'] = 'The \'FritzBox Host\' instance is required to display hostnames.';
            $Form['actions'][2]['popup']['items'][1]['width'] = '200px';
            $ConfiguratorID = $this->GetConfiguratorID();
            if ($ConfiguratorID > 0) {
                $Form['actions'][2]['popup']['items'][2]['caption'] = 'Open Configurator';
                $Form['actions'][2]['popup']['items'][2]['visible'] = true;
                $Form['actions'][2]['popup']['items'][2]['objectID'] = $ConfiguratorID;
            }
        }
        if (!$this->ReadPropertyBoolean('HostAsTable')) {
            $Form['elements'][6]['items'][0]['enabled'] = false;
            $Form['elements'][6]['items'][1]['enabled'] = false;
            $Form['elements'][6]['items'][2]['enabled'] = false;
        }

        if ($this->ReadPropertyBoolean('ShowWLanKeyAsQRCode')) {
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            if ($QRCodeID > 0) {
                $Image =
                    [
                        'type'   => 'Image',
                        'mediaID'=> $QRCodeID
                    ];
                $Form['actions'][1]['items'][] = $Image;
            }
        } else {
            $Form['elements'][3]['items'][1]['enabled'] = false;
        }

        if (!$this->ReadPropertyBoolean('HostAsVariable')) {
            $this->ShowVariableWarning = false;
            $Form['elements'][4]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][4]['items'][0]['items'][2]['enabled'] = false;
            $Form['elements'][4]['items'][1]['items'][0]['enabled'] = false;
        } else {
            $this->ShowVariableWarning = true;
            $Form['elements'][4]['items'][1]['onClick'] = 'IPS_RequestAction($id,"HostvariablesPanel", true);';
        }

        $Values = $this->GetHostVariables();
        if (count($Values) == 0) {
            // Fallback für konfigurierte Statusvariablen der Hosts, wenn Abfrage fehlschlägt; z.B. wenn IO offline
            $Values = json_decode($this->ReadPropertyString('HostVariables'), true);
        }
        $Form['elements'][4]['items'][1]['items'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function ReceiveData($JSONString)
    {
        $Processed = parent::ReceiveData($JSONString);
        if ($Processed !== null) {
            return $Processed;
        }
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        if ($data['Function'] == 'NewHostListEvent') {
            $this->SendDebug('NewHostListEvent', '', 0);
            $this->RefreshHostList();
        }
        return true;
    }

    public function RefreshHostList()
    {
        if ($this->ParentID == 0) {
            return false;
        }
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        if (!($Variable || ($Table))) {
            return true;
        }
        // Host holen für Namen
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
        } else {
            $xmlHosts = new \simpleXMLElement($XMLData);
            if ($xmlHosts === false) {
                unset($xmlHosts);
                $this->SendDebug('XML decode error', $XMLData, 0);
            }
        }
        // WLAN Daten holen
        $Uri = $this->GetWLANDeviceListPath();
        if ($Uri === false) {
            $this->SendDebug('Error load WLAN Data', '', 0);
            return false;
        }
        $XMLData = $this->LoadAndGetData($Uri);
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'WLAN', 0);
            return false;
        }
        $xmlWLAN = new \simpleXMLElement($XMLData);
        if ($xmlWLAN === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return false;
        }
        // WLAN Daten filtern auf unseren Channel
        if ($this->APisGuest) {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceGuest = '1']");
        } else {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceChannel ='" . $this->Channel . "']");
        }

        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = array_column(json_decode($this->ReadPropertyString('HostVariables'), true), 'use', 'ident');

        $TableData = [];

        $ChildsOld = IPS_GetChildrenIDs($this->InstanceID);
        $ChildsNew = [];
        // hier WLAN Daten durchgehen
        foreach ($Devices as $xmlItem) {
            $Hostname = strtoupper((string) $xmlItem->AssociatedDeviceMACAddress) . ' (' . (string) $xmlItem->AssociatedDeviceIPAddress . ')';
            $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $xmlItem->AssociatedDeviceMACAddress));
            if (isset($xmlHosts)) {
                $Xpath = $xmlHosts->xpath('//Item[MACAddress="' . strtoupper((string) $xmlItem->AssociatedDeviceMACAddress) . '"]/HostName');
                if (count($Xpath) > 0) {
                    $Hostname = (string) $Xpath[0];
                }
            }
            if ($Table) {
                $TableData[] = [
                    'Hostname'      => $Hostname,
                    'IPAddress'     => (string) $xmlItem->AssociatedDeviceIPAddress,
                    'MACAddress'    => (string) $xmlItem->AssociatedDeviceMACAddress,
                    'Speed'         => (string) $xmlItem->{'X_AVM-DE_Speed'} . ' MBit/s',
                    'Signalstrength'=> (string) $xmlItem->{'X_AVM-DE_SignalStrength'} . ' %'
                ];
            }
            if ($Variable) {
                if (array_key_exists($Ident, $HostVariables)) {
                    $Used = $HostVariables[$Ident];
                } else {
                    $Used = $this->ReadPropertyBoolean('AutoAddHostVariables');
                }
                if ($Used) {
                    $this->setIPSVariable($Ident, $Hostname, true, VARIABLETYPE_BOOLEAN, '~Switch', false);
                    $VarId = $this->GetIDForIdent($Ident);
                    $ChildsNew[] = $VarId;
                    if ($Rename && (IPS_GetName($VarId) != $Hostname)) {
                        IPS_SetName($VarId, $Hostname);
                    }
                    $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', 'Speed', VARIABLETYPE_INTEGER, 'FB.MBits');
                    SetValueInteger($SpeedId, (int) $xmlItem->{'X_AVM-DE_Speed'});
                    $SignalId = $this->RegisterSubVariable($VarId, 'Signal', 'Signalstrength', VARIABLETYPE_INTEGER, '~Intensity.100');
                    SetValueInteger($SignalId, (int) $xmlItem->{'X_AVM-DE_SignalStrength'});
                }
            }
        }
        if ($Variable) {
            $OfflineVarIds = array_diff($ChildsOld, $ChildsNew);
            foreach ($OfflineVarIds as $VarId) {
                $Ident = IPS_GetObject($VarId)['ObjectIdent'];
                if (strpos($Ident, 'MAC') === 0) {
                    if (array_key_exists($Ident, $HostVariables)) {
                        $Used = $HostVariables[$Ident];
                    } else {
                        $Used = $this->ReadPropertyBoolean('AutoAddHostVariables');
                    }
                    if ($Used) {
                        if (isset($xmlHosts)) {
                            $Mac = implode(':', str_split(substr($Ident, 3), 2));
                            $Xpath = $xmlHosts->xpath('//Item[MACAddress="' . $Mac . '"]/HostName');
                            if (count($Xpath) > 0) {
                                $Hostname = (string) $Xpath[0];
                                if ($Rename && (IPS_GetName($VarId) != $Hostname)) {
                                    IPS_SetName($VarId, $Hostname);
                                }
                            }
                        }
                        $this->SetValue($Ident, false);
                        $SpeedId = $this->RegisterSubVariable($VarId, 'Speed', 'Speed', VARIABLETYPE_INTEGER, 'FB.MBits');
                        SetValueInteger($SpeedId, 0);
                        $SignalId = $this->RegisterSubVariable($VarId, 'Signal', 'Signalstrength', VARIABLETYPE_INTEGER, '~Intensity.100');
                        SetValueInteger($SignalId, 0);
                    }
                }
            }
        }
        if ($Table) {
            $this->CreateWLANHTMLTable($TableData);
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

    public function GetHTMLQRCode()
    {
        $useVariable = $this->ReadPropertyBoolean('ShowWLanKeyAsVariable');
        $useQRCode = $this->ReadPropertyBoolean('ShowWLanKeyAsQRCode');
        $SSID = (string) $this->GetValue('SSID');
        if ($useQRCode && $useVariable) {
            $KeyPassphrase = @$this->GetValue('KeyPassphrase');
            $QRCodeID = @IPS_GetObjectIDByIdent('QRCodeIMG', $this->InstanceID);
            $QRData = IPS_GetMediaContent($QRCodeID);
        } else {
            $resultKeys = $this->GetSecurityKeys();
            if ($resultKeys === false) {
                return false;
            }
            $KeyPassphrase = (string) $resultKeys['NewKeyPassphrase'];
            $QRData = base64_encode($this->GenerateQRCodeData($SSID, $KeyPassphrase));
        }

        $HTMLData = '<center><h1 style="color:red">' . $this->Translate('Credentials') . '</h1><h2>WLAN: ' . $SSID . '</h2><h2>' . $this->Translate('Password') . ': ' . $KeyPassphrase . '</h2></center>';
        $HTMLData .= '<center><img src="data:image/png;base64,' . $QRData . '"></span></center>';
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
        if (!$this->APisGuest) {
            $this->UpdateInfo();
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
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('HostNumberOfEntries', 'Number of active WLAN devices', (int) $result, VARIABLETYPE_INTEGER, '', false, -2);
        return (int) $result;
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

    private function CreateWLANHTMLTable(array $TableData)
    {
        $HostName = array_column($TableData, 'Hostname');
        array_multisort($HostName, SORT_ASC, SORT_LOCALE_STRING, $TableData);
        $HTML = $this->GetTable($TableData, '', '', '', -1, true);
        $this->SetValue('HostTable', $HTML);
    }

    private function GetHostVariables(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        // Host holen für Namen
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
            return [];
        }
        $xmlHosts = new \simpleXMLElement($XMLData);
        if ($xmlHosts === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return [];
        }

        // WLAN Daten holen
        $Uri = $this->GetWLANDeviceListPath();
        if ($Uri === false) {
            $this->SendDebug('Error load WLAN Data', '', 0);
            return [];
        }
        $XMLData = $this->LoadAndGetData($Uri);
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'WLAN', 0);
            return [];
        }
        $xmlWLAN = new \simpleXMLElement($XMLData);
        if ($xmlWLAN === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return [];
        }

        $KnownVariableIDs = array_filter(IPS_GetChildrenIDs($this->InstanceID), function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if (substr($Ident, 0, 3) == 'MAC') {
                return true;
            }
            return false;
        });
        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = json_decode($this->ReadPropertyString('HostVariables'), true);
        // Property durchgehen und Werte ergänzen. Alle Idents merken
        $FoundIdents = array_column($HostVariables, 'ident');
        foreach ($HostVariables as &$HostVariable) {
            //$HostName = suche in $xmlHosts, sonst MAC
            $HostVariable['address'] = implode(':', str_split(substr($HostVariable['ident'], 3), 2));
            $HostName = $xmlHosts->xpath("//Item[MACAddress ='" . $HostVariable['address'] . "']");
            if (count($HostName) > 0) {
                $HostVariable['host'] = (string) $HostName[0]->HostName;
            } else {
                $HostVariable['host'] = 'unknown'; //$HostVariable['address'];
            }
            $VariableID = @$this->GetIDForIdent($HostVariable['ident']);
            if ($VariableID > 0) {
                $Key = array_search($VariableID, $KnownVariableIDs);
                unset($KnownVariableIDs[$Key]);
                $HostVariable['name'] = IPS_GetName($VariableID);
                $HostVariable['rowColor'] = ($HostVariable['name'] != IPS_GetName($VariableID)) ? '#DFDFDF' : '#FFFFFF';
            } else {
                $HostVariable['rowColor'] = '#FFFFFF';
                $HostVariable['name'] = '';
            }

            //prüfen ob in Hosts vorhanden
            $Found = $xmlHosts->xpath("//Item[MACAddress ='" . $HostVariable['address'] . "']");
            if (count($Found) > 0) {
                if (!$HostVariable['use']) {
                    $HostVariable['rowColor'] = '#C0FFC0';
                }
            } else {
                $HostVariable['host'] = $this->Translate('invalid');
                $HostVariable['rowColor'] = '#FFC0C0';
            }
        }
        // restliche Objekte aus WLANXML immer anhängen

        // hier WLAN Daten durchgehen und Namen in Host suchen
        // WLAN Daten filtern auf unseren Channel
        if ($this->APisGuest) {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceGuest = '1']");
        } else {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceChannel ='" . $this->Channel . "']");
        }
        foreach ($Devices as $xmlItem) {
            $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $xmlItem->AssociatedDeviceMACAddress));
            if (in_array($Ident, $FoundIdents)) {
                continue;
            }
            $Address = strtoupper((string) $xmlItem->AssociatedDeviceMACAddress);
            $HostName = $xmlHosts->xpath("//Item[MACAddress ='" . $Address . "']");
            if (count($HostName) > 0) {
                $Host = (string) $HostName[0]->HostName;
            } else {
                $Host = 'unknown';
            }
            $Name = '';
            $VariableID = @$this->GetIDForIdent($Ident);
            if ($VariableID > 0) {
                $Name = IPS_GetName($VariableID);
                $Key = array_search($VariableID, $KnownVariableIDs);
                unset($KnownVariableIDs[$Key]);
                $RowColor = ($Name != $Host) ? '#DFDFDF' : '#FFFFFF';
                $Used = true;
            } else {
                $RowColor = '#C0FFC0';
                $Used = false;
            }
            $HostVariables[] = [
                'ident'   => $Ident,
                'address' => $Address,
                'name'    => $Name,
                'host'    => $Host,
                'rowColor'=> $RowColor,
                'use'     => $Used
            ];
        }
        // restliche Idents aus dem Objektbaum hinzufügen, wenn auto-Add aktiv
        foreach ($KnownVariableIDs as $VariableID) {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            $Address = implode(':', str_split(substr($Ident, 3), 2));
            $Name = IPS_GetName($VariableID);
            $Host = '';
            $FoundAddress = $xmlHosts->xpath("//Item[MACAddress ='" . $Address . "']");
            if (count($FoundAddress) > 0) {
                $Host = (string) $FoundAddress[0]->HostName;
                $RowColor = ($Name != $Host) ? '#DFDFDF' : '#FFFFFF';
            } else {
                $Address = '';
                $Host = $this->Translate('invalid');
                $RowColor = '#FFC0C0';
            }

            $HostVariables[] = [
                'ident'   => $Ident,
                'address' => $Address,
                'name'    => $Name,
                'host'    => $Host,
                'rowColor'=> $RowColor,
                'use'     => true
            ];
        }
        return $HostVariables;
    }

    private function GetWLANForm(array &$Options)
    {
        $result = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'GetMaxWLANs'
            ]
        ));
        if ($result === false) {
            return;
        }
        $NoOfWlan = unserialize($result);
        for ($i = 1; $i <= $NoOfWlan; $i++) {
            $Index = $Options[$i]['value'];
            $result = $this->SendDataToParent(json_encode(
                [
                    'DataID'    => \FritzBox\GUID::SendToFritzBoxIO,
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
                $Options[$i]['caption'] = $i . $this->Translate(' (Guest)');
                continue;
            }
            $result = $this->SendDataToParent(json_encode(
                [
                    'DataID'    => \FritzBox\GUID::SendToFritzBoxIO,
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
            if ((int) $info['NewChannel'] < 14) {
                $Options[$i]['caption'] = $i . $this->Translate(' (2,4 Ghz)');
            } else {
                $Options[$i]['caption'] = $i . $this->Translate(' (5 Ghz)');
            }
        }
    }

    private function UpdateInfo()
    {
        $resultState = $this->GetInfo();
        if ($resultState === false) {
            return false;
        }
        $this->Channel = (int) $resultState['NewChannel'];
        $this->setIPSVariable('X_AVM_DE_APEnabled', 'WLAN state', (int) $resultState['NewEnable'] !== 0, VARIABLETYPE_BOOLEAN, '~Switch', false, -11);
        $this->setIPSVariable('SSID', 'SSID Name', (string) $resultState['NewSSID'], VARIABLETYPE_STRING, '', false, -10);
        if ($this->ReadPropertyBoolean('InfoVariables')) {
            $result = $this->GetWLANExtInfo();
            if ($result === false) {
                return false;
            }
            $this->APisGuest = ((string) $result['NewX_AVM-DE_APType'] == 'guest');
            if ((string) $result['NewX_AVM-DE_APType'] == 'guest') {
                $this->setIPSVariable('TimeoutActive', 'Timeout active', $result['NewX_AVM-DE_TimeoutActive'], VARIABLETYPE_BOOLEAN, '~Switch', false, -7);
                $this->setIPSVariable('TimeRemainRaw', 'Remain time in minutes', (int) $result['NewX_AVM-DE_TimeRemain'], VARIABLETYPE_INTEGER, '', false, -6);
                $this->setIPSVariable('TimeRemain', 'Remain time', $this->ConvertRuntime(((int) $result['NewX_AVM-DE_TimeRemain']) * 60), VARIABLETYPE_STRING, '', false, -5);
                $this->setIPSVariable('OffTime', 'Scheduled shutdown', time() + ((int) $result['NewX_AVM-DE_TimeRemain'] * 60), VARIABLETYPE_INTEGER, '~UnixTimestamp', false, -4);
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
                $this->setIPSVariable('KeyPassphrase', 'Password', (string) $resultKeys['NewKeyPassphrase'], VARIABLETYPE_STRING, '', false, -9);
            }
            if ($useQRCode) {
                $QRData = $this->GenerateQRCodeData((string) $resultState['NewSSID'], (string) $resultKeys['NewKeyPassphrase']);

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

    private function GenerateQRCodeData(string $SSID, string $KeyPassphrase, int $size = 0)
    {
        $CodeText = 'WIFI:S:' . $SSID . ';T:WPA;P:' . $KeyPassphrase . ';;';
        $Size = $this->ReadPropertyInteger('QRCodeSize');
        include __DIR__ . '/../libs/phpqrcode/qrlib.php';
        ob_start();
        QRcode::png($CodeText, null, QR_ECLEVEL_L, $Size);
        $QRImage = ob_get_contents();
        ob_end_clean();
        return $QRImage;
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
                'index'   => 0,
                'key'     => 'Hostname',
                'name'    => $this->Translate('Hostname'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],
            [
                'index'   => 1,
                'key'     => 'IPAddress',
                'name'    => $this->Translate('IP-Address'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 2,
                'key'     => 'MACAddress',
                'name'    => $this->Translate('MAC-Address'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 3,
                'key'     => 'Speed',
                'name'    => $this->Translate('Speed'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 4,
                'key'     => 'Signalstrength',
                'name'    => $this->Translate('Signalstrength'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
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
                'bgcolor' => -1,
                'color'   => -1,
                'style'   => ''
            ],
            [
                'row'     => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => -1,
                'color'   => -1,
                'style'   => ''
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
    }
}
