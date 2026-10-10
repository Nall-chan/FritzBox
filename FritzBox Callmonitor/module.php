<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTelHelper.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
eval('declare(strict_types=1);namespace FritzBoxCallmonitor {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');

/**
 * @property array $CallData
 * @method bool lock(string $ident)
 * @method void unlock(string $ident)
 */
class FritzBoxCallmonitor extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;
    use \FritzBoxModul\TelHelper;
    use \FritzBoxCallmonitor\Semaphore;
    public const Call_Incoming = 1;
    public const Call_Outgoing = 2;
    public const Connected_Incoming = 3;
    public const Connected_Outgoing = 4;
    public const Disconnect_Incoming = 5;
    public const Disconnect_Outgoing = 6;
    public const FoundMarker = 20;

    protected static $ControlUrlArray = [
        '/upnp/control/x_contact'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'
    ];

    protected static $SecondEventGUID = \FritzBox\GUID::CallMonitorEvent;
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::AreaCode, '');
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::CountryCode, '');

        $this->RegisterPropertyInteger(\FritzBox\TelHelper\Property::ReverseSearchInstanceID, 0);
        $this->RegisterPropertyInteger(\FritzBox\TelHelper\Property::CustomSearchScriptID, 0);
        $this->RegisterPropertyInteger(\FritzBox\TelHelper\Property::MaxNameSize, 30);
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::SearchMarker, '(*) ');
        $this->RegisterPropertyString(\FritzBox\TelHelper\Property::UnknownNumberName, $this->Translate('(unknown)'));
        $this->RegisterPropertyBoolean(\FritzBox\Callmonitor\Property::NotShowWarning, false);

        $this->RegisterPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsTable, true);
        $this->RegisterPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsNotification, true);
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::Targets, json_encode([]));
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::Notification, json_encode($this->GenerateDefaultNotificationProperty()));
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::Actions, json_encode([]));
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\FritzBox\Table\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Rows, json_encode($Style['Rows']));
        $this->RegisterPropertyString(\FritzBox\Callmonitor\Property::Icons, json_encode($Style['Icons']));
        $this->CallData = [];
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
        // Vorwahlen nur einmalig aus der FritzBox übernehmen, solange sie leer sind (z.B. neue Instanz).
        // Spätere Änderungen erfolgen nur durch den Nutzer über das Konfigurationsformular.
        if (($this->ReadPropertyString(\FritzBox\Callmonitor\Property::AreaCode) == '') || ($this->ReadPropertyString(\FritzBox\Callmonitor\Property::CountryCode) == '')) {
            $Codes = $this->ReadAreaCodes();
            if ($Codes !== false) {
                $HasChanges = false;
                foreach ($Codes as $Property => $Value) {
                    if ($this->ReadPropertyString($Property) == '') {
                        IPS_SetProperty($this->InstanceID, $Property, $Value);
                        $HasChanges = true;
                    }
                }
                if ($HasChanges) {
                    IPS_ApplyChanges($this->InstanceID);
                    return;
                }
            }
        }
        foreach ($this->GetReferenceList() as $Reference) {
            $this->UnregisterReference($Reference);
        }
        foreach (json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Targets), true) as $Target) {
            $this->RegisterReference($Target['target']);
        }
        foreach (json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Actions), true) as $Action) {
            $ActionData = json_decode($Action['action'], true);
            $this->SendDebug('ActionData', $ActionData, 0);
            // Nicht jede Aktion hat ein Ziel (z.B. PHP-Code ausführen)
            $Target = $ActionData['parameters']['TARGET'] ?? 0;
            if ($Target > 0) {
                $this->RegisterReference($Target);
            }
        }
        if ($this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsTable)) {
            $this->RegisterVariableString(\FritzBox\Callmonitor\Variables::CallList, $this->Translate('Active calls'), '~HTMLBox', 0);
            $this->RebuildTable();
        } else {
            $this->UnregisterVariable(\FritzBox\Callmonitor\Variables::CallList);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\Callmonitor\Action::RefreshCallList:
                return $this->RebuildTable();
            case \FritzBox\Callmonitor\Action::ReadAreaCodes:
                $Codes = $this->ReadAreaCodes();
                if ($Codes === false) {
                    $this->ReportError($this->Translate('The dialing codes could not be read from the FRITZ!Box.'));
                    return false;
                }
                foreach ($Codes as $Property => $Value) {
                    $this->UpdateFormField($Property, 'value', $Value);
                }
                return true;
            case \FritzBox\Callmonitor\Action::EditAreaCodes:
                $this->UpdateFormField('CountryCode', 'enabled', true);
                $this->UpdateFormField('AreaCode', 'enabled', true);
                return true;
            case \FritzBox\Callmonitor\Action::SendNotification:
                return $this->SendNotification(unserialize($Value));
            case \FritzBox\Callmonitor\Action::RunActions:
                return $this->RunActions(unserialize($Value));
            case \FritzBox\Callmonitor\Action::PreviewIcon:
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
            case \FritzBox\Callmonitor\Action::ReverseSearchInstanceID:
                if ($Value > 1) {
                    $this->UpdateFormField('CustomSearchScriptID', 'enabled', false);
                } else {
                    $this->UpdateFormField('CustomSearchScriptID', 'enabled', true);
                }
                return;
            case \FritzBox\Callmonitor\Action::CustomSearchScriptID:
                if ($Value > 1) {
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
            case \FritzBox\Callmonitor\Action::CallsAsNotification:
                $this->UpdateFormField('NotificationPanel', 'expanded', $Value);
                $this->UpdateFormField('Targets', 'enabled', $Value);
                $this->UpdateFormField('Notification', 'enabled', $Value);
                return;
            case \FritzBox\Callmonitor\Action::CallsAsTable:
                $this->UpdateFormField('Icons', 'enabled', $Value);
                $this->UpdateFormField('ShowImage', 'enabled', $Value);
                $this->UpdateFormField('Table', 'enabled', $Value);
                $this->UpdateFormField('Columns', 'enabled', $Value);
                $this->UpdateFormField('Rows', 'enabled', $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', $Value);
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
        if (($this->ReadPropertyString(\FritzBox\Callmonitor\Property::AreaCode) == '') || ($this->ReadPropertyString(\FritzBox\Callmonitor\Property::CountryCode) == '')) {
            $Form['elements'][0]['items'][0]['items'][0]['enabled'] = true;
            $Form['elements'][0]['items'][0]['items'][1]['enabled'] = true;
            $Form['actions'][2]['visible'] = true;
            $Form['actions'][2]['popup']['items'][0]['caption'] = 'Areacode not found!';
            $Form['actions'][2]['popup']['items'][1]['caption'] = "The area code could not be determined automatically.\r\nPlease specify manually.";
        }
        if (!IPS_LibraryExists(\FritzBox\GUID::CIRS)) {
            if (!$this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::NotShowWarning)) {
                $Form['elements'][5]['visible'] = true;
            }
        }
        if ($this->ReadPropertyInteger(\FritzBox\TelHelper\Property::CustomSearchScriptID) > 1) {
            $Form['elements'][0]['items'][1]['expanded'] = false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][0]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][0]['enabled'] = false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][1]['enabled'] = false;
        }
        if ($this->ReadPropertyInteger(\FritzBox\TelHelper\Property::ReverseSearchInstanceID) > 1) {
            $Form['elements'][1]['items'][1]['enabled'] = false;
        }
        if (!$this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsTable)) {
            $Form['elements'][3]['items'][1]['items'][0]['items'][0]['enabled'] = false;
            $Form['elements'][3]['items'][1]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][3]['items'][1]['items'][1]['enabled'] = false;
            $Form['elements'][3]['items'][1]['items'][2]['enabled'] = false;
            $Form['elements'][3]['items'][1]['items'][3]['enabled'] = false;
        }
        $Form['elements'][2]['items'][1]['items'][1]['columns'][3]['edit']['options'] = $this->GetIconsList();
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        $this->SendDebug('ReceiveCallMonitorData', $data, 0);

        $CallEvent = explode(';', utf8_decode($data['Buffer']));
        $CallEvent[2] = (int) $CallEvent[2];
        $this->lock('CallData');
        $Calls = $this->CallData;

        $Calls[$CallEvent[2]]['Status'] = $CallEvent[1];
        switch ($CallEvent[1]) {
            case 'RING': // Ankommend klingelt
                $Calls[$CallEvent[2]] = [];
                $Calls[$CallEvent[2]]['Type'] = 'CALLIN';
                $Calls[$CallEvent[2]]['Event'] = self::Call_Incoming;
                $Calls[$CallEvent[2]]['Remote'] = $CallEvent[3];
                $Calls[$CallEvent[2]]['Local'] = $CallEvent[4];
                $Calls[$CallEvent[2]]['Line'] = $CallEvent[5];
                $Calls[$CallEvent[2]]['Time'] = $CallEvent[0];
                $Calls[$CallEvent[2]]['Device'] = '*** RING ***';
                $Calls[$CallEvent[2]]['DeviceID'] = 0;
                $Calls[$CallEvent[2]]['Duration'] = $this->ConvertRuntime(0);
                $Calls[$CallEvent[2]]['Duration_Raw'] = 0;
                $Calls[$CallEvent[2]]['Name'] = $this->SearchName($CallEvent[3]);
                break;
            case 'CALL': //Abgehend
                $Calls[$CallEvent[2]] = [];
                $Calls[$CallEvent[2]]['Event'] = self::Call_Outgoing;
                $Calls[$CallEvent[2]]['Type'] = 'CALLOUT';
                $Calls[$CallEvent[2]]['Device'] = $this->GetPhoneDeviceNameByID((int) $CallEvent[3]);
                $Calls[$CallEvent[2]]['DeviceID'] = (int) $CallEvent[3];
                $Calls[$CallEvent[2]]['Duration_Raw'] = 0;
                $Calls[$CallEvent[2]]['Duration'] = $this->ConvertRuntime(0);
                $Calls[$CallEvent[2]]['Local'] = $CallEvent[4];
                $Calls[$CallEvent[2]]['Remote'] = $CallEvent[5];
                $Calls[$CallEvent[2]]['Line'] = $CallEvent[6];
                $Calls[$CallEvent[2]]['Time'] = $CallEvent[0];
                $Calls[$CallEvent[2]]['Name'] = $this->SearchName($CallEvent[5]);
                break;
            case 'CONNECT': // Verbunden
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event'] = self::Connected_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event'] = self::Connected_Outgoing;
                }
                $Calls[$CallEvent[2]]['Status'] = $CallEvent[1];
                $Calls[$CallEvent[2]]['Time'] = $CallEvent[0];
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] = $this->GetPhoneDeviceNameByID((int) $CallEvent[3]);
                    $Calls[$CallEvent[2]]['DeviceID'] = (int) $CallEvent[3];
                }
                break;
            case 'DISCONNECT': // Getrennt
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event'] = self::Disconnect_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event'] = self::Disconnect_Outgoing;
                }
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] = '';
                }
                $Calls[$CallEvent[2]]['Duration_Raw'] = (int) $CallEvent[3];
                $Calls[$CallEvent[2]]['Duration'] = $this->ConvertRuntime((int) $CallEvent[3]);
                break;
        }
        $NotifyData = $this->ArrayKeyToUpper($Calls[$CallEvent[2]]);
        $NotifyData['NAME'] = str_replace('{ICON}', '', $NotifyData['NAME']);
        if ($CallEvent[1] == 'DISCONNECT') {
            unset($Calls[$CallEvent[2]]);
        }
        $this->CallData = $Calls;
        $this->unlock('CallData');
        // Nur wenn WebFront Notification aktiv
        if ($this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsNotification)) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'SendNotification\',\'' . serialize($NotifyData) . '\');');
        }
        //nur wenn HTML-Tabelle aktiv
        if ($this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsTable)) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'RefreshCallList\',true);');
        }
        //nur wenn Aktions aktiv
        if (count(json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Actions))) > 0) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'RunActions\',\'' . serialize($NotifyData) . '\');');
        }
        return true;
    }

    /**
     * Liest Länder- und Ortsvorwahl aus der FritzBox.
     *
     * @return array|false ['CountryCode' => '+49', 'AreaCode' => '030'] oder false, wenn die FritzBox keine Vorwahlen liefert.
     */
    private function ReadAreaCodes(): array|false
    {
        $AreaCodes = @$this->GetAreaCodes();
        if (!array_key_exists('OKZ', $AreaCodes)) {
            return false;
        }
        return [
            'CountryCode' => '+' . $AreaCodes['LKZ'],
            'AreaCode'    => $AreaCodes['OKZPrefix'] . $AreaCodes['OKZ']
        ];
    }

    private function SendNotification(array $NotifyData)
    {
        if (!$this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsNotification)) {
            return;
        }
        $this->SendDebug('SendNotification', $NotifyData, 0);
        $WFC_IDs = json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Targets), true);
        if (count($WFC_IDs) == 0) {
            return;
        }
        $this->SendDebug('Targets', $WFC_IDs, 0);
        $NotificationConfig = json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Notification), true);
        $ConfigIndex = array_search($NotifyData['EVENT'], array_column($NotificationConfig, 'event'));
        if ($ConfigIndex === false) {
            return;
        }

        $NotifyData = $this->ArrayWithCurlyBracketsKey($NotifyData);
        $Title = $NotificationConfig[$ConfigIndex]['title'];
        $Text = $NotificationConfig[$ConfigIndex]['text'];
        $Icon = $NotificationConfig[$ConfigIndex]['icon'];
        $Timeout = $NotificationConfig[$ConfigIndex]['timeout'];
        $Pattern = array_keys($NotifyData);
        $Values = array_values($NotifyData);
        $Title = str_replace($Pattern, $Values, $Title);
        $Text = str_replace($Pattern, $Values, $Text);
        foreach ($WFC_IDs as $Target) {
            WFC_SendNotification($Target['target'], $Title, $Text, $Icon, $Timeout);
        }
    }

    private function RunActions(array $NotifyData)
    {
        $Actions = json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Actions), true);
        if (count($Actions) == 0) {
            return;
        }
        $RunActions = array_filter($Actions, function ($Action) use ($NotifyData)
        {
            if ($Action['event'] == 0) {
                return true;
            }
            return $Action['event'] == $NotifyData['EVENT'];
        });
        $this->SendDebug('RunActions', $RunActions, 0);
        $this->SendDebug('RunActions', $NotifyData, 0);
        foreach ($RunActions as $Action) {
            $ActionData = json_decode($Action['action'], true);
            $DataForParameters = $this->ArrayWithCurlyBracketsKey($NotifyData);
            $Pattern = array_keys($DataForParameters);
            $Values = array_values($DataForParameters);
            $ActionData['parameters'] = $this->InsertValuesInActionParameters($ActionData['parameters'], $Pattern, $Values);
            $ActionData['parameters'] = array_merge($ActionData['parameters'], $NotifyData);
            $ActionData['parameters']['SENDER'] = 'FritzBox';
            $ActionData['parameters']['PARENT'] = $this->InstanceID;
            $this->SendDebug('ActionData', $ActionData, 0);
            IPS_RunAction($ActionData['actionID'], $ActionData['parameters']);
        }
    }

    /**
     * Ersetzt Platzhalter (z.B. {NUMBER}) in allen String-Parametern einer Aktion, auch in verschachtelten Arrays.
     *
     * @param array $Parameters Parameter der Aktion
     * @param array $Pattern    Platzhalter
     * @param array $Values     Werte zu den Platzhaltern
     *
     * @return array Parameter mit ersetzten Platzhaltern
     */
    private function InsertValuesInActionParameters(array $Parameters, array $Pattern, array $Values): array
    {
        foreach ($Parameters as $Key => $Parameter) {
            if (is_array($Parameter)) {
                $Parameters[$Key] = $this->InsertValuesInActionParameters($Parameter, $Pattern, $Values);
            } elseif (is_string($Parameter)) {
                $Parameters[$Key] = str_replace($Pattern, $Values, $Parameter);
            }
        }
        return $Parameters;
    }

    private function RebuildTable()
    {
        if (!$this->ReadPropertyBoolean(\FritzBox\Callmonitor\Property::CallsAsTable)) {
            return true;
        }
        $Calls = $this->CallData;
        $this->SendDebug('Calls', $Calls, 0);
        $Config_Icons = json_decode($this->ReadPropertyString(\FritzBox\Callmonitor\Property::Icons), true);
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
        foreach ($Calls as &$Call) {
            if ($Call['Type'] == 'CALLIN') {
                $Call['Icon'] = '<div class="Icon' . $this->InstanceID . self::Call_Incoming . '"></div>';
            } else {
                $Call['Icon'] = '<div class="Icon' . $this->InstanceID . self::Call_Outgoing . '"></div>';
            }
            $Call['Name'] = str_replace('{ICON}', '<div class="Icon' . $this->InstanceID . self::FoundMarker . '"></div>', $Call['Name']);
        }
        $HTML = $this->GetTable($Calls) . '</div>';
        $this->SetValue(\FritzBox\Callmonitor\Variables::CallList, $Icon_CSS . $HTML);
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
                'key'     => 'Time',
                'name'    => $this->Translate('Time'),
                'show'    => true,
                'width'   => 110,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],  [
                'index'   => 3,
                'key'     => 'Line',
                'name'    => $this->Translate('Line'),
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
                'index'   => 5,
                'key'     => 'Remote',
                'name'    => $this->Translate('Remote'),
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
                'key'     => 'Device',
                'name'    => $this->Translate('Device'),
                'show'    => false,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index'   => 7,
                'key'     => 'Local',
                'name'    => $this->Translate('Local number'),
                'show'    => true,
                'width'   => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
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
        $NewIcons = [
            [
                'type'          => self::Call_Incoming,
                'DisplayName'   => $this->Translate('Incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callnew.png')),
                'align'         => 'left',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/callout.png')),
                'align'         => 'left',
                'style'         => ''
            ],
            [
                'type'          => self::FoundMarker,
                'DisplayName'   => $this->Translate('Marker for reverse search'),
                'icon'          => '',
                'align'         => 'left',
                'style'         => 'display:inline-block;'
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig, 'Icons' => $NewIcons];
    }

    private function SearchName(string $Number)
    {
        $AreaCode = $this->ReadPropertyString(\FritzBox\Callmonitor\Property::AreaCode);
        $CountryCode = $this->ReadPropertyString(\FritzBox\Callmonitor\Property::CountryCode);
        $Name = $this->GetNameByNumber($Number, $AreaCode, $CountryCode);
        if ($Name === false) {
            $MaxNameSize = $this->ReadPropertyInteger(\FritzBox\TelHelper\Property::MaxNameSize);
            $UnknownName = '(' . $Number . ')';
            $SearchMarker = $this->ReadPropertyString(\FritzBox\Callmonitor\Property::SearchMarker);
            $Name = $this->DoReverseSearch($Number, $SearchMarker, $UnknownName, $MaxNameSize);
        }

        return $Name;
    }

    private function GenerateDefaultNotificationProperty()
    {
        return [
            [
                'event'   => self::Call_Incoming,
                'title'   => $this->Translate('Incoming Call!'),
                'text'    => $this->Translate('From: {NAME} To: {LOCAL}'),
                'icon'    => '',
                'timeout' => 30,
            ],
            [
                'event'   => self::Call_Outgoing,
                'title'   => $this->Translate('Outgoing call!'),
                'text'    => $this->Translate('{DEVICE} calling {NAME}.'),
                'icon'    => '',
                'timeout' => 30,
            ],
            [
                'event'   => self::Connected_Incoming,
                'title'   => $this->Translate('Call accepted!'),
                'text'    => $this->Translate('{DEVICE} has accepted the call.'),
                'icon'    => '',
                'timeout' => 30,
            ],
            [
                'event'   => self::Connected_Outgoing,
                'title'   => $this->Translate('Call accepted!'),
                'text'    => $this->Translate('{NAME} has accepted the call.'),
                'icon'    => '',
                'timeout' => 30,
            ],
            [
                'event'   => self::Disconnect_Incoming,
                'title'   => $this->Translate('Call ended!'),
                'text'    => $this->Translate('Call from {NAME} ended after {DURATION}.'),
                'icon'    => '',
                'timeout' => 30,
            ],
            [
                'event'   => self::Disconnect_Outgoing,
                'title'   => $this->Translate('Call ended!'),
                'text'    => $this->Translate('Call from {DEVICE} ended after {DURATION}.'),
                'icon'    => '',
                'timeout' => 30,
            ]
        ];
    }
}
