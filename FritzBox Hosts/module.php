<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
/**
 * @property int $HostNumberOfEntriesId
 * @property bool $ShowVariableWarning
 */
class FritzBoxHosts extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;

    protected static $ControlUrlArray = [
        '/upnp/control/hosts'
    ];
    protected static $EventSubURLArray = [
        '/upnp/control/hosts'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:Hosts:1'
    ];
    protected static $SecondEventGUID = '{3C010D20-02A3-413A-9C5E-D0747D61BEF0}';
    protected static $DefaultIndex = 0;
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->HostNumberOfEntriesId = 0;
        $this->RegisterPropertyBoolean('HostAsVariable', false);
        $UsedVariableIdents = array_map(function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if ((substr($Ident, 0, 2) == 'IP') || (substr($Ident, 0, 3) == 'MAC')) {
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
        $this->RegisterPropertyBoolean('AutoAddHostVariables', true);
        $this->RegisterPropertyBoolean('RenameHostVariables', true);
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterPropertyBoolean('HostAsTable', true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));

        $this->RegisterTimer('RefreshHosts', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshHosts",true);');
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->UnregisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
        $this->SetTimerInterval('RefreshHosts', 0);
        $this->HostNumberOfEntriesId = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number of network devices'), '', -2);
        $this->SetTimerInterval('RefreshHosts', 0);
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        if ($Table) {
            $this->RegisterVariableString('HostTable', $this->Translate('Network devices'), '~HTMLBox', -3);
        } else {
            $this->UnregisterVariable('HostTable');
        }
        if (!($Variable || ($Table))) {
            $this->SetStatus(IS_INACTIVE);
            return;
        }

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

        $this->SetTimerInterval('RefreshHosts', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
        usleep(5);
        parent::ApplyChanges();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case VM_UPDATE:
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
            case 'RefreshHosts':
                return $this->UpdateHostNumberOfEntries();
            case 'HostAsVariable':
                $this->UpdateFormField('AutoAddHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('RenameHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostvariablesPanel', 'expanded', (bool) $Value);
                $this->UpdateFormField('HostVariables', 'enabled', (bool) $Value);
                return;
            case 'HostTableAsVariable':
                $this->UpdateFormField('Table', 'enabled', (bool) $Value);
                $this->UpdateFormField('Columns', 'enabled', (bool) $Value);
                $this->UpdateFormField('Rows', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostAsTablePanel', 'expanded', (bool) $Value);
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
        if (strpos($Ident, 'MAC') === 0) {
            if ($Value === true) {
                $MACAddress = implode(':', str_split(substr($Ident, 3), 2));
                $this->WakeOnLANByMACAddress($MACAddress);
            }
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
        if (!$this->ReadPropertyBoolean('HostAsTable')) {
            $Form['elements'][3]['items'][0]['enabled'] = false;
            $Form['elements'][3]['items'][1]['enabled'] = false;
            $Form['elements'][3]['items'][2]['enabled'] = false;
        }
        if (!$this->ReadPropertyBoolean('HostAsVariable')) {
            $this->ShowVariableWarning = false;
            $Form['elements'][1]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][1]['items'][0]['items'][2]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][0]['enabled'] = false;
        } else {
            $this->ShowVariableWarning = true;
            $Form['elements'][1]['items'][1]['onClick'] = 'IPS_RequestAction($id,"HostvariablesPanel", true);';
        }
        $Values = $this->GetHostVariables();
        if (count($Values) == 0) {
            // Fallback für konfigurierte Statusvariablen der Hosts, wenn Abfrage fehlschlägt; z.B. wenn IO offline
            $Values = json_decode($this->ReadPropertyString('HostVariables'), true);
        }
        $Form['elements'][1]['items'][1]['items'][0]['values'] = $Values;
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
        if ($data['Function'] == 'RefreshHostList') {
            $File = $this->GetHostListPath();
            if ($File === false) {
                return false;
            }
            if (!$this->LoadAndSaveFile($File, 'Hosts')) {
                return false;
            }
        }
        return 'OK';
    }
    public function RefreshHostList()
    {
        if ($this->ParentID == 0) {
            return false;
        }

        $File = $this->GetHostListPath();
        if ($File === false) {
            return false;
        }

        if (!$this->LoadAndSaveFile($File, 'Hosts')) {
            return false;
        }
        $this->SendDebug('Fire', 'NewHostListEvent', 0);
        $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'NewHostListEvent'
            ]
        ));

        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        if (!($Variable || ($Table))) {
            return true;
        }
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
            return false;
        }
        $xml = new \simpleXMLElement($XMLData);
        if ($xml === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return false;
        }
        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = array_column(json_decode($this->ReadPropertyString('HostVariables'), true), 'use', 'ident');
        $OnlineCounter = 0;
        $TableData = [];
        foreach ($xml as $xmlItem) {
            if ((string) $xmlItem->MACAddress == '') {
                $Ident = 'IP' . strtoupper($this->ConvertIdent((string) $xmlItem->IPAddress));
                $Action = false;
            } else {
                $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $xmlItem->MACAddress));
                $Action = true;
            }
            if ($Variable) {
                if (array_key_exists($Ident, $HostVariables)) {
                    $Used = $HostVariables[$Ident];
                } else {
                    $Used = $this->ReadPropertyBoolean('AutoAddHostVariables');
                }

                if ($Used) {
                    $VarId = @$this->GetIDForIdent($Ident);
                    $this->setIPSVariable($Ident, (string) $xmlItem->HostName, (int) $xmlItem->Active == 1, VARIABLETYPE_BOOLEAN, '~Switch', $Action);
                    if ($VarId == 0) {
                        $VarId = $this->GetIDForIdent($Ident);
                        //Standard-Aktion vorhanden, aber default nicht aktiv (WOL)
                        IPS_SetVariableCustomAction($VarId, 1);
                    } else {
                        if ($Rename) {
                            if (IPS_GetName($VarId) != (string) $xmlItem->HostName) {
                                IPS_SetName($VarId, (string) $xmlItem->HostName);
                            }
                        }
                    }
                }
            }
            if ((int) $xmlItem->Active == 1) {
                $OnlineCounter++;
                $xmlItem->Active = '<div class="isactive">' . $this->Translate('Active') . '</div>';
            } else {
                $xmlItem->Active = '<div class="isinactive">' . $this->Translate('Inactive') . '</div>';
            }
            if ($Table) {
                $TableData[] = (array) $xmlItem;
            }
        }
        $this->setIPSVariable('HostNumberActive', 'Number of active network devices', $OnlineCounter, VARIABLETYPE_INTEGER, '', false, -1);
        if ($Table) {
            $this->CreateHostHTMLTable($TableData);
        }
        return true;
    }
    public function GetHostNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetSpecificHostEntry(string $MACAddress)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewMACAddress'=> $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetGenericHostEntry(int $Index)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIndex'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetSpecificHostEntryByIP(string $IPAddress)
    {
        $result = $this->Send('X_AVM-DE_GetSpecificHostEntryByIP', [
            'NewIPAddress'=> $IPAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetChangeCounter()
    {
        $result = $this->Send('X_AVM-DE_GetChangeCounter');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetHostNameByMACAddress(string $MACAddress, string $Hostname)
    {
        $result = $this->Send('X_AVM-DE_SetHostNameByMACAddress', [
            'NewMACAddress'=> $MACAddress,
            'NewHostName'  => $Hostname
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetAutoWakeOnLANByMACAddress(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_GetAutoWakeOnLANByMACAddress', [
            'NewMACAddress'=> $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetAutoWakeOnLANByMACAddress(string $MACAddress, bool $Enabled)
    {
        $result = $this->Send('X_AVM-DE_SetAutoWakeOnLANByMACAddress', [
            'NewMACAddress'    => $MACAddress,
            'NewAutoWOLEnabled'=> $Enabled
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function WakeOnLANByMACAddress(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_WakeOnLANByMACAddress', [
            'NewMACAddress'    => $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function HostsCheckUpdate()
    {
        $result = $this->Send('X_AVM-DE_HostsCheckUpdate');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function HostDoUpdate(string $MACAddress)
    {
        $result = $this->Send('X_AVM-DE_HostDoUpdate', [
            'NewMACAddress'    => $MACAddress
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetHostListPath()
    {
        $result = $this->Send('X_AVM-DE_GetHostListPath');
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetMeshListPath()
    {
        $result = $this->Send('X_AVM-DE_GetMeshListPath');
        if ($result === false) {
            return false;
        }
        return $result;
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
        foreach ($xmlHosts as $Host) {
            $Ident = 'IP' . strtoupper($this->ConvertIdent((string) $Host->IPAddress));
            $KnownHostNames[$Ident] = ['Hostname' => (string) $Host->HostName, 'IPAddress' => (string) $Host->IPAddress];
        }
        $KnownVariableIDs = array_filter(IPS_GetChildrenIDs($this->InstanceID), function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if ((substr($Ident, 0, 2) == 'IP') || (substr($Ident, 0, 3) == 'MAC')) {
                return true;
            }
            return false;
        });
        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = json_decode($this->ReadPropertyString('HostVariables'), true);
        // Property durchgehen und Werte ergänzen. Alle Idents merken
        $FoundIdents = array_column($HostVariables, 'ident');
        foreach ($HostVariables as &$HostVariable) {
            if ((substr($HostVariable['ident'], 0, 3) == 'MAC')) {
                $HostVariable['address'] = implode(':', str_split(substr($HostVariable['ident'], 3), 2));
                $HostName = $xmlHosts->xpath("//Item[MACAddress ='" . $HostVariable['address'] . "']");
                if (count($HostName) > 0) {
                    $HostVariable['host'] = (string) $HostName[0]->HostName;
                } else {
                    $HostVariable['host'] = ''; //$HostVariable['address'];
                }
            } else {
                if (array_key_exists($HostVariable['ident'], $KnownHostNames)) {
                    $HostVariable['host'] = $KnownHostNames[$HostVariable['ident']]['Hostname'];
                    $HostVariable['address'] = $KnownHostNames[$HostVariable['ident']]['IPAddress'];
                } else {
                    $HostVariable['host'] = '';
                    $HostVariable['address'] = '';
                }
            }
            $VariableID = @$this->GetIDForIdent($HostVariable['ident']);
            if ($VariableID > 0) {
                $Key = array_search($VariableID, $KnownVariableIDs);
                unset($KnownVariableIDs[$Key]);
                $HostVariable['name'] = IPS_GetName($VariableID);
                $HostVariable['rowColor'] = ($HostVariable['host'] != $HostVariable['name']) ? '#DFDFDF' : '#FFFFFF';
            } else {
                $HostVariable['rowColor'] = '#FFFFFF';
                $HostVariable['name'] = '';
            }

            //prüfen ob in Hosts vorhanden
            if ((substr($HostVariable['ident'], 0, 3) == 'MAC')) {
                $Found = $xmlHosts->xpath("//Item[MACAddress ='" . $HostVariable['address'] . "']");
                if (count($Found) > 0) {
                    if (!$HostVariable['use']) {
                        $HostVariable['rowColor'] = '#C0FFC0';
                    }
                } else {
                    $HostVariable['host'] = $this->Translate('invalid');
                    $HostVariable['rowColor'] = '#FFC0C0';
                }
            } else {
                if (array_key_exists($HostVariable['ident'], $KnownHostNames)) {
                    if (!$HostVariable['use']) {
                        $HostVariable['rowColor'] = '#C0FFC0';
                    }
                } else {
                    $HostVariable['rowColor'] = '#FFFFFF';
                }
            }
        }
        // restliche Objekte aus HOST immer anhängen
        foreach ($xmlHosts as $Host) {
            if ((string) $Host->MACAddress == '') {
                $Address = (string) $Host->IPAddress;
                $Ident = 'IP' . strtoupper($this->ConvertIdent((string) $Host->IPAddress));
            } else {
                $Address = (string) $Host->MACAddress;
                $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $Host->MACAddress));
            }
            if (in_array($Ident, $FoundIdents)) {
                continue;
            }
            $Host = (string) $Host->HostName;
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
            if ((substr($Ident, 0, 2) == 'IP')) {
                continue;
            }
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
    private function CreateHostHTMLTable(array $TableData)
    {
        $HostName = array_column($TableData, 'HostName');
        array_multisort($HostName, SORT_ASC, SORT_LOCALE_STRING, $TableData);
        $HTML = $this->GetTable($TableData, '', '', '', -1, true);
        $this->SetValue('HostTable', $HTML);
    }

    private function UpdateHostNumberOfEntries()
    {
        $result = $this->GetHostNumberOfEntries();
        if ($result === false) {
            return false;
        }
        $this->setIPSVariable('HostNumberOfEntries', 'Number of network devices', (int) $result, VARIABLETYPE_INTEGER, '', false, -2);
        return true;
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
            ],
            [
                'tag'   => 'active',
                'style' => 'background-image: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -moz-linear-gradient(50% 0%, transparent 0px, rgba(0, 0, 0, 0.3) 28%, rgba(0, 0, 0, 0.3) 100%);
                background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                color: rgba(255, 255, 255, 0.3);
                color: rgb(255, 255, 255);
                background-color: rgba(255,255,255,0.1);
                background-color: rgb(0, 255, 0);
                width: 25%;
                display: inline-block;
                margin: 2px 0px 1px 3px;
                border-color: transparent;
                border-style: solid;
                border-width: 1px 0px;
                padding: 0px 10px;
                vertical-align: middle;'
            ],
            [
                'tag'   => 'inactive',
                'style' => 'background-image: linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -o-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -moz-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -webkit-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-image: -ms-linear-gradient(top,rgba(0,0,0,0) 0,rgba(0,0,0,0.3) 28%,rgba(0,0,0,0.3) 100%);
                background-color: rgba(255, 255, 255, 0.3);
                width: 25%;
                display: inline-block;
                margin: 2px 0px 1px 3px;
                border-color: transparent;
                border-style: solid;
                border-width: 1px 0px;
                padding: 0px 10px;
                vertical-align: middle;'
            ]
        ];
        $NewColumnsConfig = [
            [
                'index'   => 0,
                'key'     => 'HostName',
                'name'    => $this->Translate('Hostname'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
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
                'tdalign' => 'center',
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
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index'   => 3,
                'key'     => 'InterfaceType',
                'name'    => $this->Translate('Interface'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],            [
                'index'   => 4,
                'key'     => 'Active',
                'name'    => $this->Translate('Connection'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
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
