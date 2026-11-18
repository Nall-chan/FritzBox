<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * Host Filter: Internetzugang je Netzwerkgerät sperren, Zugangsprofile und Online-Tickets.
 * Die Instanz-Kachel (HTML-SDK) zeigt alle Geräte mit Sperre; Zugangsprofil und Ticket je Gerät auf Klick
 * (die FritzBox liefert das Profil nur einzeln je Gerät).
 *
 * @property bool $ShowVariableWarning
 * @property array $TileHosts Geräte für die Kachel (letzter Abruf)
 */
class FritzBoxHostFilter extends FritzBoxModulBase
{
    use \FritzBoxModul\Tile;

    protected static $ControlUrlArray = [
        '/upnp/control/x_hostfilter'
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_HostFilter:1'
    ];
    protected static $SecondEventGUID = \FritzBox\GUID::NewHostListEvent;
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 60);
        $this->RegisterPropertyBoolean(\FritzBox\HostFilter\Property::HostAsVariable, false);
        $this->RegisterPropertyBoolean(\FritzBox\HostFilter\Property::AutoAddHostVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\HostFilter\Property::RenameHostVariables, false);
        $this->RegisterPropertyString(\FritzBox\HostFilter\Property::HostVariables, '[]');
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, 0);
        $this->TileHosts = [];
        parent::ApplyChanges();
        $this->RegisterTile();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        $HostVariables = json_decode($this->ReadPropertyString(\FritzBox\HostFilter\Property::HostVariables), true);
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

    public function GetConfigurationForm(): string
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
        if (!$this->ReadPropertyBoolean(\FritzBox\HostFilter\Property::HostAsVariable)) {
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
            $Values = json_decode($this->ReadPropertyString(\FritzBox\HostFilter\Property::HostVariables), true);
        }
        $Form['elements'][1]['items'][1]['items'][0]['values'] = $Values;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function RefreshHostList(): bool
    {
        if ($this->ParentID == 0) {
            return false;
        }
        $Variable = $this->ReadPropertyBoolean(\FritzBox\HostFilter\Property::HostAsVariable);
        $Rename = $this->ReadPropertyBoolean(\FritzBox\HostFilter\Property::RenameHostVariables);
        $AutoAdd = $this->ReadPropertyBoolean(\FritzBox\HostFilter\Property::AutoAddHostVariables);
        // Die Geräteliste wird auch ohne Variablen gelesen, die Kachel zeigt sie
        $XMLData = $this->GetFile('Hosts');
        if ($XMLData === false) {
            $this->SendDebug('XML not found', 'Hosts', 0);
            return false;
        }
        try {
            $xmlHosts = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
            return false;
        }
        $HostVariables = array_column(json_decode($this->ReadPropertyString(\FritzBox\HostFilter\Property::HostVariables), true), 'use', 'ident');
        $KnownHostNames = [];
        $TileHosts = [];
        foreach ($xmlHosts as $Host) {
            if ((string) $Host->IPAddress == '') {
                continue;
            }
            $Ident = 'IP' . str_replace('.', '_', (string) $Host->IPAddress);
            $KnownHostNames[$Ident] = [
                'Hostname' => (string) $Host->HostName,
                'Disallow' => ((int) $Host->{'X_AVM-DE_Disallow'} == 1) ? true : false
            ];
            $TileHosts[] = [
                'name'     => (string) $Host->HostName,
                'ip'       => (string) $Host->IPAddress,
                'active'   => (int) $Host->Active == 1,
                'disallow' => (int) $Host->{'X_AVM-DE_Disallow'} == 1,
                'guest'    => (int) $Host->{'X_AVM-DE_Guest'} == 1,
                // Online-Ticket möglich (Angabe der FritzBox; live 0 auch bei Profil mit Zeitplan, solange nicht gesperrt)
                'ticket'   => (int) $Host->{'X_AVM-DE_AddTicketAvailable'} == 1
            ];
        }
        $this->TileHosts = $TileHosts;
        $this->PushTile();
        if (!$Variable) {
            return true;
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

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        if ($data['Function'] == \FritzBox\IOFunction::NewHostListEvent) {
            $this->SendDebug('NewHostListEvent', '', 0);
            $this->RefreshHostList();
        }
        return '';
    }

    public function MarkTicket(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetTicketIDStatus(int $TicketID): mixed
    {
        $result = $this->Send(__FUNCTION__, [
            'NewTicketID'=> $TicketID
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function DiscardAllTickets(): bool
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return true;
    }

    public function DisallowWANAccessByIP(string $IPv4Address, bool $Disallow): bool
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

    public function GetWANAccessByIP(string $IPv4Address): mixed
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    /**
     * Liefert die Zugangsprofile der FritzBox.
     *
     * @return string|false Liste der Profile, wie von der FritzBox geliefert
     */
    public function GetFilterProfiles(): mixed
    {
        return $this->Send(__FUNCTION__);
    }

    /**
     * Liefert ein Zugangsprofil.
     *
     * @param string $FilterProfileID ID des Profils, wie von GetFilterProfiles geliefert
     *
     * @return string|false
     */
    public function GetFilterProfileByID(string $FilterProfileID): mixed
    {
        if ($FilterProfileID === '') {
            $this->ReportError($this->Translate('Invalid profile ID.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewFilterProfileID'=> $FilterProfileID
        ]);
    }

    /**
     * Liefert Hostname, Zugangsprofil, Online-Zeit und Tickets eines Netzwerkgerätes.
     *
     * @param string $IPv4Address IPv4-Adresse des Geräts
     *
     * @return array|false
     */
    public function GetHostEntryByIP(string $IPv4Address): mixed
    {
        if (!filter_var($IPv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->ReportError($this->Translate('Invalid IP address.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address
        ]);
    }

    /**
     * Weist ein Netzwerkgerät einem Zugangsprofil zu.
     *
     * @param string $IPv4Address     IPv4-Adresse des Geräts
     * @param string $FilterProfileID ID des Profils, wie von GetFilterProfiles geliefert
     *
     * @return bool
     */
    public function AddHostEntryToFilterProfile(string $IPv4Address, string $FilterProfileID): bool
    {
        if (!filter_var($IPv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->ReportError($this->Translate('Invalid IP address.'));
            return false;
        }
        if ($FilterProfileID === '') {
            $this->ReportError($this->Translate('Invalid profile ID.'));
            return false;
        }
        $result = $this->Send(__FUNCTION__, [
            'NewIPv4Address'    => $IPv4Address,
            'NewFilterProfileID'=> $FilterProfileID
        ]);
        return $result !== false;
    }

    /**
     * Fügt einem Netzwerkgerät ein Online-Ticket (zusätzliche Online-Zeit) hinzu.
     *
     * @param string $IPv4Address IPv4-Adresse des Geräts
     *
     * @return array|false
     */
    public function AddTicketTimeToHostEntryByIP(string $IPv4Address): mixed
    {
        if (!filter_var($IPv4Address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $this->ReportError($this->Translate('Invalid IP address.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewIPv4Address'=> $IPv4Address
        ]);
    }

    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if (parent::ProcessRequestAction($Ident, $Value)) {
            return true;
        }
        if ($this->ProcessTileAction($Ident, $Value)) {
            return true;
        }

        switch ($Ident) {
            case \FritzBox\Tile\Action::Disallow:
                $Data = json_decode((string) $Value, true);
                if (!is_array($Data) || !$this->DisallowWANAccessByIP((string) ($Data['ip'] ?? ''), (bool) ($Data['value'] ?? false))) {
                    return false;
                }
                $VariableIdent = 'IP' . str_replace('.', '_', (string) $Data['ip']);
                if (@$this->GetIDForIdent($VariableIdent) > 0) {
                    $this->SetValue($VariableIdent, (bool) $Data['value']);
                }
                $this->SetTileHostDisallow((string) $Data['ip'], (bool) $Data['value']);
                return true;
            case \FritzBox\Tile\Action::HostEntry:
                return $this->PushTileHostEntry((string) $Value);
            case \FritzBox\Tile\Action::FilterProfile:
                $Data = json_decode((string) $Value, true);
                if (!is_array($Data) || !$this->AddHostEntryToFilterProfile((string) ($Data['ip'] ?? ''), (string) ($Data['profile'] ?? ''))) {
                    return false;
                }
                // Prüfen, ob die FritzBox das Profil übernommen hat (Standard wird bei Geräten außerhalb des Gastnetzes
                // als Gast-Profil gemeldet, siehe TileProfiles)
                $Entry = $this->GetHostEntryByIP((string) $Data['ip']);
                if (is_array($Entry)) {
                    $Tile = $this->TileProfiles((string) $Data['ip'], (string) ($Entry['NewFilterProfileID'] ?? ''));
                    if ($Tile['profile'] != (string) $Data['profile']) {
                        $this->PushTileNotice($this->Translate('The FritzBox did not apply the access profile.'));
                    }
                }
                return $this->PushTileHostEntry((string) $Data['ip']);
            case \FritzBox\Tile\Action::Ticket:
                if ($this->AddTicketTimeToHostEntryByIP((string) $Value) === false) {
                    return false;
                }
                // Rückmeldung: Online-Zeit und Budget ändern sich durch ein Ticket nicht sichtbar (TimeMax ohne Ticket-Zeit)
                $this->PushTileNotice($this->Translate('Online ticket added.'));
                return $this->PushTileHostEntry((string) $Value);
            case \FritzBox\HostFilter\Action::ReloadForm:
                usleep((2000) * 1000);
                $this->ReloadForm();
                return true;
            case \FritzBox\Base\Action::RefreshState:
                return $this->UpdateInfo();
            case \FritzBox\HostFilter\Action::HostAsVariable:
                $this->UpdateFormField('AutoAddHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('RenameHostVariables', 'enabled', (bool) $Value);
                $this->UpdateFormField('HostvariablesPanel', 'expanded', (bool) $Value);
                $this->UpdateFormField('HostVariables', 'enabled', (bool) $Value);
                return true;
            case \FritzBox\HostFilter\Action::HostvariablesPanel:
                if ($this->ShowVariableWarning) {
                    $this->UpdateFormField('ErrorPopup', 'visible', true);
                    $this->UpdateFormField('ErrorTitle', 'caption', 'Attention!');
                    $this->UpdateFormField('ErrorText', 'caption', 'Deselecting a host causes the associated status variable to be deleted.');
                    $this->ShowVariableWarning = false;
                }
                return true;
            case \FritzBox\HostFilter\Action::DelHostVariable:
                $ObjectId = @$this->GetIDForIdent($Value);
                if ($ObjectId > 0) {
                    $this->DelSubObjects($ObjectId);
                    $this->UnregisterVariable($Value);
                }
                return true;
        }
        if (strpos($Ident, 'IP') === 0) {
            $IPAddress = str_replace('_', '.', substr($Ident, 2));
            if ($this->DisallowWANAccessByIP($IPAddress, (bool) $Value)) {
                $this->SetValue($Ident, $Value);
                $this->SetTileHostDisallow($IPAddress, (bool) $Value);
                return true;
            } else {
                return false;
            }
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: alle Geräte mit IPv4-Adresse und ihre Sperre.
     */
    protected function GetTileData(): array
    {
        return ['hosts' => $this->TileHosts];
    }

    protected function GetTileTextKeys(): array
    {
        return [
            'Internet access', 'Blocked', 'Allowed', 'Blocked devices', 'Search', 'No devices', 'Access profile', 'Online ticket', 'Add ticket',
            'Online time today', 'min', 'Details', 'Loading…', 'Remaining', 'Budget', 'Ticket'
        ];
    }

    /**
     * Sperre eines Gerätes in der Liste der Kachel nachführen und senden.
     */
    private function SetTileHostDisallow(string $IPAddress, bool $Disallow): void
    {
        $Hosts = $this->TileHosts;
        foreach ($Hosts as &$Host) {
            if ($Host['ip'] == $IPAddress) {
                $Host['disallow'] = $Disallow;
            }
        }
        $this->TileHosts = $Hosts;
        $this->PushTile();
    }

    /**
     * Zugangsprofil, Online-Zeit und Tickets eines Gerätes sowie die Liste der Profile an die Kachel senden.
     */
    private function PushTileHostEntry(string $IPAddress): bool
    {
        $Entry = $this->GetHostEntryByIP($IPAddress);
        if ($Entry === false) {
            return false;
        }
        $Tile = $this->TileProfiles($IPAddress, (string) ($Entry['NewFilterProfileID'] ?? ''));
        $this->UpdateVisualizationValue(json_encode([
            'type'     => 'hostEntry',
            'ip'       => $IPAddress,
            'profile'  => $Tile['profile'],
            'wan'      => (string) ($Entry['NewWANAccess'] ?? ''),
            'timeUsed' => (int) ($Entry['NewTimeUsed'] ?? 0),
            'timeMax'  => (int) ($Entry['NewTimeMax'] ?? 0),
            'tickets'  => (int) ($Entry['NewTicketsInAdvance'] ?? 0),
            // Restminuten des laufenden Online-Tickets (zählt herunter, live geprüft; ein Ticket = 45 min)
            'ticketValid' => (int) ($Entry['NewTicketValid'] ?? 0),
            'ticket'      => $Tile['ticket'],
            'profiles'    => $Tile['profiles']
        ], self::$TileJsonFlags));
        return true;
    }

    /**
     * Zugangsprofile für die Kachel, wie sie die Oberfläche der FritzBox zeigt.
     *
     * Live an einer 6690 (FRITZ!OS 8.x) beobachtet, nicht von AVM dokumentiert: Für Geräte außerhalb des Gastnetzes
     * lehnt die FritzBox das Gast-Profil mit Fehler 820 ab; `Standard` nimmt sie an, meldet danach aber die ID des
     * Gast-Profils (die Oberfläche zeigt dann „Standard“). Für diese Geräte daher kein Gast-Profil in der Auswahl und
     * die Gast-ID als Standard anzeigen. Profile ohne Namen (z. B. Typ Unrestricted) erhalten den übersetzten Typ.
     *
     * @param string $IPAddress IP-Adresse des Gerätes.
     * @param string $ProfileID Profil des Gerätes, wie von GetHostEntryByIP geliefert.
     *
     * Ein Online-Ticket (zusätzliche Online-Zeit) bietet die Kachel nur an, wenn die FritzBox es für das Gerät in der
     * Geräteliste freigibt (`X_AVM-DE_AddTicketAvailable`); sonst antwortet sie mit Fehler 820 (live: auch bei einem
     * Profil mit Zeitplan, solange das Gerät nicht gesperrt ist).
     *
     * @return array ['profile' => anzuzeigende Profil-ID, 'profiles' => [['id', 'name'], …], 'ticket' => bool]
     */
    private function TileProfiles(string $IPAddress, string $ProfileID): array
    {
        $Hosts = array_column($this->TileHosts, null, 'ip');
        $IsGuest = (bool) ($Hosts[$IPAddress]['guest'] ?? false);
        $Profiles = [];
        $GuestIDs = [];
        $StandardID = '';
        $Ticket = (bool) ($Hosts[$IPAddress]['ticket'] ?? false);
        $XML = $this->GetFilterProfiles();
        if (is_string($XML) && ($XML != '')) {
            try {
                $TypeNames = ['Unrestricted' => 'Unrestricted', 'Standard' => 'Standard', 'Guest' => 'Guest'];
                foreach ((new \SimpleXMLElement($XML))->FilterProfile as $Profile) {
                    $ID = (string) $Profile->FilterProfileID;
                    $Type = (string) $Profile->FilterProfileType;
                    if ($Type == 'Guest') {
                        $GuestIDs[] = $ID;
                        if (!$IsGuest) {
                            continue;
                        }
                    }
                    if ($Type == 'Standard') {
                        $StandardID = $ID;
                    }
                    $Name = trim((string) $Profile->Name);
                    if ($Name == '') {
                        $Name = isset($TypeNames[$Type]) ? $this->Translate($TypeNames[$Type]) : $ID;
                    }
                    $Profiles[] = ['id' => $ID, 'name' => $Name];
                }
            } catch (\Throwable $th) {
                $this->SendDebug('FilterProfiles', $th->getMessage(), 0);
            }
        }
        if (!$IsGuest && ($StandardID != '') && in_array($ProfileID, $GuestIDs, true)) {
            $ProfileID = $StandardID;
        }
        return ['profile' => $ProfileID, 'profiles' => $Profiles, 'ticket' => $Ticket];
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
        try {
            $xmlHosts = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
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
        $HostVariables = json_decode($this->ReadPropertyString(\FritzBox\HostFilter\Property::HostVariables), true);
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
