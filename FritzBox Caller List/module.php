<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * @property array $PhonebookFiles
 */
class FritzBoxCallerList extends FritzBoxModulBase
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
        $this->RegisterPropertyInteger('RefreshIntervalPhonebook', 60);
        $this->RegisterPropertyInteger('RefreshIntervalCallList', 60);
        $this->RegisterTimer('RefreshPhonebook', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshPhonebook",true);');
        $this->RegisterTimer('RefreshCallList', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"RefreshCallList",true);');
        $this->PhonebookFiles=[];
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString('Table', json_encode($Style['Table']));
        $this->RegisterPropertyString('Columns', json_encode($Style['Columns']));
        $this->RegisterPropertyString('Rows', json_encode($Style['Rows']));
        $this->RegisterPropertyString('Icons', json_encode($Style['Icons']));

        $this->RegisterPropertyInteger('ReverseSearch', 1);
        $this->RegisterPropertyInteger('CustomSerachScript', 0);

        $this->RegisterPropertyInteger('LoadListType', 2);
        $this->RegisterPropertyInteger('LastEntries', 20);
        
        $this->RegisterPropertyInteger('MaxNameSize', 30);
        $this->RegisterPropertyString('SearchMarker', '(*)');
        $this->RegisterPropertyString('UnknownNumberName', $this->Translate('(unknown)'));

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
        $this->SetTimerInterval('RefreshPhonebook', $this->ReadPropertyInteger('RefreshIntervalPhonebook')*60000);
        $this->SetTimerInterval('RefreshCallList', $this->ReadPropertyInteger('RefreshIntervalCallList')*60000);
        /*$this->GetDECTHandsetList();
        $this->GetDECTHandsetInfo(1);
        $this->GetDECTHandsetInfo(2);*/
        //$this->GetNumberOfDeflections();
        // GetDeflection
        //$this->GetDeflections();
        // SetDeflectionEnable
        $this->RegisterVariableString('CallerList', $this->Translate('Caller list'), '~HTMLBox', 0);
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
    private function RefreshCallList()
    {
        $result = $this->GetCallList();
        if ($result === false) {
            return false;
        }
        $entries = $this->ReadPropertyInteger('LastEntries');
        switch ($this->ReadPropertyInteger('LoadListType')) {
            case 1:
                $result.='&days='.$entries;
                break;
            case 2:
                $result.='&max='.$entries;
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
        $Data=[];
        $UnknownName=$this->ReadPropertyString('UnknownNumberName');
        $MaxNameSize=$this->ReadPropertyInteger('MaxNameSize');
        for ($i=0;$i<count($CallList->Call);$i++) {
            // Eigene Nummer bereinigen, entfernt z.B. ISDN: POTS: SIP: etc...
            $Data[$i]['Name']=(string)$CallList->Call[$i]->Name;
            if ((int)$CallList->Call[$i]->Type == self::Call_Outgoing) {
                $Data[$i]['Caller'] = str_replace(strtoupper((string)$CallList->Call[$i]->Numbertype).": ", "", (string)$CallList->Call[$i]->Caller);
                $Data[$i]['Called'] = (string)$CallList->Call[$i]->Called;
            } else {
                $Data[$i]['Caller'] = (string)$CallList->Call[$i]->Caller;
                $Data[$i]['Called'] = str_replace(strtoupper((string)$CallList->Call[$i]->Numbertype).": ", "", (string)$CallList->Call[$i]->Called);
                if ($Data[$i]['Caller'] =='') {
                    $Data[$i]['Name']=$UnknownName;
                }
            }
            //$CallList->Call[$i]->addChild("Fax"); // leeren FAX Eintrag erzeugen.
            $Data[$i]['Fax']='';
            // Fax-Anruf ?
            if ((int)$CallList->Call[$i]->Port == 5) {
                $Data[$i]['Type']= self::Call_Tam_Deleted; // vorbelegen mit Fax schon gelöscht
                $Data[$i]['Called'] = (string)$CallList->Call[$i]->Device;
                $Data[$i]['Duration'] = "---";  // Warum auch immer ist die Dauer immer 0:01 auch bei FAX

                if (strlen((string)$CallList->Call[$i]->Path) <> 0) {
                    $Data[$i]['Fax'] = "1"; // FAX-Eintrag ist vorhanden !
                    //$CallList->Call[$i]->Path =  $AB_URL."?fax=".urlencode((string)$CallList->Call[$i]->Path);// URL-Anpassen
                    $Data[$i]['Type'] = self::Call_Tam_Old;
                }
            } else {
                $Data[$i]['Type'] =(int)$CallList->Call[$i]->Type;
                $Data[$i]['Duration'] =(string)$CallList->Call[$i]->Duration;
            }
            $Data[$i]['Icon']='<div class="Icon'.$this->InstanceID.$Data[$i]['Type'].'"></div>';
            $Data[$i]['Date']=(string)$CallList->Call[$i]->Date;
            $Data[$i]['Device']=(string)$CallList->Call[$i]->Device;
            if (strlen($Data[$i]['Name'])>$MaxNameSize) {
                $Data[$i]['Name']=substr($Data[$i]['Name'], 0, $MaxNameSize);
            }
        }
        $Config_Icons = json_decode($this->ReadPropertyString('Icons'), true);
        $Icon_CSS='<div id="scoped-content"><style type="text/css" scoped>'."\r\n";
        foreach ($Config_Icons as $Config_Icon) {
            $ImageData =  @getimagesize('data://text/plain;base64,'.$Config_Icon['icon']);
            if ($ImageData === false) {
                continue;
            }
            $Icon_CSS.='.Icon'.$this->InstanceID.$Config_Icon['type'].' {width:100%;height:'.$ImageData[1].'px;background:url('.'data://'.$ImageData['mime'].';base64,'.$Config_Icon['icon'].') no-repeat '.$Config_Icon['align'].' center;}'."\r\n";
        }
        $Icon_CSS.='</style>'."\r\n";
        $HTML = $this->GetTable($Data).'</div>';
        $this->SetValue('CallerList', $Icon_CSS . $HTML);
        return true;
    }
    private function RefreshPhonebook()
    {
        $result = $this->GetPhonebookList();
        if ($result === false) {
            return false;
        }
        $PhonebookIDs = explode(',', $result);
        $LoadedFiles=[];
        foreach ($PhonebookIDs as $PhonebookID) {
            $PhonebookData = $this->GetPhonebook((int)$PhonebookID);
            if ($PhonebookData === false) {
                continue;
            }
            $FileName = 'Phonebook_'.$PhonebookData['NewPhonebookName'].'.xml';
            if (!$this->LoadAndSaveFile($PhonebookData['NewPhonebookURL'], $FileName)) {
                continue;
            }
            $LoadedFiles[]=$FileName;
        }
        $this->PhonebookFiles=$LoadedFiles;
        return true;
    }
    public function GetInfoByIndex(int $Index)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
            'NewIndex'=> $Index
        ]
        );
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function SetEnableByIndex(int $Index, bool $Enable)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
            'NewIndex' => $Index,
            'NewEnable'=> $Enable
        ]
        );
        if ($result === null) {
            return true;
        }
        return false;
    }
    public function GetNumberOfEntries()
    {
        $result = $this->Send(__FUNCTION__);
        if ($result === false) {
            return false;
        }
        return $result;
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
    public function GetDeflection(int $Index)
    {
        $result = $this->Send(
            __FUNCTION__,
            [
            'NewDeflectionId'=> $Index
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
            'NewEnable'       => $Enable
        ]
        );
        if ($result === false) {
            return false;
        }
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
                'hrcolor' => 0xffffff,
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
                'hrcolor' => 0xffffff,
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
                'hrcolor' => 0xffffff,
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
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 4,
                'key'   => 'Caller',
                'name'  => $this->Translate('Caller'),
                'show'  => true,
                'width' => 200,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 7,
                'key'   => 'Device',
                'name'  => $this->Translate('Device'),
                'show'  => true,
                'width' => 150,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 5,
                'key'   => 'Called',
                'name'  => $this->Translate('Called'),
                'show'  => true,
                'width' => 100,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 6,
                'key'   => 'Duration',
                'name'  => $this->Translate('Duration'),
                'show'  => true,
                'width' => 80,
                'hrcolor' => 0xffffff,
                'hralign' => 'center',
                'hrstyle' => '',
                'tdalign' => 'left',
                'tdstyle' => ''
            ],
            [
                'index' => 8,
                'key'   => 'Action',
                'name'  => $this->Translate('Fax / TAM'),
                'show'  => false,
                'width' => 50,
                'hrcolor' => 0xffffff,
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
                'bgcolor' => 0x000000,
                'color'   => 0xffffff,
                'style'   => ''
            ],
            [
                'row'     => 'even',
                'name'    => $this->Translate('even'),
                'bgcolor' => 0x080808,
                'color'   => 0xffffff,
                'style'   => ''
            ]
        ];
        $NewIcons=[
            [
                'type'          => self::Call_Incoming,
                'DisplayName'   => $this->Translate('Incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Missed,
                'DisplayName'   => $this->Translate('Missed call'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callinfailed.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Tam_New,
                'DisplayName'   => $this->Translate('New message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/msgnew.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Tam_Old,
                'DisplayName'   => $this->Translate('Old message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/msgold.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Tam_Deleted,
                'DisplayName'   => $this->Translate('Deleted message'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/delete.png')),
                'align'         => 'center',
                'style'         =>''
            ],
            [
                'type'          => self::Call_Active_Incoming,
                'DisplayName'   => $this->Translate('Incoming (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callin.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Rejected_Incoming,
                'DisplayName'   => $this->Translate('Rejected call'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callinfailed.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Active_Outgoing,
                'DisplayName'   => $this->Translate('Outgoing (active)'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/callout.png')),
                'align'         => 'center',
                'style'         => ''
            ],
            [
                'type'          => self::Call_Fax,
                'DisplayName'   => $this->Translate('Fax incoming'),
                'icon'          => base64_encode(file_get_contents(__DIR__.'/imgs/msgfax.png')),
                'align'         => 'center',
                'style'         => ''
            ]
        ];
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig, 'Icons'=> $NewIcons];
    }
}
