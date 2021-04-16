<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTelHelper.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * @property array $CallData
 */
class FritzBoxCallmonitor extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;
    use \FritzBoxModul\TelHelper;

    protected static $ControlUrlArray = [
        '/upnp/control/x_contact'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'
    ];

    protected static $SecondEventGUID = '{FE5B2BCA-CA0F-25DC-8E79-BDFD242CB06E}';
    const Call_Incoming = 1;
    const Call_Outgoing = 2;
    const Connected_Incoming = 3;
    const Connected_Outgoing = 4;
    const Disconnect_Incoming = 5;
    const Disconnect_Outgoing = 6;
    const FoundMarker = 20;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);

        $this->RegisterPropertyInteger('ReverseSearchInstanceID', 0);
        $this->RegisterPropertyInteger('CustomSearchScriptID', 0);
        $this->RegisterPropertyInteger('MaxNameSize', 30);
        $this->RegisterPropertyString('SearchMarker', '(*) ');
        $this->RegisterPropertyString('UnknownNumberName', $this->Translate('(unknown)'));
        $this->RegisterPropertyBoolean('NotShowWarning', false);

        $this->RegisterPropertyBoolean('CallsAsTable', true);
        $this->RegisterPropertyBoolean('CallsAsNotification', true);
        $this->RegisterPropertyString('Targets', json_encode([]));
        $this->RegisterPropertyString('Notification', json_encode($this->GenerateDefaultNotificationProperty()));
        $this->RegisterPropertyString('Actions', json_encode([]));
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterPropertyString('Icons', json_encode($Style['Icons']));
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
        foreach ($this->GetReferenceList() as $Reference) {
            $this->UnregisterReference($Reference);
        }
        foreach (json_decode($this->ReadPropertyString('Targets'), true) as $Target) {
            $this->RegisterReference($Target['target']);
        }
        foreach (json_decode($this->ReadPropertyString('Actions'), true) as $Action) {
            $this->RegisterReference(json_decode($Action['action'], true)['targetID']);
        }
        if ($this->ReadPropertyBoolean('CallsAsTable')) {
            $this->RegisterVariableString('CallList', $this->Translate('Active calls'), '~HTMLBox', 0);
            $this->RebuildTable();
        } else {
            $this->UnregisterVariable('CallList');
        }
    }
    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshCallList':
                return $this->RebuildTable();
            case 'SendNotification':
                return $this->SendNotification(unserialize($Value));
            case 'RunActions':
                return $this->RunActions(unserialize($Value));
            case 'PreviewIcon':
                $Data = unserialize($Value);
                $ImageData =  @getimagesize('data://text/plain;base64,' . $Data['Icon']);
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
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if (!IPS_LibraryExists('{D0E8905A-F00C-EA84-D607-3D27000348D8}')) {
            if (!$this->ReadPropertyBoolean('NotShowWarning')) {
                $Form['elements'][5]['visible']=true;
            }
        }
        if ($this->ReadPropertyInteger('CustomSearchScriptID')>0) {
            $Form['elements'][0]['items'][1]['expanded']=false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][0]['enabled']=false;
            $Form['elements'][0]['items'][1]['items'][0]['items'][1]['enabled']=false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][0]['enabled']=false;
            $Form['elements'][0]['items'][1]['items'][1]['items'][1]['enabled']=false;
        }
        if ($this->ReadPropertyInteger('ReverseSearchInstanceID')>0) {
            $Form['elements'][1]['items'][1]['enabled']=false;
        }
        $Form['elements'][2]['items'][1]['items'][1]['columns'][3]['edit']['options'] = $this->GetIconsList();
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }
    private function SendNotification(array $NotifyData)
    {
        if (!$this->ReadPropertyBoolean('CallsAsNotification')) {
            return;
        }
        $this->SendDebug('SendNotification', $NotifyData, 0);
        $WFC_IDs = json_decode($this->ReadPropertyString('Targets'), true);
        if (sizeof($WFC_IDs) == 0) {
            return;
        }
        $this->SendDebug('Targets', $WFC_IDs, 0);
        $NotificationConfig = json_decode($this->ReadPropertyString('Notification'), true);
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
        $Actions = json_decode($this->ReadPropertyString('Actions'), true);
        if (sizeof($Actions) == 0) {
            return;
        }
        $RunActions = array_filter($Actions, function ($Action) use ($NotifyData) {
            if ($Action['event'] == 0) {
                return true;
            }
            return $Action['event'] == $NotifyData['EVENT'];
        });
        $this->SendDebug('RunActions', $RunActions, 0);
        $this->SendDebug('RunActions', $NotifyData, 0);
        foreach ($RunActions as $Action) {
            $ActionData = json_decode($Action['action'], true);
            $ActionData['parameters'] = array_merge($ActionData['parameters'], $NotifyData);
            $ActionData['parameters']['SENDER'] = 'FritzBox';
            $this->SendDebug('ActionData', $ActionData, 0);
            IPS_RunAction($ActionData['actionID'], $ActionData['targetID'], $ActionData['parameters']);
        }
    }
    private function RebuildTable()
    {
        if (!$this->ReadPropertyBoolean('CallsAsTable')) {
            return true;
        }
        $Calls = $this->CallData;
        $this->SendDebug('Calls', $Calls, 0);
        //todo
        $Config_Icons = json_decode($this->ReadPropertyString('Icons'), true);
        $Icon_CSS = '<div id="scoped-content"><style type="text/css" scoped>' . "\r\n";
        foreach ($Config_Icons as $Config_Icon) {
            $ImageData =  @getimagesize('data://text/plain;base64,' . $Config_Icon['icon']);
            if ($ImageData === false) {
                continue;
            }
            if ($Config_Icon['type'] == self::FoundMarker) {
                $width = $ImageData[0].'px';
            } else {
                $width = '100%';
            }
            $Icon_CSS .= '.Icon' . $this->InstanceID . $Config_Icon['type'] . ' {width:'.$width.';height:'.$ImageData[1].'px;background:url('.'data://'.$ImageData['mime'].';base64,'.$Config_Icon['icon'].') no-repeat '.$Config_Icon['align'].' center;'.$Config_Icon['style'].'}'."\r\n";
        }
        $Icon_CSS .= '</style>';
        foreach ($Calls as &$Call) {
            if ($Call['Type'] == 'CALLIN') {
                $Call['Icon'] = '<div class="Icon' . $this->InstanceID . self::Call_Incoming . '"></div>';
            } else {
                $Call['Icon'] = '<div class="Icon' . $this->InstanceID . self::Call_Outgoing . '"></div>';
            }
            $Call['Name']  = str_replace('{ICON}', '<div class="Icon'.$this->InstanceID.self::FoundMarker.'"></div>', $Call['Name']);
        }
        $HTML = $this->GetTable($Calls) . '</div>';
        $this->SetValue('CallList', $Icon_CSS . $HTML);
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
                'index' => 0,
                'key'   => 'Icon',
                'name'  => '',
                'show'  => true,
                'width' => 35,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],           [
                'index' => 1,
                'key'   => 'Type',
                'name'  => $this->Translate('Type'),
                'show'  => false,
                'width' => 35,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],
            [
                'index' => 2,
                'key'   => 'Time',
                'name'  => $this->Translate('Time'),
                'show'  => true,
                'width' => 110,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],  [
                'index' => 3,
                'key'   => 'Line',
                'name'  => $this->Translate('Line'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 4,
                'key'   => 'Name',
                'name'  => $this->Translate('Name'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 5,
                'key'   => 'Remote',
                'name'  => $this->Translate('Remote'),
                'show'  => false,
                'width' => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 6,
                'key'   => 'Device',
                'name'  => $this->Translate('Device'),
                'show'  => false,
                'width' => 150,
                'hrcolor' => -1,
                'hralign' => 'left',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 7,
                'key'   => 'Local',
                'name'  => $this->Translate('Local number'),
                'show'  => true,
                'width' => 150,
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
        if ($Number == '') {
            return $this->ReadPropertyString('UnknownNumberName');
        }
        $Name = $this->DoPhonebookSearch($Number);
        if ($Name === false) {
            $UnknownName='('.$Number.')';
            $ReverseSearchInstanceID= $this->ReadPropertyInteger('ReverseSearchInstanceID');
            $CustomSearchScriptID = $this->ReadPropertyInteger('CustomSearchScriptID');
            $MaxNameSize=$this->ReadPropertyInteger('MaxNameSize');
            $SearchMarker = $this->ReadPropertyString('SearchMarker');
            $Name = $this->DoReverseSearch($ReverseSearchInstanceID, $CustomSearchScriptID, $Number, $UnknownName, $SearchMarker, $MaxNameSize);
        }
        return $Name;
    }
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        $this->SendDebug('ReceiveCallMonitorData', $data, 0);

        $CallEvent = explode(";", utf8_decode($data['Buffer']));
        $CallEvent[2] = (int)$CallEvent[2];
        $Calls = $this->CallData;

        $Calls[$CallEvent[2]]['Status'] = $CallEvent[1];
        $Name = false;
        switch ($CallEvent[1]) {
            case "RING": // Ankommend klingelt
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
            case "CALL": //Abgehend
                $Calls[$CallEvent[2]] = [];
                $Calls[$CallEvent[2]]['Event'] = self::Call_Outgoing;
                $Calls[$CallEvent[2]]['Type'] = 'CALLOUT';
                $Calls[$CallEvent[2]]['Device'] = $this->GetPhoneDeviceNameByID((int)$CallEvent[3]);
                $Calls[$CallEvent[2]]['DeviceID'] = (int)$CallEvent[3];
                $Calls[$CallEvent[2]]['Duration_Raw'] = 0;
                $Calls[$CallEvent[2]]['Duration'] = $this->ConvertRuntime(0);
                $Calls[$CallEvent[2]]['Local'] = $CallEvent[4];
                $Calls[$CallEvent[2]]['Remote'] = $CallEvent[5];
                $Calls[$CallEvent[2]]['Line'] = $CallEvent[6];
                $Calls[$CallEvent[2]]['Time'] = $CallEvent[0];
                $Calls[$CallEvent[2]]['Name'] = $this->SearchName($CallEvent[5]);
                break;
            case "CONNECT": // Verbunden
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event'] = self::Connected_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event'] = self::Connected_Outgoing;
                }
                $Calls[$CallEvent[2]]['Status'] = $CallEvent[1];
                $Calls[$CallEvent[2]]['Time'] = $CallEvent[0];
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] = $this->GetPhoneDeviceNameByID((int)$CallEvent[3]);
                    $Calls[$CallEvent[2]]['DeviceID'] = (int)$CallEvent[3];
                }
                break;
            case "DISCONNECT": // Getrennt
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event'] = self::Disconnect_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event'] = self::Disconnect_Outgoing;
                }
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] = '';
                }
                $Calls[$CallEvent[2]]['Duration_Raw'] = (int)$CallEvent[3];
                $Calls[$CallEvent[2]]['Duration'] = $this->ConvertRuntime((int)$CallEvent[3]);
                break;
        }
        $NotifyData = $this->ArrayKeyToUpper($Calls[$CallEvent[2]]);
        $NotifyData['NAME'] = str_replace('{ICON}', '', $NotifyData['NAME']);
        if ($CallEvent[1] == "DISCONNECT") {
            unset($Calls[$CallEvent[2]]);
        }
        $this->CallData = $Calls;
        // Nur wenn WebFront Notification aktiv
        if ($this->ReadPropertyBoolean('CallsAsNotification')) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'SendNotification\',\'' . serialize($NotifyData) . '\');');
        }
        //nur wenn HTML-Tabelle aktiv
        if ($this->ReadPropertyBoolean('CallsAsTable')) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'RefreshCallList\',true);');
        }
        //nur wenn Aktions aktiv
        if (sizeof(json_decode($this->ReadPropertyString('Actions'))) > 0) {
            IPS_RunScriptText('IPS_RequestAction(' . $this->InstanceID . ',\'RunActions\',\'' . serialize($NotifyData) . '\');');
        }
        return true;
    }

    private function GenerateDefaultNotificationProperty()
    {
        return [
            [
                'event' => self::Call_Incoming,
                'title' => $this->Translate('Incoming Call!'),
                'text' => $this->Translate('From: {NAME} To: {LOCAL}'),
                'icon' => '',
                'timeout' => 30,
            ],
            [
                'event' => self::Call_Outgoing,
                'title' => $this->Translate('Outgoing call!'),
                'text' => $this->Translate('{DEVICE} calling {NAME}.'),
                'icon' => '',
                'timeout' => 30,
            ],
            [
                'event' => self::Connected_Incoming,
                'title' => $this->Translate('Call accepted!'),
                'text' => $this->Translate('{DEVICE} has accepted the call.'),
                'icon' => '',
                'timeout' => 30,
            ],
            [
                'event' => self::Connected_Outgoing,
                'title' => $this->Translate('Call accepted!'),
                'text' => $this->Translate('{NAME} has accepted the call.'),
                'icon' => '',
                'timeout' => 30,
            ],
            [
                'event' => self::Disconnect_Incoming,
                'title' => $this->Translate('Call ended!'),
                'text' => $this->Translate('Call from {NAME} ended after {DURATION}.'),
                'icon' => '',
                'timeout' => 30,
            ],
            [
                'event' => self::Disconnect_Outgoing,
                'title' => $this->Translate('Call ended!'),
                'text' => $this->Translate('Call from {DEVICE} ended after {DURATION}.'),
                'icon' => '',
                'timeout' => 30,
            ]
        ];
    }
}
