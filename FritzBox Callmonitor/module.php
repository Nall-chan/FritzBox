<?php
declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

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
        return true;
    }
}
