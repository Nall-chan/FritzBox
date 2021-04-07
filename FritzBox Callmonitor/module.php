<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * @property array $CallData
 */
class FritzBoxCallmonitor extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;

    protected static $ControlUrlArray = [
        '/upnp/control/x_contact'
    ];
    protected static $EventSubURLArray = [

    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'
    ];

    protected static $SecondEventGUID='{FE5B2BCA-CA0F-25DC-8E79-BDFD242CB06E}';
    const Call_Incoming = 1;
    const Call_Outgoing = 2;
    const Connected_Incoming = 3;
    const Connected_Outgoing = 4;
    const Disconnect_Incoming = 5;
    const Disconnect_Outgoing = 6;
    const FoundMarker= 20;
    
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
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
        $this->CallData=[];
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
        $this->RebuildTable();
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
                    $ImageData =  @getimagesize('data://text/plain;base64,'.$Data['Icon']);
                        if ($ImageData === false) {
                            $this->UpdateFormField('IconName', 'caption', 'No valid image');
                            $this->UpdateFormField('IconPreview', 'visible', true);
                            return;
                        }
                    $this->UpdateFormField('IconName', 'caption', $Data['DisplayName']);
                    $this->UpdateFormField('IconImage', 'image', 'data://'.$ImageData['mime'].';base64,'.$Data['Icon']);
                    $this->UpdateFormField('IconPreview', 'visible', true);
                    return;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }
    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $Form['elements'][0]['items'][1]['items'][1]['columns'][3]['edit']['options'] =$this->GetIconsList();
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
        $NotifyData=$this->ArrayWithCurlyBracketsKey($NotifyData);
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
        if (sizeof($Actions)==0) {
            return;
        }
        $RunActions= array_filter($Actions, function ($Action) use ($NotifyData) {
            if ($Action['event'] == 0) {
                return true;
            }
            return $Action['event'] == $NotifyData['EVENT'];
        });
        $this->SendDebug('RunActions', $RunActions, 0);
        $this->SendDebug('RunActions', $NotifyData, 0);
        foreach ($RunActions as $Action) {
            $ActionData = json_decode($Action['action'], true);
            $ActionData['parameters']=array_merge($ActionData['parameters'], $NotifyData);
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
        //Tabelle bauen
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
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''

            ],           [
                'index' => 1,
                'key'   => 'Type',
                'name'  => $this->Translate('Type'),
                'show'  => false,
                'width' => 35,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''

            ],
            [
                'index' => 2,
                'key'   => 'Date',
                'name'  => $this->Translate('Time'),
                'show'  => true,
                'width' => 110,
                'hrcolor' => -1,
                'hralign' => 'center',
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
                'hralign' => 'center',
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
                'hralign' => 'center',
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
                'hralign' => 'center',
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
                'hralign' => 'center',
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
        $NewIcons=[
            [
                'type'          => self::Call_Incoming,
                'DisplayName'   => $this->Translate('Incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callnew.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::FoundMarker,
                'DisplayName'   => $this->Translate('Marker for reverse search'),
                'icon'          => '',
                'align'         => 'center',
                'style'         => ''
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig, 'Icons'=> $NewIcons];
    }
    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        unset($data['DataID']);
        $this->SendDebug('ReceiveCallMonitorData', $data, 0);

        $CallEvent = explode(";", utf8_decode($data['Buffer']));
        $CallEvent[2]=(int)$CallEvent[2];
        $Calls = $this->CallData;
        
        $Calls[$CallEvent[2]]['Status'] =$CallEvent[1];
        $Name = false;
        switch ($CallEvent[1]) {
            case "RING": // Ankommend klingelt
                $Calls[$CallEvent[2]]=[];
                $Calls[$CallEvent[2]]['Type'] ='CALLIN';
                $Calls[$CallEvent[2]]['Event'] =self::Call_Incoming;
                $Calls[$CallEvent[2]]['Remote'] =$CallEvent[3];
                $Calls[$CallEvent[2]]['Local'] =$CallEvent[4];
                $Calls[$CallEvent[2]]['Line'] =$CallEvent[5];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                $Calls[$CallEvent[2]]['Device'] ='*** RING ***';
                $Calls[$CallEvent[2]]['DeviceID'] =0;
                $Calls[$CallEvent[2]]['Duration'] =$this->ConvertRuntime(0);
                $Calls[$CallEvent[2]]['DurationRaw'] =0;
                //todo
                //$Name = FB_InversSuche($CallEvent[3], $Config['SucheType']);
                if ($Name === false) {
                    $Name =$CallEvent[3];
                }
                $Calls[$CallEvent[2]]['Name'] = $Name;
            break;
            case "CALL": //Abgehend
                $Calls[$CallEvent[2]]=[];
                $Calls[$CallEvent[2]]['Event'] =self::Call_Outgoing;
                $Calls[$CallEvent[2]]['Type'] ='CALLOUT';
                $Calls[$CallEvent[2]]['Device'] = 'ToDo:'.(int)$CallEvent[3]; //FB_GetPhoneDevice((int)$CallEvent[3]);
                $Calls[$CallEvent[2]]['DeviceID'] =(int)$CallEvent[3];
                $Calls[$CallEvent[2]]['DurationRaw'] =0;
                $Calls[$CallEvent[2]]['Duration'] =$this->ConvertRuntime(0);
                $Calls[$CallEvent[2]]['Local'] =$CallEvent[4];
                $Calls[$CallEvent[2]]['Remote'] =$CallEvent[5];
                $Calls[$CallEvent[2]]['Line'] =$CallEvent[6];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                //todo
                //$Name = FB_InversSuche($CallEvent[5], $Config['SucheType']);
                if ($Name === false) {
                    $Name =$CallEvent[5];
                }
                $Calls[$CallEvent[2]]['Name'] = $Name;
            break;
            case "CONNECT": // Verbunden
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event']= self::Connected_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event']= self::Connected_Outgoing;
                }
                $Calls[$CallEvent[2]]['Status'] =$CallEvent[1];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] ='ToDo:'.(int)$CallEvent[3];//FB_GetPhoneDevice($CallEvent[3]);
                    $Calls[$CallEvent[2]]['DeviceID'] =(int)$CallEvent[3];
                }
            break;
            case "DISCONNECT": // Getrennt
                if ($Calls[$CallEvent[2]]['Type'] == 'CALLIN') {
                    $Calls[$CallEvent[2]]['Event']= self::Disconnect_Incoming;
                } else {
                    $Calls[$CallEvent[2]]['Event']= self::Disconnect_Outgoing;
                }
                if ($Calls[$CallEvent[2]]['DeviceID'] == 0) {
                    $Calls[$CallEvent[2]]['Device'] ='';
                }
                $Calls[$CallEvent[2]]['DurationRaw'] =(int)$CallEvent[3];
                $Calls[$CallEvent[2]]['Duration'] =$this->ConvertRuntime((int)$CallEvent[3]);
            break;
        }
        $NotifyData=$this->ArrayKeyToUpper($Calls[$CallEvent[2]]);
        if ($CallEvent[1] == "DISCONNECT") {
            unset($Calls[$CallEvent[2]]);
        }
        $this->CallData = $Calls;
        // Nur wenn WebFront Notification aktiv
        if ($this->ReadPropertyBoolean('CallsAsNotification')) {
            IPS_RunScriptText('IPS_RequestAction('.$this->InstanceID.',\'SendNotification\',\''.serialize($NotifyData).'\');');
        }
        //nur wenn HTML-Tabelle aktiv
        if ($this->ReadPropertyBoolean('CallsAsTable')) {
            IPS_RunScriptText('IPS_RequestAction('.$this->InstanceID.',\'RefreshCallList\',true);');
        }
        //nur wenn Aktions aktiv
        if (sizeof(json_decode($this->ReadPropertyString('Actions')))>0) {
            IPS_RunScriptText('IPS_RequestAction('.$this->InstanceID.',\'RunActions\',\''.serialize($NotifyData).'\');');
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
