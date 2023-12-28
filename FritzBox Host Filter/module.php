<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';

/**
 * @property bool $ShowVariableWarning
 */
class FritzBoxHostFilter extends FritzBoxModulBase
{
    protected static $ControlUrlArray = [
        '/upnp/control/x_hostfilter'
    ];
    protected static $EventSubURLArray = [
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_HostFilter:1'
    ];
    protected static $SecondEventGUID = \FritzBox\GUID::NewHostListEvent;
    protected static $DefaultIndex = 0;
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('RefreshInterval', 60);
        $this->RegisterPropertyBoolean('HostAsVariable', false);
        $this->RegisterPropertyBoolean('AutoAddHostVariables', true);
        $this->RegisterPropertyBoolean('RenameHostVariables', false);
        $this->RegisterPropertyString('HostVariables', '[]');
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshState', 0);
        parent::ApplyChanges();
        $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
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

        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->RefreshHostList();
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
        if (strpos($Ident, 'IP') === 0) {
            $IPAddress = str_replace('_', '.', substr($Ident, 2));
            if ($this->DisallowWANAccessByIP($IPAddress, (bool) $Value)) {
                $this->SetValue($Ident, $Value);
                return true;
            } else {
                return false;
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
        if (!$this->GetFile('Hosts')) {
            $Form['actions'][2]['visible'] = true;
            $Form['actions'][2]['popup']['items'][0]['caption'] = 'Hostnames not available!';
            $Form['actions'][2]['popup']['items'][1]['caption'] = 'The \'FritzBox Host\' instance is required to display hostnames.';
            $Form['actions'][2]['popup']['items'][1]['width'] = '200px';
            $ConfiguratorID = $this->GetConfiguratorID();
            if ($ConfiguratorID > 1) {
                $Form['actions'][2]['popup']['items'][2]['caption'] = 'Open Configurator';
                $Form['actions'][2]['popup']['items'][2]['visible'] = true;
                $Form['actions'][2]['popup']['items'][2]['objectID'] = $ConfiguratorID;
            }
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

    public function RefreshHostList()
    {
        if ($this->ParentID == 0) {
            return false;
        }
        $Variable = $this->ReadPropertyBoolean('HostAsVariable');
        $Rename = $this->ReadPropertyBoolean('RenameHostVariables');
        $AutoAdd = $this->ReadPropertyBoolean('AutoAddHostVariables');
        if (!$Variable) {
            return false;
        }
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
            return false;
        }
        $xmlHosts = new \simpleXMLElement($XMLData);
        if ($xmlHosts === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return false;
        }
        $HostVariables = array_column(json_decode($this->ReadPropertyString('HostVariables'), true), 'use', 'ident');
        foreach ($xmlHosts as $Host) {
            if ((string) $Host->IPAddress == '') {
                continue;
            }
            $Ident = 'IP' . str_replace('.', '_', (string) $Host->IPAddress);
            $KnownHostNames[$Ident] = [
                'Hostname' => (string) $Host->HostName,
                'Disallow' => ((int) $Host->{'X_AVM-DE_Disallow'} == 1) ? true : false
            ];
        }
        foreach ($KnownHostNames as $Ident => $HostData) {
            if (array_key_exists($Ident, $HostVariables)) {
                if (!$HostVariables[$Ident]) {
                    $VarId = @$this->GetIDForIdent($Ident);
                    if ($VarId > 0) {
                        $this->DelSubObjects($VarId);
                        $this->UnregisterVariable($Ident);
                    }
                    continue;
                }
            } else {
                if (!$AutoAdd) {
                    continue;
                }
            }
            $this->setIPSVariable($Ident, $HostData['Hostname'], $HostData['Disallow'], VARIABLETYPE_BOOLEAN, '~Switch', true);
            if ($Rename) {
                $VarId = @$this->GetIDForIdent($Ident);
                if ($HostData['Hostname'] != IPS_GetName($VarId)) {
                    IPS_SetName($VarId, $HostData['Hostname']);
                }
            }
        }
        return true;
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
    public function MarkTicket()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetTicketIDStatus(int $TicketID)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewTicketID'=> $TicketID
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function DiscardAllTickets()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function DisallowWANAccessByIP(string $IPv4Address, bool $Disallow)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address,
            'NewDisallow'   => (int) $Disallow
        ]);
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetWANAccessByIP(string $IPv4Address)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    private function UpdateInfo()
    {
        $this->RefreshHostXML();
    }
    private function GetHostVariables(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
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
            $Ident = 'IP' . str_replace('.', '_', (string) $Host->IPAddress);
            $KnownHostNames[$Ident] = (string) $Host->HostName;
        }
        $KnownVariableIDs = array_filter(IPS_GetChildrenIDs($this->InstanceID), function ($VariableID)
        {
            $Ident = IPS_GetObject($VariableID)['ObjectIdent'];
            if (substr($Ident, 0, 2) == 'IP') {
                return true;
            }
            return false;
        });
        // Konfigurierte Statusvariablen für Hosts
        $HostVariables = json_decode($this->ReadPropertyString('HostVariables'), true);
        // Property durchgehen und Werte ergänzen. Alle Idents merken
        $FoundIdents = array_column($HostVariables, 'ident');
        foreach ($HostVariables as &$HostVariable) {
            $HostVariable['address'] = str_replace('_', '.', substr($HostVariable['ident'], 2));
            if (array_key_exists($HostVariable['ident'], $KnownHostNames)) {
                $HostVariable['host'] = $KnownHostNames[$HostVariable['ident']];
            } else {
                $HostVariable['host'] = '';
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
            if (array_key_exists($HostVariable['ident'], $KnownHostNames)) {
                if (!$HostVariable['use']) {
                    $HostVariable['rowColor'] = '#C0FFC0';
                }
            } else {
                $HostVariable['host'] = $this->Translate('invalid');
                $HostVariable['rowColor'] = '#FFC0C0';
            }
        }
        // restliche Objekte aus HOST immer anhängen
        foreach ($xmlHosts as $Host) {
            if ((string) $Host->IPAddress == '') {
                continue;
            }
            $Address = (string) $Host->IPAddress;
            $Ident = 'IP' . str_replace('.', '_', $Address);
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
            $Address = str_replace('_', '.', substr($Ident, 2));
            $Name = IPS_GetName($VariableID);
            if (array_key_exists($Ident, $KnownHostNames)) {
                $Host = $KnownHostNames[$Ident];
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
}
