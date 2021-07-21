<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTelHelper.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
//require_once __DIR__ . '/../libs/helper/WebhookHelper.php';

class FritzBoxTelephony extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;
    use \FritzBoxModul\TelHelper;

    const Call_Incoming = 1;
    const Call_Missed = 2;
    const Call_Outgoing = 3;
    //const Call_Tam_New=4;
    //const Call_Tam_Old = 5;
    //const Call_Tam_Deleted = 6;
    const Call_Fax = 7;
    const Call_Active_Incoming = 9;
    const Call_Rejected_Incoming = 10;
    const Call_Active_Outgoing = 11;
    const FoundMarker = 20;
    //use \WebhookHelper;

    protected static $ControlUrlArray = [
        '/upnp/control/x_contact'
    ];
    protected static $EventSubURLArray = [

    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'
    ];

    protected static $SecondEventGUID = '{FE5B2BCA-CA0F-25DC-8E79-BDFD242CB06E}';

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
        $this->RegisterPropertyInteger('RefreshIntervalPhonebook', 60);
        $this->RegisterPropertyInteger('RefreshIntervalDeflectionList', 60);
        $this->RegisterPropertyInteger('RefreshIntervalCallList', 10);
        $this->RegisterPropertyBoolean('DeflectionAsVariable', false);
        $this->RegisterPropertyBoolean('CallBarringAsVariable', false);
        $this->RegisterTimer('RefreshPhonebook', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshPhonebook",true);');
        $this->RegisterTimer('RefreshCallList', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshCallList",true);');
        $this->RegisterTimer('RefreshDeflectionList', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshDeflectionList",true);');
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterPropertyString('Icons', json_encode($Style['Icons']));

        $this->RegisterPropertyInteger('ReverseSearchInstanceID', 0);
        $this->RegisterPropertyInteger('CustomSearchScriptID', 0);

        $this->RegisterPropertyInteger('LoadListType', 2);
        $this->RegisterPropertyInteger('LastEntries', 20);

        $this->RegisterPropertyInteger('MaxNameSize', 30);
        $this->RegisterPropertyString('SearchMarker', '{ICON}');
        $this->RegisterPropertyString('UnknownNumberName', $this->Translate('(unknown)'));
        $this->RegisterPropertyBoolean('NotShowWarning', false);
        //todo
        // HTML Box abwählbar

        //DatumMaskieren für gestern / heute (def an)
        // Timer für 55sek nach 0Uhr

        // NR Filter (Liste?, add über auswahl der Nr + Name?)
        // Nr für Fax (kann das abgefragt werden?)
        // AB  / TAM?!?! über Auswahl TAM Instanz -> SelectInstance
        // Filter / Gesprächstypen (Liste)
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
        $this->SetTimerInterval('RefreshPhonebook', $this->ReadPropertyInteger('RefreshIntervalPhonebook') * 60000);
        $this->SetTimerInterval('RefreshCallList', $this->ReadPropertyInteger('RefreshIntervalCallList') * 60000);

        if ($this->ReadPropertyBoolean('DeflectionAsVariable') || $this->ReadPropertyBoolean('CallBarringAsVariable')) {
            $this->SetTimerInterval('RefreshDeflectionList', $this->ReadPropertyInteger('RefreshIntervalDeflectionList') * 60000);
        } else {
            $this->SetTimerInterval('RefreshDeflectionList', 0);
        }

        /*$this->GetDECTHandsetList();
        $this->GetDECTHandsetInfo(1);
        $this->GetDECTHandsetInfo(2);*/
        //$this->GetNumberOfDeflections();
        // GetDeflection
        //$this->GetDeflections();
        // SetDeflectionEnable
        //$this->RegisterHook('/hook/FritzBoxCallList' . $this->InstanceID);
        $this->RegisterVariableString('CallList', $this->Translate('Call list'), '~HTMLBox', -1);
        $this->RefreshCallList();
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshPhonebook':
                return $this->RefreshPhonebook();
            case 'RefreshCallList':
                return $this->RefreshCallList();
            case 'RefreshDeflectionList':
                return $this->RefreshDeflectionList();
            case 'PreviewIcon':
                $Data = unserialize($Value);
                $ImageData = @getimagesize('data://text/plain;base64,' . $Data['Icon']);
                    if ($ImageData === false) {
                        $this->UpdateFormField('IconName', 'caption', 'No valid image');
                        $this->UpdateFormField('IconPreview', 'visible', true);
                        return;
                    }
                $this->UpdateFormField('IconName', 'caption', $Data['DisplayName']);
                $this->UpdateFormField('IconImage', 'image', 'data://' . $ImageData['mime'] . ';base64,' . $Data['Icon']);
                $this->UpdateFormField('IconPreview', 'visible', true);
                return;
            case 'ReverseSearchInstanceID':
                $this->SendDebug('ReverseSearchInstanceID', $Value, 0);
                if ($Value > 0) {
                    $this->UpdateFormField('CustomSearchScriptID', 'enabled', false);
                } else {
                    $this->UpdateFormField('CustomSearchScriptID', 'enabled', true);
                }
                return;
            case 'CustomSearchScriptID':
                $this->SendDebug('CustomSearchScriptID', $Value, 0);
                if ($Value > 0) {
                    $this->UpdateFormField('MaxNameSize', 'enabled', false);
                    $this->UpdateFormField('ReverseSearchInstanceID', 'enabled', false);
                    $this->UpdateFormField('SearchMarker', 'enabled', false);
                    $this->UpdateFormField('UnknownNumberName', 'enabled', false);
                } else {
                    $this->UpdateFormField('MaxNameSize', 'enabled', true);
                    $this->UpdateFormField('ReverseSearchInstanceID', 'enabled', true);
                    $this->UpdateFormField('SearchMarker', 'enabled', true);
                    $this->UpdateFormField('UnknownNumberName', 'enabled', true);
                }
                return;
        }

        $IdentData = explode('_', $Ident);
        if ((count($IdentData) == 4) && (($IdentData[0] == 'D') || ($IdentData[0] == 'C'))) {
            return $this->RefreshDeflectionList($Ident, $Value);
        }

        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!IPS_LibraryExists('{D0E8905A-F00C-EA84-D607-3D27000348D8}')) {
            if (!$this->ReadPropertyBoolean('NotShowWarning')) {
                $Form['elements'][4]['visible'] = true;
            }
        }
        if ($this->ReadPropertyInteger('CustomSearchScriptID') > 0) {
            $Form['elements'][0]['items'][1]['expanded'] = false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][0]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][0]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][1]['enabled'] = false;
        }
        if ($this->ReadPropertyInteger('ReverseSearchInstanceID') > 0) {
            $Form['elements'][1]['items'][1]['enabled'] = false;
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
    public function GetCallList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetPhonebookList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetPhonebook(int $PhonebookID)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookID'=> $PhonebookID
            ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetPhonebookEntry(int $PhonebookID, int $PhonebookEntryID)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookID'     => $PhonebookID,
                'NewPhonebookEntryID'=> $PhonebookEntryID
            ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetPhonebookEntryUID(int $PhonebookID, int $PhonebookEntryUniqueID)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookID'           => $PhonebookID,
                'NewPhonebookEntryUniqueID'=> $PhonebookEntryUniqueID
            ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetDECTHandsetList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetDECTHandsetInfo(int $DectID)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewDectID'=> $DectID
            ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetNumberOfDeflections()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetDeflections()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetDeflection(int $DeflectionId)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewDeflectionId'=> $DeflectionId
            ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetDeflectionEnable(int $DeflectionId, bool $Enable)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewDeflectionId' => $DeflectionId,
                'NewEnable'       => (int) $Enable
            ]
        );
        if ($result === false) {
            return false;
        }
        return true;
    }
    public function GetCallBarringList()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $this->LoadAndGetData($result);
        //return $result;
    }
    public function GetCallBarringEntry(int $PhonebookEntryID)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookEntryID'=> $PhonebookEntryID
            ]
            );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function GetCallBarringEntryByNum(string $Number)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewNumber'=> $Number
            ]
            );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetCallBarringEntry(string $PhonebookEntryData)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookEntryData'=> $PhonebookEntryData
            ]
            );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function DeleteCallBarringEntryUID(string $PhonebookEntryData)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
                'NewPhonebookEntryData'=> $PhonebookEntryData
            ]
            );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        $this->SendDebug('ReceiveCallMonitorData', $data, 0);
        $CallEvent = explode(';', utf8_decode($data['Buffer']));
        switch ($CallEvent[1]) {
            case 'CONNECT': // Verbunden
            case 'DISCONNECT': // Getrennt
                IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',"RefreshCallList",true);');
            break;
        }
        return true;
    }
    /**
     * Verarbeitet Daten aus dem Webhook.
     *
     * @global array $_GET
     */
    protected function ProcessHookdata()
    {
        $this->SendDebug('Server', $_SERVER['HOOK'], 0);
        if ($_SERVER['SCRIPT_NAME'] == '/hook/FritzBoxCallList' . $this->InstanceID . '/tooltip.js') {
            $this->ServeFile(__DIR__ . '/../libs/wz_tooltip.js');
            return;
        }
        $this->SendDebug('GET', $_GET, 0);
        $this->SendDebug('Server', $_SERVER['HOOK'], 0);
        $this->SendDebug('Request', $_REQUEST, 0);
        $this->SendDebug('Files', $_FILES, 0);
        return;
        /*
        //Todo
        if ((!isset($_GET['Type'])) || (!isset($_GET['Secret']))) {
            echo $this->Translate('Bad Request');

            return;
        }

        $CalcSecret = base64_encode(sha1($this->WebHookSecret . '0' . (string) $_GET['ID'], true));
        if ($CalcSecret != rawurldecode($_GET['Secret'])) {
            echo $this->Translate('Access denied');
            return;
        }
        if ($_GET['Type'] != 'Index') {
            echo $this->Translate('Bad Request');

            return;
        }

        if ($this->SelectInfoListItem((int) $_GET['ID'])) {
            echo 'OK';
        }*/
    }
    private function RefreshCallList()
    {
        $this->SendDebug('RefreshCallList', 'start', 0);
        $result = $this->GetCallList();
        if ($result === false) {
            return false;
        }
        $entries = $this->ReadPropertyInteger('LastEntries');
        switch ($this->ReadPropertyInteger('LoadListType')) {
            case -1:
                return true;
            case 1:
                $result .= '&days=' . $entries;
                break;
            case 2:
                $result .= '&max=' . $entries;
                break;
        }
        $XMLData = $this->LoadAndGetData($result);
        if ($XMLData === false) {
            return false;
        }
        $CallList = new simpleXMLElement($XMLData);
        if ($CallList === false) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            return false;
        }
        $PhoneDevices = $this->GetPhoneDevices();
        $Data = [];
        $UnknownName = $this->ReadPropertyString('UnknownNumberName');
        $ReverseSearchInstanceID = $this->ReadPropertyInteger('ReverseSearchInstanceID');
        $CustomSearchScriptID = $this->ReadPropertyInteger('CustomSearchScriptID');
        $MaxNameSize = $this->ReadPropertyInteger('MaxNameSize');
        $SearchMarker = $this->ReadPropertyString('SearchMarker');
        for ($i = 0; $i < count($CallList->Call); $i++) {
            $Data[$i]['Name'] = (string) $CallList->Call[$i]->Name;
            if ((int) $CallList->Call[$i]->Type == self::Call_Outgoing) {
                $Data[$i]['Caller'] = str_replace(strtoupper((string) $CallList->Call[$i]->Numbertype) . ': ', '', (string) $CallList->Call[$i]->Caller);
                $Data[$i]['Called'] = (string) $CallList->Call[$i]->Called;
                $Data[$i]['Number'] = (string) $CallList->Call[$i]->Called;
                if ($Data[$i]['Name'] == '') {
                    $Data[$i]['Name'] = $this->DoReverseSearch($ReverseSearchInstanceID, $CustomSearchScriptID, $Data[$i]['Called'], $UnknownName, $SearchMarker, $MaxNameSize);
                } else {
                    if (strlen($Data[$i]['Name']) > $MaxNameSize) {
                        $Data[$i]['Name'] = substr($Data[$i]['Name'], 0, $MaxNameSize);
                    }
                }
            } else {
                $Data[$i]['Caller'] = (string) $CallList->Call[$i]->Caller;
                $Data[$i]['Called'] = str_replace(strtoupper((string) $CallList->Call[$i]->Numbertype) . ': ', '', (string) $CallList->Call[$i]->Called);
                if ($Data[$i]['Caller'] == '') {
                    $Data[$i]['Name'] = $UnknownName;
                } else {
                    if ($Data[$i]['Name'] == '') {
                        $Data[$i]['Name'] = $this->DoReverseSearch($ReverseSearchInstanceID, $CustomSearchScriptID, $Data[$i]['Caller'], $UnknownName, $SearchMarker, $MaxNameSize);
                    } else {
                        if (strlen($Data[$i]['Name']) > $MaxNameSize) {
                            $Data[$i]['Name'] = substr($Data[$i]['Name'], 0, $MaxNameSize);
                        }
                    }
                }
                $Data[$i]['Number'] = (string) $CallList->Call[$i]->Caller;
            }
            $Data[$i]['Name'] = str_replace('{ICON}', '<div class="Icon' . $this->InstanceID . self::FoundMarker . '"></div>', $Data[$i]['Name']);
            //$CallList->Call[$i]->addChild("Fax"); // leeren FAX Eintrag erzeugen.
            $Data[$i]['Fax'] = '';
            // Fax-Anruf ?
            if ((int) $CallList->Call[$i]->Port == 5) {
                $Data[$i]['Type'] = self::Call_Fax; //self::Call_Tam_Deleted; // vorbelegen mit Fax schon gelöscht
                //$Data[$i]['Called'] = (string)$CallList->Call[$i]->Device;
                //$Data[$i]['Duration'] = "---";  // Warum auch immer ist die Dauer immer 0:01 auch bei FAX

                /*if (strlen((string)$CallList->Call[$i]->Path) <> 0) {
                    $Data[$i]['Fax'] = "1"; // FAX-Eintrag ist vorhanden !
                    //$CallList->Call[$i]->Path =  $AB_URL."?fax=".urlencode((string)$CallList->Call[$i]->Path);// URL-Anpassen
                    $Data[$i]['Type'] = self::Call_Fax;
                }*/
            } else {
                $Data[$i]['Type'] = (int) $CallList->Call[$i]->Type;
                $Data[$i]['Duration'] = (string) $CallList->Call[$i]->Duration;
            }
            $Data[$i]['Icon'] = '<div class="Icon' . $this->InstanceID . $Data[$i]['Type'] . '"></div>';
            $Data[$i]['Date'] = (string) $CallList->Call[$i]->Date;
            $Data[$i]['Device'] = (string) $CallList->Call[$i]->Device;
            $PhoneDevices[(int) $CallList->Call[$i]->Port] = (string) $CallList->Call[$i]->Device;
        }
        $this->SetPhoneDevices($PhoneDevices);
        $Config_Icons = json_decode($this->ReadPropertyString('Icons'), true);
        $Icon_CSS = '<div id="scoped-content"><style type="text/css" scoped>' . "\r\n";
        foreach ($Config_Icons as $Config_Icon) {
            $ImageData = @getimagesize('data://text/plain;base64,' . $Config_Icon['icon']);
            if ($ImageData === false) {
                continue;
            }
            if ($Config_Icon['type'] == self::FoundMarker) {
                $width = $ImageData[0] . 'px';
            } else {
                $width = '100%';
            }
            $Icon_CSS .= '.Icon' . $this->InstanceID . $Config_Icon['type'] . ' {width:' . $width . ';height:' . $ImageData[1] . 'px;background:url(' . 'data://' . $ImageData['mime'] . ';base64,' . $Config_Icon['icon'] . ') no-repeat ' . $Config_Icon['align'] . ' center;' . $Config_Icon['style'] . '}' . "\r\n";
        }
        $Icon_CSS .= '</style>';
        //$JS ='<script type="text/javascript" src="hook/FritzBoxCallList'.$this->InstanceID.'/tooltips.js"></script>';
        $JS = '';
        $HTML = $this->GetTable($Data) . '</div>';
        $this->SetValue('CallList', $Icon_CSS . $JS . $HTML);
        $this->SendDebug('RefreshCallList', 'done', 0);
        return true;
    }

    private function RefreshPhonebook()
    {
        $result = $this->GetPhonebookList();
        if ($result === false) {
            return false;
        }
        $PhonebookIDs = explode(',', $result);
        $LoadedFiles = [];
        foreach ($PhonebookIDs as $PhonebookID) {
            $PhonebookData = $this->GetPhonebook((int) $PhonebookID);
            if ($PhonebookData === false) {
                continue;
            }
            $FileName = 'Phonebook_' . $PhonebookData['NewPhonebookName'] . '.xml';
            if (!$this->LoadAndSaveFile($PhonebookData['NewPhonebookURL'], $FileName)) {
                continue;
            }
            $LoadedFiles[] = $FileName;
        }
        // $LoadedFiles im IO ablegen, damit andere es lesen können.
        $this->SetPhonebookFiles($LoadedFiles);
        return true;
    }
    private function RefreshDeflectionList($ActionIdent = null, $ActionValue = false)
    {
        if (!$this->ReadPropertyBoolean('DeflectionAsVariable') && !$this->ReadPropertyBoolean('CallBarringAsVariable') && is_null($ActionIdent)) {
            return false;
        }
        if (is_null($ActionIdent)) {
            $Result = true;
        } else {
            $Result = false;
        }
        $Deflections = $this->GetDeflections();
        if ($Deflections === false) {
            return false;
        }
        $PhoneBooks = $this->GetPhoneBookFiles();
        $DeflectionList = new \simpleXMLElement($Deflections);
        $CallBarringItems = $DeflectionList->xpath("//Item[Mode='eNoSignal' and DeflectionToNumber='']");
        foreach ($CallBarringItems as $Index => $CallBarringItem) {
            $this->SendDebug('CallBarring:' . $Index, (array) $CallBarringItem, 0);
            $Ident = 'C_' . $CallBarringItem->Type . '_' . $CallBarringItem->Number . '_' . $CallBarringItem->PhonebookID;
            $this->SendDebug('CallBarringIdent:' . $Index, $Ident, 0);
            $Value = (int) $CallBarringItem->Enable === 1;
            if ($ActionIdent == $Ident) {
                if ($this->SetDeflectionEnable((int) $CallBarringItem->DeflectionId, $ActionValue)) {
                    $Result = true;
                    $Value = $ActionValue;
                }
            }
            if ($this->ReadPropertyBoolean('CallBarringAsVariable')) {
                if (@$this->GetIDForIdent($Ident)) {
                    $this->SetValue($Ident, $Value);
                } else {
                    switch ($CallBarringItem->Type) {
                    case 'fromAll':
                        $Name = $this->Translate('Block all incoming calls');
                    break;
                    case 'fromAnonymous':
                        $Name = $this->Translate('Block anonymous incoming calls');
                    break;
                    case 'fromNotVIP':
                        $Name = $this->Translate('Block incoming call not from a VIP');
                    break;
                    case 'fromNumber':
                        $CallBarringNumberName = $this->DoPhonebookSearch((string) $CallBarringItem->Number, 50);
                        if ($CallBarringNumberName == '') {
                            $CallBarringNumberName = (string) $CallBarringItem->Number;
                        }
                        $Name = $this->Translate('Block incoming call from number') . ' ' . $CallBarringNumberName;
                    break;
                    case 'fromPB':
                        $Name = $this->Translate('Block incoming call from phonebook');
                        if (array_key_exists((int) $CallBarringItem->PhonebookID, $PhoneBooks)) {
                            $Name .= ' ' . substr($PhoneBooks[(int) $CallBarringItem->PhonebookID], 10, -4);
                        } else {
                            $Name .= ' ' . $CallBarringItem->PhonebookID;
                        }
                    break;
                    case 'fromVIP':
                        $Name = $this->Translate('Block incoming calls from a VIP number') . ' ' . $CallBarringItem->Number;
                    break;
                    default:
                        $Name = 'Block ' . $CallBarringItem->Type . ' ' . $CallBarringItem->Number;
                    break;
                    }
                    $this->SendDebug('CallBarringName:' . $Index, $Name, 0);
                    $this->setIPSVariable($Ident, $Name, $Value, VARIABLETYPE_BOOLEAN, '~Switch', true);
                }
            }
        }

        $DeflectionItems = $DeflectionList->xpath("//Item[DeflectionToNumber !='']");
        foreach ($DeflectionItems as $Index => $DeflectionItem) {
            $this->SendDebug('Deflection:' . $Index, (array) $DeflectionItem, 0);
            $Ident = 'D_' . $DeflectionItem->Type . '_' . $DeflectionItem->Number . '_' . $DeflectionItem->DeflectionToNumber;
            $this->SendDebug('DeflectionIdent:' . $Index, $Ident, 0);
            $Value = (int) $DeflectionItem->Enable === 1;
            if ($ActionIdent == $Ident) {
                if ($this->SetDeflectionEnable((int) $DeflectionItem->DeflectionId, $ActionValue)) {
                    $Result = true;
                    $Value = $ActionValue;
                }
            }
            if ($this->ReadPropertyBoolean('DeflectionAsVariable')) {
                if (@$this->GetIDForIdent($Ident)) {
                    $this->SetValue($Ident, $Value);
                } else {
                    $DeviceName = $this->DoPhonebookSearch('**' . (string) $DeflectionItem->DeflectionToNumber, 50);
                    if ($DeviceName == '') {
                        $DeviceName = $this->DoPhonebookSearch((string) $DeflectionItem->DeflectionToNumber, 50);
                    }
                    if ($DeviceName == '') {
                        $DeviceName = (string) $DeflectionItem->DeflectionToNumber;
                    }
                    switch ($DeflectionItem->Type) {
                        case 'fromAll':
                            $Name = $this->Translate('Deflect all incoming calls to') . ' ' . $DeviceName;
                        break;
                        case 'fromAnonymous':
                            $Name = $this->Translate('Deflect anonymous incoming calls to') . ' ' . $DeviceName;
                        break;
                        case 'fromNotVIP':
                            $Name = $this->Translate('Deflect incoming call not from a VIP to') . ' ' . $DeviceName;
                        break;
                        case 'fromNumber':
                            $DeflectionNumberName = $this->DoPhonebookSearch((string) $DeflectionItem->Number, 50);
                            if ($DeflectionNumberName == '') {
                                $DeflectionNumberName = (string) $DeflectionItem->Number;
                            }
                            $Name = sprintf($this->Translate('Deflect incoming call from %s to %s'), $DeflectionNumberName, $DeviceName);
                        break;
                        case 'fromPB':
                            $PhonebookName = (string) $DeflectionItem->PhonebookID;
                            if (array_key_exists((int) $DeflectionItem->PhonebookID, $PhoneBooks)) {
                                $PhonebookName = substr($PhoneBooks[(int) $DeflectionItem->PhonebookID], 10, -4);
                            }
                            $Name = sprintf($this->Translate('Deflect incoming call from phonebook (%s) to %s'), $PhonebookName, $DeviceName);

                        break;
                        case 'fromVIP':
                            $Name = $this->Translate('Deflect incoming calls from a VIP number to') . ' ' . $DeviceName;
                        break;
                        //toMSN ' ' . $DeflectionItem->Number . ' to ' . $DeviceName;
                        //toPOTS ' to ' . $DeviceName;
                        //toVoIP ' ' . $DeflectionItem->Number . ' to ' . $DeviceName;
                        default:
                            $Name = 'Deflect ' . $DeflectionItem->Type . ' ' . $DeflectionItem->Number . ' to ' . $DeviceName;
                        break;
                    }
                    $this->SendDebug('DeflectionName:' . $Index, $Name, 0);
                    $this->setIPSVariable($Ident, $Name, $Value, VARIABLETYPE_BOOLEAN, '~Switch', true);
                }
            }
        }
        return $Result;
    }
    private function SetPhonebookFiles(array $Files)
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Function', 'SetPhonebookFiles', 0);
        $this->SendDebug('Files', $Files, 0);
        $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'SETPHONEBOOKS',
                'Files'      => $Files
            ]
        ));
    }

    private function GenerateHTMLStyleProperty()
    {
        $NewTableConfig = [
            [
                'tag'   => '<table>',
                'style' => 'margin:0 auto; font-size:0.8em; float:center;'
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
                'key'     => 'Icon',
                'name'    => '',
                'show'    => true,
                'width'   => 35,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],           [
                'index'   => 1,
                'key'     => 'Type',
                'name'    => $this->Translate('Type'),
                'show'    => false,
                'width'   => 35,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],
            [
                'index'   => 2,
                'key'     => 'Date',
                'name'    => $this->Translate('Time'),
                'show'    => true,
                'width'   => 110,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 3,
                'key'     => 'Name',
                'name'    => $this->Translate('Name'),
                'show'    => true,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 4,
                'key'     => 'Caller',
                'name'    => $this->Translate('Caller'),
                'show'    => false,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 5,
                'key'     => 'Called',
                'name'    => $this->Translate('Called'),
                'show'    => false,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 6,
                'key'     => 'Number',
                'name'    => $this->Translate('Number'),
                'show'    => true,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 7,
                'key'     => 'Device',
                'name'    => $this->Translate('Device'),
                'show'    => true,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],

            [
                'index'   => 8,
                'key'     => 'Duration',
                'name'    => $this->Translate('Duration'),
                'show'    => true,
                'width'   => 80,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ]/*,
            [
                'index' => 9,
                'key'   => 'Action',
                'name'  => $this->Translate('Fax / TAM'),
                'show'  => false,
                'width' => 50,
                'hrcolor' => 0xffffff,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ]*/
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
        $NewIcons = [
            [
                'type'          => self::Call_Incoming,
                'DisplayName'   => $this->Translate('Incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Missed,
                'DisplayName'   => $this->Translate('Missed call'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callinfailed.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            /*[
                'type'          => self::Call_Tam_New,
                'DisplayName'   => $this->Translate('New message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/msgnew.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Tam_Old,
                'DisplayName'   => $this->Translate('Old message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/msgold.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Tam_Deleted,
                'DisplayName'   => $this->Translate('Deleted message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/delete.png')),
                'align'         => 'center',
                'style'         =>''
            ],*/
            [
                'type'          => self::Call_Active_Incoming,
                'DisplayName'   => $this->Translate('Incoming (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Rejected_Incoming,
                'DisplayName'   => $this->Translate('Rejected call'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callinfailed.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Active_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Fax,
                'DisplayName'   => $this->Translate('Fax'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/msgfax.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::FoundMarker,
                'DisplayName'   => $this->Translate('{ICON} for reverse search'),
                'icon'          => '',
                'align'         => 'left',
                'style'         => 'display:inline-block;'
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig, 'Icons'=> $NewIcons];
    }
}
