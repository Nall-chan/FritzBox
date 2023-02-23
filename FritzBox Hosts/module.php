<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
/**
 * @property int $HostNumberOfEntriesId
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
    protected static $SecondEventGUID = '{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';
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

        parent::ApplyChanges();
        $this->RefreshHostList();

        $this->SetTimerInterval('RefreshHosts', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
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
        $this->SendDebug('ReceiveHostData', $data, 0);
        return true;
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
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        if (!($Variable || ($Table))) {
            return true;
        }

        $HostVariables = array_column(json_decode($this->ReadPropertyString('HostVariables'), true), 'use', 'ident');

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
        $this->SendDebug('XML', $XMLData, 0);
        $OnlineCounter = 0;
        $TableData = [];
        foreach ($xml as $xmlItem) {
            //$this->SendDebug('XML xmlItem', (array)$xmlItem, 0);
            if ((string) $xmlItem->MACAddress == '') {
                $Ident = 'IP' . strtoupper($this->ConvertIdent((string) $xmlItem->IPAddress));
                $Action = false;
            } else {
                $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $xmlItem->MACAddress));
                $Action = true;
            }
            if ($Variable) {
                if (array_key_exists($Ident, $HostVariables)) {
                    if ($HostVariables[$Ident]) {
                        $VarId = @$this->GetIDForIdent($Ident);
                        $this->setIPSVariable($Ident, (string) $xmlItem->HostName, (int) $xmlItem->Active == 1, VARIABLETYPE_BOOLEAN, '~Switch', $Action);
                        if ($VarId == 0) {
                            $VarId = $this->GetIDForIdent($Ident);
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
            }
            if ((int) $xmlItem->Active == 1) {
                $OnlineCounter++;
                $xmlItem->Active = '<div class="isactive">' . $this->Translate('Active') . '</div>';
            } else {
                $xmlItem->Active = '<div class="isinactive">' . $this->Translate('Inactive') . '</div>';
            }

            $TableData[] = (array) $xmlItem;
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
        return [];
        //todo
        $HostVariables = array_column(json_decode($this->ReadPropertyString('HostVariables'), true), 'use', 'ident');
        if (!$this->HasActiveParent()) {
            return [];
        }
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
            return [];
        }
        $xml = new \simpleXMLElement($XMLData);
        if ($xml === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return [];
        }
        $this->SendDebug('XML', $XMLData, 0);
        $KnownVariableIDs = array_filter(IPS_GetChildrenIDs($this->InstanceID), function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if ((substr($Ident, 0, 2) == 'IP') || (substr($Ident, 0, 3) == 'MAC')) {
                return true;
            }
            return false;
        });
        $TableData = [];
        foreach ($xml as $xmlItem) {
            if ((string) $xmlItem->MACAddress == '') {
                $Address = (string) $xmlItem->IPAddress;
                $Ident = 'IP' . strtoupper($this->ConvertIdent((string) $xmlItem->IPAddress));
            } else {
                $Address = (string) $xmlItem->MACAddress;
                $Ident = 'MAC' . strtoupper($this->ConvertIdent((string) $xmlItem->MACAddress));
            }
            if (array_key_exists($Ident, $HostVariables)) {
                $Used = $HostVariables[$Ident];
            } else {
                $Used = false;
            }
            $VariableID = @$this->GetIDForIdent($Ident);
            if ($VariableID > 0) {
                $Key = array_search($VariableID, $KnownVariableIDs);
                $Name = IPS_GetName($VariableID);
                unset($KnownVariableIDs[$Key]);
                $RowColor = ($Name != (string) $xmlItem->HostName) ? '#DFDFDF' : '#FFFFFF';
            } else {
                $Name = (string) $xmlItem->HostName;
                $RowColor = '#C0FFC0';
            }
            $TableData[] = [
                'ident'   => $Ident,
                'address' => $Address,
                'name'    => $Name,
                'rowColor'=> $RowColor,
                'use'     => $Used
            ];
        }
        foreach ($KnownVariableIDs as $VariableID) {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if (array_key_exists($Ident, $HostVariables)) {
                $Used = $HostVariables[$Ident];
            } else {
                $Used = false;
            }
            $TableData[] = [
                'ident'   => $Ident,
                'address' => '',
                'name'    => IPS_GetName($VariableID),
                'rowColor'=> '#FFC0C0',
                'use'     => $Used
            ];
        }
        return $TableData;
    }
    private function CreateHostHTMLTable(array $TableData)
    {
        $HTML = $this->GetTable($TableData);
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
