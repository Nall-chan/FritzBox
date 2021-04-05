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
    const Call_Missed=2;
    const Call_Outgoing = 3;
    const Call_Tam_New=4;
    const Call_Tam_Old = 5;
    const Call_Tam_Deleted = 6;
    const Call_Fax = 7;
    const Call_Active_Incoming = 9;
    const Call_Rejected_Incoming = 10;
    const Call_Active_Outgoing= 11;
    
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('Index', 0);
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
        }
        
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    private function RebuildTable()
    {
        $Calls = $this->CallData=[];
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
            ],
            [
                'index' => 3,
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
                'index' => 4,
                'key'   => 'Caller',
                'name'  => $this->Translate('Caller'),
                'show'  => false,
                'width' => 150,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 5,
                'key'   => 'Called',
                'name'  => $this->Translate('Called'),
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
                'key'   => 'Number',
                'name'  => $this->Translate('Number'),
                'show'  => true,
                'width' => 150,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index' => 7,
                'key'   => 'Device',
                'name'  => $this->Translate('Device'),
                'show'  => true,
                'width' => 150,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],

            [
                'index' => 8,
                'key'   => 'Duration',
                'name'  => $this->Translate('Duration'),
                'show'  => true,
                'width' => 80,
                'hrcolor' => -1,
                'hralign' => 'center',
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
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
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
        $NewIcons=[
            [
                'type'          => self::Call_Incoming,
                'DisplayName'   => $this->Translate('Incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Missed,
                'DisplayName'   => $this->Translate('Missed call'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callinfailed.png')),
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
            ],
            [
                'type'          => self::Call_Active_Incoming,
                'DisplayName'   => $this->Translate('Incoming (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Rejected_Incoming,
                'DisplayName'   => $this->Translate('Rejected call'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callinfailed.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Active_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Fax,
                'DisplayName'   => $this->Translate('Fax incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/../imgs/msgfax.png')),
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
        //return true;
        /*
        04.04.21 18:50:56;RING;0;015233755959;44993;SIP2;\r\n
        04.04.21 18:50:59;DISCONNECT;0;0;\r\n
*/
        $CallEvent = explode(";", utf8_decode($data->Buffer));
        $Calls = $this->CallData=[];
        $Calls[$CallEvent[2]]['Status'] =$CallEvent[1];
        $Name = false;
        switch ($CallEvent[1]) {
            case "RING": // Ankommend klingelt
                //$WFCData =	$WFC_Notify[$CallEvent[1]];
                $Calls[$CallEvent[2]]['Typ'] ='CALLIN';
                $Calls[$CallEvent[2]]['Remote'] =$CallEvent[3];
                $Calls[$CallEvent[2]]['Local'] =$CallEvent[4];
                $Calls[$CallEvent[2]]['Line'] =$CallEvent[5];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                $Calls[$CallEvent[2]]['Device'] ='*** RING ***';
                $Calls[$CallEvent[2]]['DeviceID'] =0;
                //$Name = FB_InversSuche($CallEvent[3], $Config['SucheType']);
                if ($Name === false) {
                    $Name =$CallEvent[3];
                }
                $Calls[$CallEvent[2]]['Name'] = $Name;
                //$WFCData['Text'] = sprintf($WFCData['Text'], (string)$CallEvent[3], (string)$CallEvent[4], $Name);
                /*if ($User_Script <> 0) {
                    IPS_RunScriptEx($User_Script, FB_CopyArray($Calls[$CallEvent[2]], 'CALL'));
                }*/
            break;
            case "CALL": //Abgehend
                //$WFCData =	$WFC_Notify[$CallEvent[1]];
                $Calls[$CallEvent[2]]['Typ'] ='CALLOUT';
                //$Calls[$CallEvent[2]]['Device'] = FB_GetPhoneDevice((int)$CallEvent[3]);
                $Calls[$CallEvent[2]]['DeviceID'] =(int)$CallEvent[3];
                $Calls[$CallEvent[2]]['Local'] =$CallEvent[4];
                $Calls[$CallEvent[2]]['Remote'] =$CallEvent[5];
                $Calls[$CallEvent[2]]['Line'] =$CallEvent[6];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                //$Name = FB_InversSuche($CallEvent[5], $Config['SucheType']);
                if ($Name === false) {
                    $Name =$CallEvent[5];
                }
                $Calls[$CallEvent[2]]['Name'] = $Name;
                //$WFCData['Text'] = sprintf($WFCData['Text'], (string)$CallEvent[4], (string)$CallEvent[5], $Name);
                /*if ($User_Script <> 0) {
                    IPS_RunScriptEx($User_Script, FB_CopyArray($Calls[$CallEvent[2]], 'CALL'));
                }*/
            break;
            case "CONNECT": // Verbunden
                //$WFCData =	$WFC_Notify[$CallEvent[1].'_'.$Calls[$CallEvent[2]]['Typ']];
                $Calls[$CallEvent[2]]['Time'] =$CallEvent[0];
                //$Calls[$CallEvent[2]]['Device'] =FB_GetPhoneDevice($CallEvent[3]);
                $Calls[$CallEvent[2]]['DeviceID'] =(int)$CallEvent[3];
                //$WFCData['Text'] = sprintf($WFCData['Text'], $Calls[$CallEvent[2]]['Device'], $Calls[$CallEvent[2]]['Remote'], $Calls[$CallEvent[2]]['Name']);
                /*if ($User_Script <> 0) {
                    IPS_RunScriptEx($User_Script, FB_CopyArray($Calls[$CallEvent[2]], 'CALL'));
                }*/
            break;
            case "DISCONNECT": // Getrennt
                //$WFCData =	$WFC_Notify[$CallEvent[1].'_'.$Calls[$CallEvent[2]]['Typ']];
                $time = $this->ConvertRuntime((int)$CallEvent[3]);
                $Calls[$CallEvent[2]]['ConnectTime'] =(int)$CallEvent[3];
                //$WFCData['Text'] = sprintf($WFCData['Text'], $Calls[$CallEvent[2]]['Device'], $Calls[$CallEvent[2]]['Remote'], $Calls[$CallEvent[2]]['Name'], $time);
                /*if ($User_Script <> 0) {
                    IPS_RunScriptEx($User_Script, FB_CopyArray($Calls[$CallEvent[2]], 'CALL'));
                }
                */
                unset($Calls[$CallEvent[2]]);
            break;
        }
        $Calls = $this->CallData = $Calls;
        IPS_RunScriptText('IPS_RequestAction('.$this->InstanceID.',\'RefreshCallList\',true);');
        return true;
    }
}
