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
    protected static $SecondEventGUID ='{FE6C73CB-028B-F569-46AC-3C02FF1F8F2F}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->HostNumberOfEntriesId=0;
        $this->RegisterPropertyInteger('Index', 0);
        $this->RegisterPropertyBoolean('HostAsVariable', false);
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
        $this->SetTimerInterval('RefreshHosts', 0);
        $this->HostNumberOfEntriesId = $this->RegisterVariableInteger('HostNumberOfEntries', $this->Translate('Number of hosts'), '', -2);
        $this->SetTimerInterval('RefreshHosts', 0);
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        if ($Table) {
            $this->RegisterVariableString('HostTable', $this->Translate('Host table'), '~HTMLBox', -3);
        } else {
            $this->UnregisterVariable('HostTable');
        }
        
        if (!($Variable || ($Table))) {
            $this->SetStatus(IS_INACTIVE);
            $this->UnregisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
            return;
        }
        $this->RegisterMessage($this->HostNumberOfEntriesId, VM_UPDATE);
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshHosts', $this->ReadPropertyInteger('RefreshInterval')*1000);
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
        if ($Ident == 'RefreshHosts') {
            return $this->UpdateHostNumberOfEntries();
        }
        if (strpos($Ident, 'MAC')===0) {
            if ($Value===true) {
                $MACAddress = implode(':', str_split(substr($Ident, 3), 2));
                $this->WakeOnLANByMACAddress($MACAddress);
            }
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
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
        $Table = $this->ReadPropertyBoolean('HostAsTable');
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        if (!($Variable || ($Table))) {
            return true;
        }
        if ($this->ParentID == 0) {
            return false;
        }
            
        $File = $this->GetHostListPath();
        if ($File === false) {
            return false;
        }
        
        if (!$this->LoadAndSaveFile($File, 'Hosts.xml')) {
            return false;
        }
        
        $XMLData = $this->GetFile('Hosts.xml');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts.xml', 0);
            return false;
        }
        $xml = new simpleXMLElement($XMLData);
        if ($xml === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return false;
        }
        $OnlineCounter=0;
        $TableData=[];
        $pos=0;
        foreach ($xml as $xmlItem) {
            $this->SendDebug('XML xmlItem', (array)$xmlItem, 0);
            if ((string)$xmlItem->MACAddress == '') {
                $Ident = 'IP'.strtoupper($this->ConvertIdent((string)$xmlItem->IPAddress));
                $Action = false;
            } else {
                $Ident = 'MAC'.strtoupper($this->ConvertIdent((string)$xmlItem->MACAddress));
                $Action = true;
            }
            if ($Variable) {
                $VarId= @$this->GetIDForIdent($Ident);
                $this->setIPSVariable($Ident, (string)$xmlItem->HostName, (int)$xmlItem->Active==1, VARIABLETYPE_BOOLEAN, '~Switch', $Action, ++$pos);
                if ($VarId == 0) {
                    $VarId= $this->GetIDForIdent($Ident);
                    IPS_SetVariableCustomAction($VarId, 1);
                } else {
                    if ($Rename) {
                        if (IPS_GetName($VarId) != (string)$xmlItem->HostName) {
                            IPS_SetName($VarId, (string)$xmlItem->HostName);
                        }
                    }
                }
            }
            if ((bool)$xmlItem->Active) {
                $OnlineCounter++;
            }
            $TableData[] = (array)$xmlItem;
        }
        $this->setIPSVariable('HostNumberActive', 'Number of active hosts', $OnlineCounter, VARIABLETYPE_INTEGER, '', false, -1);
        $this->CreateHostHTMLTable($TableData);
        return true;
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
        $this->setIPSVariable('HostNumberOfEntries', 'Number of hosts', (int)$result, VARIABLETYPE_INTEGER, '', false, -2);
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
                'key'   => 'HostName',
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
                'key'   => 'InterfaceType',
                'name'  => $this->Translate('Interface'),
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
