<?php

declare(strict_types=1);

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
        '/upnp/control/wlanconfig3',
        '/upnp/control/wlanconfig4'
    ];
    protected static $EventSubURLArray = [
        '/upnp/control/wlanconfig1',
        '/upnp/control/wlanconfig2',
        '/upnp/control/wlanconfig3',
        '/upnp/control/wlanconfig4'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:WLANConfiguration:1',
        'urn:dslforum-org:service:WLANConfiguration:2',
        'urn:dslforum-org:service:WLANConfiguration:3',
        'urn:dslforum-org:service:WLANConfiguration:4'
    ];
    protected static $SecondEventGUID = \FritzBox\GUID::NewHostListEvent;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->APEnabledId = 0;
        $this->HostNumberOfEntriesId = 0;
        $this->APisGuest = false;

        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::HostAsVariable, false);
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
        $this->RegisterPropertyString(\FritzBox\WLAN\Property::HostVariables, json_encode(array_values($UsedVariableIdents)));
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::InfoVariables, false);
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::AutoAddHostVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::RenameHostVariables, false);
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsVariable, false);
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsQRCode, false);
        $this->RegisterPropertyInteger(\FritzBox\WLAN\Property::QRCodeSize, 20);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterPropertyBoolean(\FritzBox\WLAN\Property::HostAsTable, false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\FritzBox\Table\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Rows, json_encode($Style['Rows']));

        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterProfile('FB.MBits');
            $QRCodeID = @IPS_GetObjectIDByIdent(\FritzBox\WLAN\Media::QRCodeIMG, $this->InstanceID);
            if ($QRCodeID > 0) {
                @IPS_DeleteMedia($QRCodeID, true);
            }
        }
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, 0);
        if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsVariable)) {
            $QRCodeID = @IPS_GetObjectIDByIdent(\FritzBox\WLAN\Media::QRCodeIMG, $this->InstanceID);
            if ($QRCodeID !== false) {
                IPS_DeleteMedia($QRCodeID, true);
            }
        }
        if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsQRCode)) {
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::KeyPassphrase);
        }
        if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::InfoVariables)) {
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::TimeoutActive);
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::TimeRemainRaw);
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::TimeRemain);
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::OffTime);
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::ForcedOff);
        }
        if ($this->ReadPropertyBoolean(\FritzBox\WLAN\Property::HostAsTable)) {
            $this->RegisterVariableString(\FritzBox\WLAN\Variables::HostTable, $this->Translate('Host Table'), '~HTMLBox', -1);
        } else {
            $this->UnregisterVariable(\FritzBox\WLAN\Variables::HostTable);
        }
        $this->RegisterProfileInteger('FB.MBits', '', '', ' MBit/s', 0, 0, 0);
        $this->APEnabledId = $this->RegisterVariableBoolean(\FritzBox\WLAN\Variables::X_AVM_DE_APEnabled, $this->Translate('WLAN state'), '~Switch', -11);
        $this->EnableAction(\FritzBox\WLAN\Variables::X_AVM_DE_APEnabled);
        $this->UnregisterMessage($this->APEnabledId, VM_UPDATE);
        usleep(5);
        $HostVariables = json_decode($this->ReadPropertyString(\FritzBox\WLAN\Property::HostVariables), true);
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
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if ($Index == -1) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }
        if (IPS_GetKernelRunlevel() == KR_READY) {
            @$this->UpdateInfo();
            if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::InfoVariables)) {
                $result = $this->GetWLANExtInfo();
                if ($result !== false) {
                    $this->APisGuest = ((string) $result['NewX_AVM-DE_APType'] == 'guest');
                }
            }
        }
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        $this->RegisterMessage($this->APEnabledId, VM_UPDATE);
        $this->HostNumberOfEntriesId = $this->RegisterVariableInteger(\FritzBox\WLAN\Variables::HostNumberOfEntries, $this->Translate('Number of active WLAN devices'), '', -2);
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
            case \FritzBox\WLAN\Action::ReloadForm:
                IPS_Sleep(2000);
                $this->ReloadForm();
                return;
            case \FritzBox\Base\Action::RefreshState:
                return $this->UpdateInfo();
            case \FritzBox\WLAN\Variables::X_AVM_DE_APEnabled:
                return $this->SetEnable((bool) $Value);
            case \FritzBox\WLAN\Action::ShowWLanKeyAsQRCode:
                $this->UpdateFormField('QRCodeSize', 'enabled', (bool) $Value);
                return;
            case \FritzBox\WLAN\Action::HostAsTable:
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostAsTablePanel', 'expanded', (bool) $Value);
                return;
            case \FritzBox\WLAN\Action::HostAsVariable:
                $this->UpdateFormField('AutoAddHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('RenameHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostvariablesPanel', 'expanded', (bool) $Value);
                $this->UpdateFormField('HostVariables', 'enabled', (bool) $Value);
                return;
            case \FritzBox\WLAN\Action::HostvariablesPanel:
                if ($this->ShowVariableWarning) {
                    $this->UpdateFormField('ErrorPopup', 'visible', true);
                    $this->UpdateFormField('ErrorTitle', 'caption', 'Attention!');
                    $this->UpdateFormField('ErrorText', 'caption', 'Deselecting a host causes the associated status variable to be deleted.');
                    $this->ShowVariableWarning = false;
                }
                return;
            case \FritzBox\WLAN\Action::DelHostVariable:
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
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
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
            if ($this->ParentID > 1) {
                $ConfiguratorID = $this->GetConfiguratorID();
                if ($ConfiguratorID > 1) {
                    $Form['actions'][2]['popup']['items'][2]['caption'] = 'Open Configurator';
                    $Form['actions'][2]['popup']['items'][2]['visible'] = true;
                    $Form['actions'][2]['popup']['items'][2]['objectID'] = $ConfiguratorID;
                }
            }
        }
        if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::HostAsTable)) {
            $Form['elements'][6]['items'][0]['enabled'] = false;
            $Form['elements'][6]['items'][1]['enabled'] = false;
            $Form['elements'][6]['items'][2]['enabled'] = false;
        }

        if ($this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsQRCode)) {
            $QRCodeID = @IPS_GetObjectIDByIdent(\FritzBox\WLAN\Media::QRCodeIMG, $this->InstanceID);
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

        if (!$this->ReadPropertyBoolean(\FritzBox\WLAN\Property::HostAsVariable)) {
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
            $Values = json_decode($this->ReadPropertyString(\FritzBox\WLAN\Property::HostVariables), true);
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
        if ($data['Function'] == \FritzBox\IOFunction::NewHostListEvent) {
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
        $Table = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::HostAsTable);
        $Variable = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::HostAsVariable);
        $Rename = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::RenameHostVariables);
        if (!($Variable || ($Table))) {
            return true;
        }
        // Host holen für Namen
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
        } else {
            try {
                $xmlHosts = new \simpleXMLElement(trim($XMLData));
            } catch (\Throwable $th) {
                $this->SendDebug('XML decode error', $XMLData, 0);
                $this->SendDebug('XML decode trace', $th->getTrace(), 0);
                $this->LogMessage($th->getMessage(), KL_ERROR);
                unset($xmlHosts);
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
        try {
            $xmlWLAN = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
            return false;
        }
        // WLAN Daten filtern auf unseren Channel
        if ($this->APisGuest) {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceGuest = '1']");
        } else {
            $Devices = $xmlWLAN->xpath("//Item[AssociatedDeviceChannel ='" . $this->Channel . "']");
        }

        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = array_column(json_decode($this->ReadPropertyString(\FritzBox\WLAN\Property::HostVariables), true), 'use', 'ident');

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
                    $Used = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::AutoAddHostVariables);
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
                        $Used = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::AutoAddHostVariables);
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
        $useVariable = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsVariable);
        $useQRCode = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsQRCode);
        $SSID = (string) $this->GetValue(\FritzBox\WLAN\Variables::SSID);
        if ($useQRCode && $useVariable) {
            $KeyPassphrase = @$this->GetValue(\FritzBox\WLAN\Variables::KeyPassphrase);
            $QRCodeID = @IPS_GetObjectIDByIdent(\FritzBox\WLAN\Media::QRCodeIMG, $this->InstanceID);
            $QRData = IPS_GetMediaContent($QRCodeID);
        } else {
            $resultKeys = $this->GetKeyPassphrase();
            if ($resultKeys === false) {
                return false;
            }
            $KeyPassphrase = (string) $resultKeys;
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

    public function SetKeyPassphrase(
        string $KeyPassphrase
    ) {
        $result = $this->Send('SetSecurityKeys', [
            'NewWEPKey0'                    => '',
            'NewWEPKey1'                    => '',
            'NewWEPKey2'                    => '',
            'NewWEPKey3'                    => '',
            'NewPreSharedKey'               => '',
            'NewKeyPassphrase'              => $KeyPassphrase
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }

    public function GetKeyPassphrase()
    {
        $result = $this->Send('GetSecurityKeys');
        if ($result === false) {
            return false;
        }
        return (string) $result['NewKeyPassphrase'];
    }

    /**
     * Liefert Modus und Status von WPS für dieses WLAN.
     *
     * @return array|false ['NewX_AVM-DE_WPSMode' => other|stop|pbc, 'NewX_AVM-DE_WPSStatus' => off|inactive|active|success|err_…]
     */
    public function GetWPSInfo()
    {
        $result = $this->Send('X_AVM-DE_GetWPSInfo');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Aktiviert oder deaktiviert WPS für dieses WLAN.
     *
     * @param bool $Enable true = WPS aktivieren
     *
     * @return bool
     */
    public function SetWPSEnable(bool $Enable)
    {
        $result = $this->Send('X_AVM-DE_SetWPSEnable', [
            'NewX_AVM-DE_WPSEnable'=> (int) $Enable
        ]);
        return $result !== false;
    }

    /**
     * Schaltet alle WLAN-Funknetze der FritzBox gemeinsam ein oder aus.
     *
     * @param bool $Enable true = WLAN global einschalten
     *
     * @return bool
     */
    public function SetWLANGlobalEnable(bool $Enable)
    {
        $result = $this->Send('X_AVM-DE_SetWLANGlobalEnable', [
            'NewX_AVM-DE_WLANGlobalEnable'=> (int) $Enable
        ]);
        return $result !== false;
    }

    /**
     * Liefert Informationen zur WLAN-Verbindung, wenn die FritzBox selbst als WLAN-Client verbunden ist (z.B. als Repeater).
     *
     * @return array|false
     */
    public function GetWLANConnectionInfo()
    {
        $result = $this->Send('X_AVM-DE_GetWLANConnectionInfo');
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Liefert, ob die Optimierung für IPTV in diesem WLAN aktiv ist.
     *
     * @return int|false 1 = aktiv, 0 = nicht aktiv, false bei Fehler
     */
    public function GetIPTVOptimized()
    {
        $result = $this->Send('X_AVM-DE_GetIPTVOptimized');
        if ($result === false) {
            return false;
        }
        return (int) $result;
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
        $this->setIPSVariable(\FritzBox\WLAN\Variables::HostNumberOfEntries, 'Number of active WLAN devices', (int) $result, VARIABLETYPE_INTEGER, '', false, -2);
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
        $this->SetValue(\FritzBox\WLAN\Variables::HostTable, $HTML);
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
        try {
            $xmlHosts = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
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
        try {
            $xmlWLAN = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
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
        $HostVariables = json_decode($this->ReadPropertyString(\FritzBox\WLAN\Property::HostVariables), true);
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
                'Function'   => \FritzBox\IOFunction::GetMaxWLANs
            ]
        ));
        if ($result === false) {
            return;
        }
        $NoOfWlan = unserialize($result);
        for ($i = 1; $i <= $NoOfWlan; $i++) {
            $Index = $Options[$i]['value'];
            $ExtInfo = $this->SendEx('X_AVM-DE_GetWLANExtInfo', $Index);
            if ($ExtInfo === false) {
                continue;
            }
            if ($ExtInfo['NewX_AVM-DE_APType'] == 'guest') {
                $Options[$i]['caption'] = $i . $this->Translate(' (Guest)');
                continue;
            }
            switch ($ExtInfo['NewX_AVM-DE_FrequencyBand']) {
                case 2400:
                    $Options[$i]['caption'] = $i . $this->Translate(' (2,4 Ghz)');
                    break;
                case 5000:
                    $Options[$i]['caption'] = $i . $this->Translate(' (5 Ghz)');
                    break;
                default:
                    $Options[$i]['caption'] = $i . $this->Translate(' (unknown)');
                    break;
            }
            /*            $info = $this->SendEx('GetInfo', $Index);
                        if ($info === false) {
                            continue;
                        }
                        if ((int) $info['NewChannel'] < 14) {
                            $Options[$i]['caption'] = $i . $this->Translate(' (2,4 Ghz)');
                        } else {
                            $Options[$i]['caption'] = $i . $this->Translate(' (5 Ghz)');
                        }*/
        }

        $Options = array_slice($Options, 0, $i, true);

    }

    private function UpdateInfo()
    {
        $resultState = $this->GetInfo();
        if ($resultState === false) {
            return false;
        }
        $this->Channel = (int) $resultState['NewChannel'];
        $this->setIPSVariable(\FritzBox\WLAN\Variables::X_AVM_DE_APEnabled, 'WLAN state', (int) $resultState['NewEnable'] !== 0, VARIABLETYPE_BOOLEAN, '~Switch', false, -11);
        $this->setIPSVariable(\FritzBox\WLAN\Variables::SSID, 'SSID Name', (string) $resultState['NewSSID'], VARIABLETYPE_STRING, '', false, -10);
        if ($this->ReadPropertyBoolean(\FritzBox\WLAN\Property::InfoVariables)) {
            $result = $this->GetWLANExtInfo();
            if ($result === false) {
                return false;
            }
            $this->APisGuest = ((string) $result['NewX_AVM-DE_APType'] == 'guest');
            if ((string) $result['NewX_AVM-DE_APType'] == 'guest') {
                $this->setIPSVariable(\FritzBox\WLAN\Variables::TimeoutActive, 'Timeout active', $result['NewX_AVM-DE_TimeoutActive'], VARIABLETYPE_BOOLEAN, '~Switch', false, -7);
                $this->setIPSVariable(\FritzBox\WLAN\Variables::TimeRemainRaw, 'Remain time in minutes', (int) $result['NewX_AVM-DE_TimeRemain'], VARIABLETYPE_INTEGER, '', false, -6);
                $this->setIPSVariable(\FritzBox\WLAN\Variables::TimeRemain, 'Remain time', $this->ConvertRuntime(((int) $result['NewX_AVM-DE_TimeRemain']) * 60), VARIABLETYPE_STRING, '', false, -5);
                $this->setIPSVariable(\FritzBox\WLAN\Variables::OffTime, 'Scheduled shutdown', time() + ((int) $result['NewX_AVM-DE_TimeRemain'] * 60), VARIABLETYPE_INTEGER, '~UnixTimestamp', false, -4);
                $this->setIPSVariable(\FritzBox\WLAN\Variables::ForcedOff, 'No shutdown when guest is active', $result['NewX_AVM-DE_NoForcedOff'], VARIABLETYPE_BOOLEAN, '~Switch', false, -3);
            }
        }
        $useVariable = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsVariable);
        $useQRCode = $this->ReadPropertyBoolean(\FritzBox\WLAN\Property::ShowWLanKeyAsQRCode);

        if ($useQRCode || $useVariable) {
            $resultKeys = $this->GetKeyPassphrase();
            if ($resultKeys === false) {
                return false;
            }
            if ($useVariable) {
                $this->setIPSVariable(\FritzBox\WLAN\Variables::KeyPassphrase, 'Password', (string) $resultKeys, VARIABLETYPE_STRING, '', false, -9);
            }
            if ($useQRCode) {
                $QRData = $this->GenerateQRCodeData((string) $resultState['NewSSID'], (string) $resultKeys);

                $QRCodeID = @IPS_GetObjectIDByIdent(\FritzBox\WLAN\Media::QRCodeIMG, $this->InstanceID);
                if ($QRCodeID === false) {
                    $QRCodeID = IPS_CreateMedia(1);
                    IPS_SetParent($QRCodeID, $this->InstanceID);
                    IPS_SetIdent($QRCodeID, \FritzBox\WLAN\Media::QRCodeIMG);
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
        $Size = $this->ReadPropertyInteger(\FritzBox\WLAN\Property::QRCodeSize);
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
