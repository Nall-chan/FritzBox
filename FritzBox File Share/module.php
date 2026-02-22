<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
class FritzBoxFileShare extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;

    protected const Is_File = 0;
    protected const Is_Dir = 1;
    protected const Is_InValid = 10;
    protected const Is_Valid = 11;
    protected static $ControlUrlArray = [
        '/upnp/control/x_filelinks'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Filelinks:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->RegisterPropertyInteger('RefreshInterval', 3600);
        $this->RegisterPropertyBoolean('SharesAsTable', false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterPropertyString('Icons', json_encode($Style['Icons']));
        $this->RegisterTimer('RefreshState', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshState",true);');
    }

    public function Destroy()
    {
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval('RefreshState', 0);
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean('SharesAsTable')) {
            $this->RegisterVariableString('SharesTable', $this->Translate('File shares'), '~HTMLBox', -3);

            $this->RefreshFileShareTable();
            $this->SetTimerInterval('RefreshState', $this->ReadPropertyInteger('RefreshInterval') * 1000);
        } else {
            $this->UnregisterVariable('SharesTable');
            $this->SetTimerInterval('RefreshState', 0);
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case 'RefreshState':
                return $this->RefreshFileShareTable();
            case 'SharesAsTable':
                $this->UpdateFormField('RefreshInterval', 'enabled', $Value);
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

        if (!$this->ReadPropertyBoolean('SharesAsTable')) {
            $Form['elements'][1]['items'][0]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][0]['items'][0]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][0]['items'][1]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][1]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][2]['enabled'] = false;
            $Form['elements'][1]['items'][1]['items'][3]['enabled'] = false;
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function RefreshFileShareTable()
    {
        $Table = $this->ReadPropertyBoolean('SharesAsTable');
        if (!$Table) {
            return false;
        }
        $Data = $this->GetShareList();
        if (!$Data) {
            return false;
        }
        return $this->CreateHostHTMLTable($Data);
    }

    public function GetNumberOfFilelinkEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return $result;
    }

    public function NewFilelinkEntry(
        string $Path,
        int $AccessCountLimit,
        int $Expire
    ) {
        $result = $this->Send(__FUNCTION__, [
            'NewPath'               => $Path,
            'NewAccessCountLimit'   => $AccessCountLimit,
            'NewExpire'             => $Expire
        ]);
        return $result;
    }

    public function SetFilelinkEntry(
        string $ID,
        int $AccessCountLimit,
        int $Expire
    ) {
        $result = $this->Send(__FUNCTION__, [
            'NewID'                    => $ID,
            'NewAccessCountLimit'      => $AccessCountLimit,
            'NewExpire'                => $Expire
        ]);
        return $result;
    }

    public function GetGenericFilelinkEntry(int $Index)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIndex'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetSpecificFilelinkEntry(string $Id)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewID'=> $Id
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function DeleteFilelinkEntry(string $Id)
    {
        $result = $this->Send(__FUNCTION__, [
            'NewID'=> $Id
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetFilelinkListPath()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    private function GetShareList()
    {
        if ($this->ParentID == 0) {
            return false;
        }

        $File = $this->GetFilelinkListPath();
        if ($File === false) {
            return false;
        }

        $XMLData = $this->LoadAndGetData($File);
        if ($XMLData === false) {
            return false;
        }
        try {
            $FileList = new \simpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->SendDebug('XML decode trace', $th->getTrace(), 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
            return false;
        }
        $TableData = [];
        /** @var \SimpleXMLElement $FileItem */
        foreach ($FileList as $FileItem) {
            $Filename = explode('/', (string) $FileItem->Path);
            $FileItem->addChild('Filename', end($Filename));

            if ((int) $FileItem->IsDirectory) {
                $FileItem->addChild('Icon', '<div class="Icon' . $this->InstanceID . self::Is_Dir . '"></div>');
            } else {
                $FileItem->addChild('Icon', '<div class="Icon' . $this->InstanceID . self::Is_File . '"></div>');
            }
            if ((int) $FileItem->Valid) {
                $FileItem->Valid = '<div class="Icon' . $this->InstanceID . self::Is_Valid . '"></div>';
            } else {
                $FileItem->Valid = '<div class="Icon' . $this->InstanceID . self::Is_InValid . '"></div>';
            }
            if ((int) $FileItem->AccessCountLimit) {
                $FileItem->AccessCount = (string) $FileItem->AccessCount . '/' . $FileItem->AccessCountLimit;
            }
            if ((int) $FileItem->Expire) {
                $Expires = new DateTime((string) $FileItem->ExpireDate);
                $Expires->setTimezone(new DateTimeZone(date_default_timezone_get()));
                $FileItem->ExpireDate = $Expires->format('j.n.Y H:i:s');
            } else {
                $FileItem->ExpireDate = $this->Translate('Never');
            }
            $TableData[] = (array) $FileItem;
        }
        $this->SendDebug('Table', $TableData, 0);
        return $TableData;
    }

    private function CreateHostHTMLTable(array $TableData)
    {
        $Path = array_column($TableData, 'Path');
        array_multisort($Path, SORT_ASC, SORT_LOCALE_STRING, $TableData);
        $HTML = $this->GetTable($TableData, '', '', '', -1, true);

        $Config_Icons = json_decode($this->ReadPropertyString('Icons'), true);
        $Icon_CSS = '<div id="scoped-content"><style type="text/css" scoped>' . "\r\n";
        foreach ($Config_Icons as $Config_Icon) {
            $ImageData = @getimagesize('data://text/plain;base64,' . $Config_Icon['icon']);
            if ($ImageData === false) {
                continue;
            }
            $Icon_CSS .= '.Icon' . $this->InstanceID . $Config_Icon['type'] . ' {width:100%;height:' . $ImageData[1] . 'px;background:url(' . 'data://' . $ImageData['mime'] . ';base64,' . $Config_Icon['icon'] . ') no-repeat ' . $Config_Icon['align'] . ' center;' . $Config_Icon['style'] . '}' . "\r\n";
        }
        $Icon_CSS .= '</style>';

        $this->SetValue('SharesTable', $Icon_CSS . $HTML);
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

            ],
            [
                'index'   => 1,
                'key'     => 'ID',
                'name'    => $this->Translate('ID'),
                'show'    => false,
                'width'   => 75,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index'   => 2,
                'key'     => 'Path',
                'name'    => $this->Translate('Path'),
                'show'    => false,
                'width'   => 200,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index'   => 3,
                'key'     => 'Filename',
                'name'    => $this->Translate('Filename'),
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
                'key'     => 'AccessCount',
                'name'    => $this->Translate('Access counter'),
                'show'    => true,
                'width'   => 75,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index'   => 5,
                'key'     => 'ExpireDate',
                'name'    => $this->Translate('Expire date'),
                'show'    => true,
                'width'   => 110,
                'hrcolor' => -1,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'center',
                'tdstyle' => ''
            ],
            [
                'index'   => 6,
                'key'     => 'Valid',
                'name'    => $this->Translate('Valid'),
                'show'    => true,
                'width'   => 35,
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

        $NewIcons = [
            [
                'type'          => self::Is_File,
                'DisplayName'   => $this->Translate('File'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/file.png')),
                'align'         => 'right',
                'style'         => ''
            ],
            [
                'type'          => self::Is_Dir,
                'DisplayName'   => $this->Translate('Directory'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/folder.png')),
                'align'         => 'right',
                'style'         => ''
            ],
            [
                'type'          => self::Is_InValid,
                'DisplayName'   => $this->Translate('Invalid link'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/delete.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Is_Valid,
                'DisplayName'   => $this->Translate('Valid link'),
                'icon'          => base64_encode(file_get_contents(__DIR__ . '/../imgs/msgold.png')),
                'align'         => 'center',
                'style'         => ''
            ]
        ];

        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig, 'Icons' => $NewIcons];
    }
}

