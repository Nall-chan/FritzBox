<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * Dateifreigaben (Filelinks) der FritzBox, optional als HTML-Tabelle.
 * Die Instanz-Kachel (HTML-SDK) zeigt die Freigaben (unabhängig von der HTML-Box) mit Link kopieren und Löschen.
 *
 * @property array $TileShares Freigaben für die Kachel (letzter Abruf)
 */
class FritzBoxFileShare extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;
    use \FritzBoxModul\Tile;

    protected const Is_File = 0;
    protected const Is_Dir = 1;
    protected const Is_InValid = 10;
    protected const Is_Valid = 11;
    /** Index von X_AVM-DE_MyFritz in $ServiceTypeArray (Adresse der Box im Internet für vollständige Links) */
    private const MyFritzIndex = 1;
    protected static $ControlUrlArray = [
        '/upnp/control/x_filelinks',
        '/upnp/control/x_myfritz'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Filelinks:1',
        'urn:dslforum-org:service:X_AVM-DE_MyFritz:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->TileShares = [];
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 3600);
        $this->RegisterPropertyBoolean(\FritzBox\FileShare\Property::SharesAsTable, false);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\FritzBox\Table\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Rows, json_encode($Style['Rows']));
        $this->RegisterPropertyString(\FritzBox\FileShare\Property::Icons, json_encode($Style['Icons']));
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshState, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshState . '",true);');
    }

    public function Destroy(): void
    {
        parent::Destroy();
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, 0);
        $this->TileShares = [];
        parent::ApplyChanges();
        $this->RegisterTile();
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean(\FritzBox\FileShare\Property::SharesAsTable)) {
            $this->RegisterVariableString(\FritzBox\FileShare\Variables::SharesTable, $this->Translate('File shares'), $this->GetPresentation('~HTMLBox', VARIABLETYPE_STRING), -3);
        } else {
            $this->UnregisterVariable(\FritzBox\FileShare\Variables::SharesTable);
        }
        // Die Freigaben werden auch ohne HTML-Box geladen, die Kachel zeigt sie
        $this->RefreshFileShareTable();
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshState, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }

        if (!$this->ReadPropertyBoolean(\FritzBox\FileShare\Property::SharesAsTable)) {
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

    public function RefreshFileShareTable(): mixed
    {
        $Data = $this->GetShareList();
        if ($Data === false) {
            return false;
        }
        $this->PushTile();
        if (!$this->ReadPropertyBoolean(\FritzBox\FileShare\Property::SharesAsTable)) {
            return true;
        }
        return $this->CreateHostHTMLTable($Data);
    }

    public function GetNumberOfFilelinkEntries(): int|false
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }

        return (int) $result;
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
        return $result !== false;
    }

    public function GetGenericFilelinkEntry(int $Index): mixed
    {
        $result = $this->Send(__FUNCTION__, [
            'NewIndex'=> $Index
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function GetSpecificFilelinkEntry(string $Id): mixed
    {
        $result = $this->Send(__FUNCTION__, [
            'NewID'=> $Id
        ]);
        if ($result === false) {
            return false;
        }
        return $result;
    }

    public function DeleteFilelinkEntry(string $Id): bool
    {
        $result = $this->Send(__FUNCTION__, [
            'NewID'=> $Id
        ]);
        return $result !== false;
    }

    public function GetFilelinkListPath(): mixed
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
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
            case \FritzBox\Tile\Action::DeleteShare:
                if (!in_array((string) $Value, array_column($this->TileShares, 'id'), true)) {
                    trigger_error($this->Translate('Unknown file share.'), E_USER_NOTICE);
                    return false;
                }
                if (!$this->DeleteFilelinkEntry((string) $Value)) {
                    return false;
                }
                return $this->RefreshFileShareTable();
            case \FritzBox\Base\Action::RefreshState:
                return $this->RefreshFileShareTable();
            case \FritzBox\FileShare\Action::SharesAsTable:
                $this->UpdateFormField('Icons', 'enabled', $Value);
                $this->UpdateFormField('ShowImage', 'enabled', $Value);
                $this->UpdateFormField('Table', 'enabled', $Value);
                $this->UpdateFormField('Columns', 'enabled', $Value);
                $this->UpdateFormField('Rows', 'enabled', $Value);
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', $Value);
                return true;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: Freigaben des letzten Abrufs.
     */
    protected function GetTileData(): array
    {
        return ['shares' => $this->TileShares];
    }

    protected function GetTileTextKeys(): array
    {
        return ['File shares', 'No file shares', 'Open link', 'Copy link', 'Link copied', 'Delete', 'Delete the file share %s?', 'Accesses', 'Expires', 'Never', 'Invalid'];
    }

    /**
     * Adresse der FritzBox im Internet über MyFRITZ! (https://<Name>.myfritz.net[:Port]), sonst leer.
     * Optionale Abfrage: Fehler nur im Debug.
     */
    private function MyFritzBaseUrl(): string
    {
        $Info = $this->SendExQuiet('GetInfo', self::MyFritzIndex);
        if (!is_array($Info) || ((int) ($Info['NewEnabled'] ?? 0) != 1) || ((string) ($Info['NewDynDNSName'] ?? '') == '')) {
            $this->SendDebug('MyFRITZ', 'not available, no complete links', 0);
            return '';
        }
        $Port = (int) ($Info['NewPort'] ?? 443);
        return 'https://' . $Info['NewDynDNSName'] . (($Port > 0) && ($Port != 443) ? ':' . $Port : '');
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
        $TileShares = [];
        // Die Liste enthält nur den Pfad (/nas/filelink.lua?id=…); vollständig erst mit der MyFRITZ!-Adresse
        $BaseUrl = $this->MyFritzBaseUrl();
        /** @var \SimpleXMLElement $FileItem */
        foreach ($FileList as $FileItem) {
            $Filename = explode('/', (string) $FileItem->Path);
            $Expires = '';
            if ((int) $FileItem->Expire) {
                $Date = new DateTime((string) $FileItem->ExpireDate);
                $Expires = (string) $Date->getTimestamp();
            }
            $TileShares[] = [
                'id'      => (string) $FileItem->ID,
                'path'    => (string) $FileItem->Path,
                'name'    => end($Filename),
                'dir'     => (int) $FileItem->IsDirectory == 1,
                'valid'   => (int) $FileItem->Valid == 1,
                'url'     => (($BaseUrl != '') && ((string) $FileItem->Url != '')) ? $BaseUrl . (string) $FileItem->Url : '',
                'count'   => (int) $FileItem->AccessCount,
                'limit'   => (int) $FileItem->AccessCountLimit,
                'expires' => (int) $Expires
            ];
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
        $Path = array_column($TileShares, 'path');
        array_multisort($Path, SORT_ASC, SORT_LOCALE_STRING, $TileShares);
        $this->TileShares = $TileShares;
        return $TableData;
    }

    private function CreateHostHTMLTable(array $TableData)
    {
        $Path = array_column($TableData, 'Path');
        array_multisort($Path, SORT_ASC, SORT_LOCALE_STRING, $TableData);
        $HTML = $this->GetTable($TableData, '', '', '', -1, true);

        $Config_Icons = json_decode($this->ReadPropertyString(\FritzBox\FileShare\Property::Icons), true);
        $Icon_CSS = '<div id="scoped-content"><style type="text/css" scoped>' . "\r\n";
        foreach ($Config_Icons as $Config_Icon) {
            $ImageData = @getimagesize('data://text/plain;base64,' . $Config_Icon['icon']);
            if ($ImageData === false) {
                continue;
            }
            $Icon_CSS .= '.Icon' . $this->InstanceID . $Config_Icon['type'] . ' {width:100%;height:' . $ImageData[1] . 'px;background:url(' . 'data://' . $ImageData['mime'] . ';base64,' . $Config_Icon['icon'] . ') no-repeat ' . $Config_Icon['align'] . ' center;' . $Config_Icon['style'] . '}' . "\r\n";
        }
        $Icon_CSS .= '</style>';

        $this->SetValue(\FritzBox\FileShare\Variables::SharesTable, $Icon_CSS . $HTML);
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

