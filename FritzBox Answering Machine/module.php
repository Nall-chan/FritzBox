<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxAnsweringMachine {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTable.php';

/**
 * Ein Anrufbeantworter (TAM) der FritzBox, je Anrufbeantworter eine Instanz (Eigenschaft TAMIndex).
 * Schalter (aktiv), Anzahl neuer und aller Nachrichten, optional eine HTML-Box mit den Nachrichten.
 * Abspielen, als gelesen markieren und Löschen aus der Tabelle laufen über einen Webhook der Instanz.
 *
 * @property array $MessagePaths Pfade der Audiodateien [Nachrichten-Index]
 * @property array $MessagesNew Nachricht war beim letzten Abruf neu [Nachrichten-Index]
 * @method void RegisterHook(string $WebHook)
 * @method void UnregisterHook(string $WebHook)
 */
class FritzBoxAnsweringMachine extends FritzBoxModulBase
{
    use \FritzBoxModul\HTMLTable;
    use \FritzBoxAnsweringMachine\WebhookHelper;

    /** Anzahl der möglichen Anrufbeantworter (Index 0 bis 4) laut AVM. */
    public const MaxTAM = 5;

    protected static $ControlUrlArray = [
        '/upnp/control/x_tam'
    ];
    protected static $EventSubURLArray = [];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_TAM:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->MessagePaths = [];
        $this->MessagesNew = [];
        $this->RegisterPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex, 0);
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 300);
        $this->RegisterPropertyBoolean(\FritzBox\AnsweringMachine\Property::MessagesAsTable, true);
        $Style = $this->GenerateHTMLStyleProperty();
        $this->RegisterPropertyString(\FritzBox\Table\Property::Table, json_encode($Style['Table']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Columns, json_encode($Style['Columns']));
        $this->RegisterPropertyString(\FritzBox\Table\Property::Rows, json_encode($Style['Rows']));
        $this->RegisterAttributeString(\FritzBox\AnsweringMachine\Attribute::HookSecret, '');
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook(\FritzBox\AnsweringMachine\Hook::Prefix . $this->InstanceID);
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        $this->MessagePaths = [];
        $this->MessagesNew = [];
        parent::ApplyChanges();
        if ($this->ReadAttributeString(\FritzBox\AnsweringMachine\Attribute::HookSecret) == '') {
            $this->WriteAttributeString(\FritzBox\AnsweringMachine\Attribute::HookSecret, bin2hex(random_bytes(16)));
        }
        $this->RegisterVariableBoolean(\FritzBox\AnsweringMachine\Variables::Enable, $this->Translate('Answering machine active'), '~Switch', 1);
        $this->EnableAction(\FritzBox\AnsweringMachine\Variables::Enable);
        $this->RegisterVariableInteger(\FritzBox\AnsweringMachine\Variables::NewMessages, $this->Translate('New messages'), '', 2);
        $this->RegisterVariableInteger(\FritzBox\AnsweringMachine\Variables::TotalMessages, $this->Translate('Number of messages'), '', 3);
        if ($this->ReadPropertyBoolean(\FritzBox\AnsweringMachine\Property::MessagesAsTable)) {
            $this->RegisterVariableString(\FritzBox\AnsweringMachine\Variables::Messages, $this->Translate('Messages'), '~HTMLBox', 4);
        } else {
            $this->UnregisterVariable(\FritzBox\AnsweringMachine\Variables::Messages);
        }
        $TAMIndex = $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex);
        if (($TAMIndex < 0) || ($TAMIndex >= self::MaxTAM)) {
            $this->SetStatus(IS_EBASE + 2);
            return;
        }
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        if ($this->ReadPropertyBoolean(\FritzBox\AnsweringMachine\Property::MessagesAsTable)) {
            $this->RegisterHook(\FritzBox\AnsweringMachine\Hook::Prefix . $this->InstanceID);
        } else {
            $this->UnregisterHook(\FritzBox\AnsweringMachine\Hook::Prefix . $this->InstanceID);
        }
        $this->UpdateInfo();
    }

    public function RequestAction($Ident, $Value)
    {
        if (parent::RequestAction($Ident, $Value)) {
            return true;
        }
        switch ($Ident) {
            case \FritzBox\AnsweringMachine\Variables::Enable:
                if (!$this->SetTAMEnable((bool) $Value)) {
                    return false;
                }
                $this->SetValue($Ident, (bool) $Value);
                return true;
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\AnsweringMachine\Property::MessagesAsTable:
                $this->UpdateFormField('HTMLExpansionPanel', 'expanded', $Value);
                $this->UpdateFormField('Table', 'enabled', $Value);
                $this->UpdateFormField('Columns', 'enabled', $Value);
                $this->UpdateFormField('Rows', 'enabled', $Value);
                return;
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Liefert die Daten des Anrufbeantworters dieser Instanz.
     *
     * @return array|false
     */
    public function GetTAMInfo()
    {
        return $this->Send('GetInfo', [
            'NewIndex' => $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex)
        ]);
    }

    /**
     * Schaltet den Anrufbeantworter dieser Instanz ein oder aus.
     *
     * @param bool $Enable true = ein, false = aus
     *
     * @return bool
     */
    public function SetTAMEnable(bool $Enable)
    {
        return $this->Send('SetEnable', [
            'NewIndex'  => $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex),
            'NewEnable' => (int) $Enable
        ]) !== false;
    }

    /**
     * Liefert die Liste aller Anrufbeantworter der FritzBox als XML-String.
     *
     * @return string|false
     */
    public function GetTAMList()
    {
        return $this->Send('GetList');
    }

    /**
     * Liefert die URL zur Nachrichtenliste des Anrufbeantworters (XML, inklusive Session-ID).
     *
     * @return string|false
     */
    public function GetTAMMessageList()
    {
        return $this->Send('GetMessageList', [
            'NewIndex' => $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex)
        ]);
    }

    /**
     * Markiert eine Nachricht als gelesen oder ungelesen.
     *
     * @param int  $MessageIndex Index der Nachricht (Feld Index aus FB_GetTAMMessages)
     * @param bool $MarkedAsRead true = gelesen, false = ungelesen
     *
     * @return bool
     */
    public function MarkTAMMessage(int $MessageIndex, bool $MarkedAsRead)
    {
        if (!$this->IsValidMessageIndex($MessageIndex)) {
            return false;
        }
        return $this->Send('MarkMessage', [
            'NewIndex'        => $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex),
            'NewMessageIndex' => $MessageIndex,
            'NewMarkedAsRead' => (int) $MarkedAsRead
        ]) !== false;
    }

    /**
     * Löscht eine Nachricht. Nicht rückgängig zu machen.
     *
     * @param int $MessageIndex Index der Nachricht (Feld Index aus FB_GetTAMMessages)
     *
     * @return bool
     */
    public function DeleteTAMMessage(int $MessageIndex)
    {
        if (!$this->IsValidMessageIndex($MessageIndex)) {
            return false;
        }
        return $this->Send('DeleteMessage', [
            'NewIndex'        => $this->ReadPropertyInteger(\FritzBox\AnsweringMachine\Property::TAMIndex),
            'NewMessageIndex' => $MessageIndex
        ]) !== false;
    }

    /**
     * Liefert alle Nachrichten des Anrufbeantworters als Array.
     *
     * @return array|false
     */
    public function GetTAMMessages()
    {
        $Url = $this->GetTAMMessageList();
        if ($Url === false) {
            return false;
        }
        $XMLData = $this->LoadAndGetData($Url);
        if ($XMLData === false) {
            return false;
        }
        try {
            $Xml = new \SimpleXMLElement(trim($XMLData));
        } catch (\Throwable $th) {
            $this->SendDebug('XML decode error', $XMLData, 0);
            $this->LogMessage($th->getMessage(), KL_ERROR);
            return false;
        }
        $Messages = [];
        foreach ($Xml->Message as $Message) {
            $Messages[] = [
                'Index'    => (int) $Message->Index,
                'Called'   => (string) $Message->Called,
                'Date'     => (string) $Message->Date,
                'Duration' => (string) $Message->Duration,
                'Inbook'   => (int) $Message->Inbook === 1,
                'Name'     => (string) $Message->Name,
                'New'      => (int) $Message->New === 1,
                'Number'   => (string) $Message->Number,
                'Path'     => (string) $Message->Path
            ];
        }
        return $Messages;
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if (!$this->ReadPropertyBoolean(\FritzBox\AnsweringMachine\Property::MessagesAsTable)) {
            foreach ($Form['elements'][3]['items'] as &$Item) {
                $Item['enabled'] = false;
            }
        }
        return json_encode($Form);
    }

    /**
     * Verarbeitet die Bedienung aus der HTML-Tabelle (Abspielen, Markieren, Löschen).
     */
    protected function ProcessHookData()
    {
        $Type = $_GET['Type'] ?? '';
        $Id = (int) ($_GET['ID'] ?? -1);
        $Secret = (string) ($_GET['Secret'] ?? '');
        $this->SendDebug('Hook', ['Type' => $Type, 'ID' => $Id], 0);
        if (!hash_equals($this->HookSignature($Type, $Id), $Secret)) {
            http_response_code(403);
            echo $this->Translate('Access denied');
            return;
        }
        switch ($Type) {
            case \FritzBox\AnsweringMachine\Hook::Play:
                $this->PlayMessage($Id);
                return;
            case \FritzBox\AnsweringMachine\Hook::Mark:
                if ($this->MarkTAMMessage($Id, true)) {
                    $this->UpdateInfo();
                    echo 'OK';
                    return;
                }
                break;
            case \FritzBox\AnsweringMachine\Hook::Delete:
                if ($this->DeleteTAMMessage($Id)) {
                    $this->UpdateInfo();
                    echo 'OK';
                    return;
                }
                break;
            default:
                http_response_code(400);
                echo $this->Translate('Bad Request');
                return;
        }
        echo $this->Translate('Action failed.');
    }

    private function IsValidMessageIndex(int $MessageIndex): bool
    {
        if ($MessageIndex < 0) {
            $this->ReportError($this->Translate('Invalid message index.'));
            return false;
        }
        return true;
    }

    /**
     * Signatur für einen Aufruf des Webhooks, damit nur Links aus der Tabelle gültig sind.
     */
    private function HookSignature(string $Type, int $Id): string
    {
        $Key = $this->ReadAttributeString(\FritzBox\AnsweringMachine\Attribute::HookSecret);
        return hash_hmac('sha256', $Type . ':' . $Id, $Key);
    }

    private function HookUrl(string $Type, int $Id): string
    {
        return \FritzBox\AnsweringMachine\Hook::Prefix . $this->InstanceID .
            '?Type=' . $Type . '&ID=' . $Id . '&Secret=' . $this->HookSignature($Type, $Id);
    }

    /**
     * Reicht die Audiodatei einer Nachricht direkt von der FritzBox an den Browser durch.
     * Die Datei läuft nicht über den Datenaustausch mit der IO. Der Pfad aus der Nachrichtenliste
     * ist relativ und ohne Session-ID, beides liefert die URL aus GetMessageList (je Aufruf neu).
     */
    private function PlayMessage(int $Id): void
    {
        $Paths = $this->MessagePaths;
        if (!isset($Paths[$Id])) {
            http_response_code(404);
            echo $this->Translate('Message not found.');
            return;
        }
        $ListUrl = $this->GetTAMMessageList();
        $Url = ($ListUrl === false) ? false : parse_url($ListUrl);
        if (($Url === false) || !isset($Url['scheme'], $Url['host'], $Url['query'])) {
            http_response_code(502);
            echo $this->Translate('Action failed.');
            return;
        }
        parse_str($Url['query'], $Query);
        $BaseUrl = $Url['scheme'] . '://' . $Url['host'] . (isset($Url['port']) ? ':' . $Url['port'] : '');
        $Context = stream_context_create([
            'http' => ['timeout' => 15],
            'ssl'  => ['verify_peer' => false, 'verify_peer_name' => false]
        ]);
        $Stream = @fopen($BaseUrl . $Paths[$Id] . '&sid=' . ($Query['sid'] ?? ''), 'rb', false, $Context);
        if ($Stream === false) {
            $this->SendDebug('Play', 'Error on load file', 0);
            http_response_code(502);
            echo $this->Translate('Action failed.');
            return;
        }
        header('Content-Type: audio/x-wav');
        header('Cache-Control: no-cache');
        fpassthru($Stream);
        fclose($Stream);
        $MessagesNew = $this->MessagesNew;
        if (!empty($MessagesNew[$Id])) {
            $this->MarkTAMMessage($Id, true);
            $this->UpdateInfo();
        }
    }

    private function UpdateInfo()
    {
        $Info = $this->GetTAMInfo();
        if ($Info === false) {
            return false;
        }
        $this->SetValue(\FritzBox\AnsweringMachine\Variables::Enable, (int) $Info['NewEnable'] === 1);
        $Messages = $this->GetTAMMessages();
        if ($Messages === false) {
            return false;
        }
        $this->SetValue(\FritzBox\AnsweringMachine\Variables::NewMessages, count(array_filter(array_column($Messages, 'New'))));
        $this->SetValue(\FritzBox\AnsweringMachine\Variables::TotalMessages, count($Messages));
        $Paths = [];
        $NewFlags = [];
        $TableData = [];
        foreach ($Messages as $Message) {
            $Paths[$Message['Index']] = $Message['Path'];
            $NewFlags[$Message['Index']] = $Message['New'];
            $TableData[] = $this->GetTableRow($Message);
        }
        $this->MessagePaths = $Paths;
        $this->MessagesNew = $NewFlags;
        if ($this->ReadPropertyBoolean(\FritzBox\AnsweringMachine\Property::MessagesAsTable)) {
            $this->SetValue(\FritzBox\AnsweringMachine\Variables::Messages, $this->GetTable($TableData));
        }
        return true;
    }

    /**
     * Erzeugt eine Zeile der HTML-Tabelle inklusive der Schaltflächen.
     */
    private function GetTableRow(array $Message): array
    {
        $Id = $Message['Index'];
        $ButtonStyle = 'cursor:pointer;padding:0 0.4em;';
        $Play = '<span style="' . $ButtonStyle . '" title="' . $this->Translate('Play') . '" onclick="' .
            'var a=window.fbTam' . $this->InstanceID . ";if(a){a.pause();}a=new Audio('" . $this->HookUrl(\FritzBox\AnsweringMachine\Hook::Play, $Id) . "');" .
            'window.fbTam' . $this->InstanceID . '=a;a.play();">&#9654;</span>';
        $Fetch = "fetch('%s').then(function(r){return r.text();}).then(function(t){if(t!=='OK'){alert(t);}});";
        $Mark = '';
        if ($Message['New']) {
            $Mark = '<span style="' . $ButtonStyle . '" title="' . $this->Translate('Mark as read') . '" onclick="' .
                sprintf($Fetch, $this->HookUrl(\FritzBox\AnsweringMachine\Hook::Mark, $Id)) . '">&#10003;</span>';
        }
        $Delete = '<span style="' . $ButtonStyle . '" title="' . $this->Translate('Delete') . '" onclick="' .
            "if(confirm('" . $this->Translate('Delete message?') . "')){" .
            sprintf($Fetch, $this->HookUrl(\FritzBox\AnsweringMachine\Hook::Delete, $Id)) . '}">&#10006;</span>';
        $Name = $Message['Name'];
        if ($Name == '') {
            $Name = ($Message['Number'] != '') ? $Message['Number'] : $this->Translate('unknown');
        }
        return [
            'Date'     => htmlspecialchars($Message['Date']),
            'Name'     => htmlspecialchars($Name),
            'Number'   => htmlspecialchars($Message['Number']),
            'Called'   => htmlspecialchars($Message['Called']),
            'Duration' => htmlspecialchars($Message['Duration']),
            'New'      => $Message['New'] ? '&#9679;' : '',
            'Actions'  => $Play . $Mark . $Delete
        ];
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
        $Columns = [
            ['New', '', true, 20, 'center'],
            ['Date', 'Date', true, 100, 'left'],
            ['Name', 'Name', true, 200, 'left'],
            ['Number', 'Caller', true, 120, 'left'],
            ['Called', 'Called number', false, 120, 'left'],
            ['Duration', 'Duration', true, 50, 'center'],
            ['Actions', '', true, 80, 'right']
        ];
        $NewColumnsConfig = [];
        foreach ($Columns as $Index => $Column) {
            $NewColumnsConfig[] = [
                'index'   => $Index,
                'key'     => $Column[0],
                'name'    => $Column[1] == '' ? '' : $this->Translate($Column[1]),
                'show'    => $Column[2],
                'width'   => $Column[3],
                'hrcolor' => -1,
                'hralign' => $Column[4],
                'hrstyle' => '',
                'tdalign' => $Column[4],
                'tdstyle' => ''
            ];
        }
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
        return ['Table' => $NewTableConfig, 'Columns' => $NewColumnsConfig, 'Rows' => $NewRowsConfig];
    }
}
