<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/SemaphoreHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxModule.php';
/**
 * @property string $Url
 * @property string $Username
 * @property bool $ForceLoadXML
 * @method void RegisterHook(string $WebHook)
 * @method void UnregisterHook(string $WebHook)
 * @method void RegisterAttributeArray(string $name, mixed $Value, int $Size = 0)
 * @method mixed ReadAttributeArray(string $name)
 * @method void WriteAttributeArray(string $name, mixed $value)
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class FritzBoxIO extends IPSModule
{
    use \FritzBoxIO\AttributeArrayHelper;
    use \FritzBoxIO\BufferHelper;
    use \FritzBoxIO\DebugHelper;
    use \FritzBoxIO\WebhookHelper;
    use \FritzBoxIO\Semaphore;

    public const isConnected = IS_ACTIVE;
    public const isInActive = IS_INACTIVE;
    public const isDisconnected = IS_EBASE + 1;
    public const isUnauthorized = IS_EBASE + 2;
    public const isURLnotValid = IS_EBASE + 3;
    public const isServicenotValid = IS_EBASE + 4;

    private static $http_error =
        [
            418 => ['Could not connect to host, maybe i am a teapot?', self::isDisconnected],
            412 => ['Precondition Failed!', self::isConnected],
            404 => ['Service not Found', self::isServicenotValid],
            401 => ['Unauthorized', self::isUnauthorized],
            500 => ['UPnPError', self::isDisconnected],
            501 => ['Webhook invalid', self::isDisconnected]
        ];

    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyBoolean('Open', true);
        $this->RegisterPropertyString('Host', 'http://');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('ReturnIP', '');
        $this->RegisterPropertyInteger('ReturnPort', 3777);

        $this->RegisterAttributeString('ConsumerAddress', 'Invalid');
        $this->RegisterAttributeArray('Events', []);
        $this->RegisterAttributeArray('PhoneBooks', []);
        $this->RegisterAttributeArray('PhoneDevices', []);
        $this->RegisterAttributeArray('AreaCodes', []);
        $this->RegisterAttributeBoolean('usePPP', false);
        $this->RegisterAttributeBoolean('HasIGD2', false);
        $this->RegisterAttributeBoolean('HasTel', false);
        $this->RegisterAttributeInteger('NoOfWlan', 0);

        $this->Url = '';
        $this->Username = '';
        //$this->RequireParent("{6179ED6A-FC31-413C-BB8E-1204150CF376}");
        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->ForceLoadXML = true;
            $this->RegisterMessage($this->InstanceID, FM_CHILDADDED);
            $this->CreateTempDir();
        } else {
            $this->RegisterMessage(0, IPS_KERNELMESSAGE);
            $this->ForceLoadXML = false;
        }
    }

    public function Destroy()
    {
        if (!IPS_InstanceExists($this->InstanceID)) {
            $this->UnregisterHook('/hook/FritzBoxIO' . $this->InstanceID);
            @array_map('unlink', glob(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/*.*'));
            @rmdir(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID);
        }
        //Never delete this line!
        parent::Destroy();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        switch ($Message) {
            case IPS_KERNELMESSAGE:
                if ($Data[0] == KR_READY) {
                    IPS_RequestAction($this->InstanceID, 'KernelReady', true);
                }
                break;
            case FM_CHILDADDED:
                if (IPS_GetInstance($Data[0])['ModuleInfo']['ModuleID'] == key(\FritzBox\Services::$Data['callmonitor'])) {
                    $this->CreateCallMonitorCS();
                }
                break;
        }
    }

    public function GetConfigurationForParent()
    {
        return json_encode(['Host'=>parse_url($this->Url, PHP_URL_HOST), 'Port' => 1012]);
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident == 'KernelReady') {
            return $this->KernelReady();
        }
    }

    public function ApplyChanges()
    {
        $OldUrl = $this->Url;

        //Never delete this line!
        parent::ApplyChanges();
        if (IPS_GetKernelRunlevel() != KR_READY) { // IPS läuft dann gleich Daten abholen
            return;
        }
        $this->RegisterHook('/hook/FritzBoxIO' . $this->InstanceID);
        $this->SetStatus(IS_INACTIVE);
        if ($this->CheckHost()) {
            $this->SetSummary($this->Url);
            if ($this->ReadPropertyString('Password') == '') {
                return;
            }
            if (($this->Url != $OldUrl) || ($this->ForceLoadXML)) {
                if ($this->LoadXMLs()) {
                    $this->ForceLoadXML = false;
                } else {
                    $this->ShowLastError(self::$http_error[418][0]);
                    $this->SetStatus(self::isURLnotValid);
                    return;
                }
            }
            if (!$this->GetConsumerAddress()) {
                $this->SetStatus(self::$http_error[501][1]);
                return;
            }
            if ($this->ReadPropertyString('Username') == '') {
                $this->Username = $this->GetLastUser();
            } else {
                $this->Username = $this->ReadPropertyString('Username');
            }
            if (!$this->getWANConnectionTyp($HttpCode)) {
                $this->SetStatus(self::$http_error[$HttpCode][1]);
                $this->ShowLastError(self::$http_error[$HttpCode][0]);
                return;
            }
            if ($this->ReadAttributeBoolean('HasTel')) {
                $this->getAreaCodes();
                $this->checkCallMonitorPort();
            }
            //Todo
            // Eigene Events holen?
            // Prüfen ob Antwort kommt ?
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->Url = '';
            $this->SetSummary('');
        }
    }

    public function ForwardData($JSONString)
    {
        $data = json_decode($JSONString, true);
        switch ($data['Function']) {
            case 'SCPD':
                $ret = $this->ReadAttributeArray('Events');
                break;
            case 'SUBSCRIBE':
                $ret = $this->Subscribe($data['EventSubURL'], $data['SID']);
                break;
            case 'UNSUBSCRIBE':
                $ret = $this->Unsubscribe($data['EventSubURL'], $data['SID']);
                break;
            case 'ISPPP':
                $ret = $this->ReadAttributeBoolean('usePPP');
                break;
            case 'HasIGD2':
                $ret = $this->ReadAttributeBoolean('HasIGD2');
                break;
            case 'HasTel':
                $ret = $this->ReadAttributeBoolean('HasTel');
                break;
            case 'CallMonitorOpen':
                $ret = $this->checkCallMonitorPort();
                break;
            case 'GetMaxWLANs':
                $ret = $this->ReadAttributeInteger('NoOfWlan');
                break;
            case 'RefreshHostList':
                $data['DataID'] = \FritzBox\GUID::RefreshHostListRequest;
                $result = $this->SendDataToChildren(json_encode($data));
                $ret = false;
                if (count($result) > 0) {
                    $ret = $result[0] == 'OK';
                }
                if ($ret) {
                    $data['DataID'] = \FritzBox\GUID::NewHostListEvent;
                    $data['Function'] = 'NewHostListEvent';
                    $ret = $this->SendDataToChildren(json_encode($data));
                }
                break;
            case 'NewHostListEvent':
                $data['DataID'] = \FritzBox\GUID::NewHostListEvent;
                $ret = $this->SendDataToChildren(json_encode($data));
                break;
            case 'LoadAndGetData':
                $ret = $this->LoadAndGetData($data['Uri']);
                break;
            case 'LoadAndSaveFile':
                $ret = $this->LoadAndSaveFile($data['Uri'], $data['Filename']);
                break;
            case 'GetFile':
                $ret = $this->GetFile($data['Filename']);
                break;
            case 'GetAreaCodes':
                $ret = $this->ReadAttributeArray('AreaCodes');
                break;
            case 'SetPhonebooks':
                $this->WriteAttributeArray('PhoneBooks', $data['Files']);
                $ret = true;
                break;
            case 'GetPhonebooks':
                $ret = $this->ReadAttributeArray('PhoneBooks');
                break;
            case 'SetPhoneDevices':
                $this->WriteAttributeArray('PhoneDevices', $data['Devices']);
                $ret = true;
                break;
            case 'GetPhoneDevice':
                $Devices = $this->ReadAttributeArray('PhoneDevices');
                if (array_key_exists($data['DeviceID'], $Devices)) {
                    $ret = $Devices[$data['DeviceID']];
                } else {
                    $ret = '';
                }
                break;
            case 'GetPhoneDevices':
                $ret = $this->ReadAttributeArray('PhoneDevices');
                break;
            default:
                $ret = $this->CallSoapAction($HttpCode, $data['ServiceTyp'], $data['ControlUrl'], $data['Function'], $data['Parameter']);
                break;
        }
        return serialize($ret);
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString);
        $data->DataID = \FritzBox\GUID::CallMonitorEvent;
        $this->SendDebug('Forward', json_encode($data), 0);
        $this->SendDataToChildren(json_encode($data));
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if ($this->ReadPropertyString('Username') == '') {
            $Form['elements'][2]['visible'] = true;
        }

        if (IPS_GetOption('NATSupport')) {
            if (IPS_GetOption('NATPublicIP') == '') {
                if ($this->ReadPropertyString('ReturnIP') == '') {
                    $Form['actions'][1]['visible'] = true;
                    $Form['actions'][1]['popup']['items'][0]['caption'] = $this->Translate('Error');
                    $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate('NAT support is active, but no public address is set.');
                }
            }
        }
        $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
        if (!$Form['actions'][1]['visible']) {
            if (($ConsumerAddress == 'Invalid') && ($this->GetStatus() != 203) && ($this->ReadPropertyBoolean('Open')) && ($this->ReadPropertyString('Password') != '')) {
                $Form['actions'][1]['visible'] = true;
                $Form['actions'][1]['popup']['items'][0]['caption'] = $this->Translate('Error');
                $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate('Couldn\'t determine webhook');
            }
        }
        $Form['actions'][0]['items'][1]['caption'] = $this->Translate($ConsumerAddress);
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    public function Reboot()
    {
        $result = $this->CallSoapAction(
            $HttpCode,
            'urn:dslforum-org:service:DeviceConfig:1',
            '/upnp/control/deviceconfig',
            'Reboot'
        );
        if (is_a($result, 'SoapFault')) {
            return false;
        }
        return true;
    }

    protected function ProcessHookData()
    {
        if ($this->ReadPropertyBoolean('Open') == false) {
            http_response_code(404);
            $this->SendHeaders();
            echo 'File not found!';
            return;
        }
        if ($_SERVER['REQUEST_METHOD'] != 'NOTIFY') {
            http_response_code(405);
            $this->SendHeaders();
            echo 'Method Not Allowed!';
            return;
        }
        /*if (!isset($_GET['eventSubURL'])) {
            http_response_code(400);
            $this->SendHeaders();
            echo 'Bad Request!';
            return;
        }*/
        if (!isset($_SERVER['HTTP_SID'])) {
            http_response_code(400);
            $this->SendHeaders();
            echo 'Bad Request!';
            return;
        }

        $eventSubUrl = substr($_SERVER['REQUEST_URI'], strlen($_SERVER['HOOK']));
        $SID = $_SERVER['HTTP_SID'];

        http_response_code(200);
        $this->SendHeaders();
        $Data = file_get_contents('php://input');
        $this->SendDebug('HOOK', $eventSubUrl, 0);
        $this->SendDebug('EVENT', $Data, 0);
        $xml = new \simpleXMLElement($Data);
        $xml->registerXPathNamespace('event', $xml->getNameSpaces(false)['e']);
        $xmlPropertys = $xml->xpath('//event:property');
        $Propertys = [];
        foreach ($xmlPropertys as $property) {
            $Propertys[str_replace('-', '_', $property->Children()->GetName())] = (string) $property->Children();
        }
        $this->SendDebug('EVENT XML', $Propertys, 0);
        $this->SendDataToChildren(
            json_encode(
                [
                    'DataID'     => \FritzBox\GUID::SendEventToChildren,
                    'EventSubURL'=> $eventSubUrl,
                    'EventData'  => $Propertys
                ]
            )
        );
    }

    private function CreateTempDir()
    {
        if (!is_dir(IPS_GetKErnelDir() . 'FritzBoxTemp')) {
            @mkdir(IPS_GetKErnelDir() . 'FritzBoxTemp');
        }
        if (!is_dir(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID)) {
            @mkdir(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID);
        }
    }

    private function CreateCallMonitorCS()
    {
        if (IPS_GetInstance($this->InstanceID)['ConnectionID'] != 0) {
            return;
        }
        $this->RequireParent(\FritzBox\GUID::ClientSocket);
        $ParentId = IPS_GetInstance($this->InstanceID)['ConnectionID'];
        if ($ParentId > 1) {
            IPS_SetProperty($ParentId, 'Host', parse_url($this->Url, PHP_URL_HOST));
            IPS_SetProperty($ParentId, 'Port', 1012);
            IPS_SetProperty($ParentId, 'Open', true);
            @IPS_ApplyChanges($ParentId);
        }
    }

    private function SendHeaders()
    {
        header('Connection: close');
        header('Server: Symcon ' . IPS_GetKernelVersion());
        header('X-Powered-By: FritzBox Module');
        header('Expires: 0');
        header('Cache-Control: no-cache');
        header('Content-Type: text/plain');
    }

    private function LoadAndGetData(string $Uri)
    {
        $Url = parse_url($Uri);
        if (isset($Url['scheme'])) {
            $Url['query'] = isset($Url['query']) ? '?' . $Url['query'] : '';
            $Url = $this->Url . $Url['path'] . $Url['query'];
        } else {
            $Url = $this->Url . $Uri;
        }
        $Data = Sys_GetURLContentEx($Url, ['Timeout'=>10000, 'VerifyHost' => false, 'VerifyPeer' => false]);
        if ($Data === false) {
            $this->SendDebug('File not found', $Uri, 0);
            return false;
        }
        $this->SendDebug('Load File: ' . $Uri, $Data, 0);
        return $Data;
    }

    private function LoadAndSaveFile(string $Uri, string $Filename)
    {
        $Data = $this->LoadAndGetData($Uri);
        if (!$Data) {
            return false;
        }
        if (strpos($Filename, 'Phonebook_') === 0) {
            $xml = new SimpleXMLElement($Data);
            $Numbers = $xml->xpath('//number');
            foreach ($Numbers as &$Number) {
                $Number[0] = preg_replace('/[^0-9+*]+/i', '', (string) $Number[0]);
            }
            $Data = $xml->asXML();
        }
        $this->SetMediaObjectData($Filename, $Data);
        return true;
    }

    private function SetMediaObjectData(string $Ident, string $Data)
    {
        $Ident = preg_replace('/[^a-z0-9_]+/i', '_', $Ident);
        $this->SendDebug('Set MediaObject', $Ident, 0);
        $MediaID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($MediaID === false) {
            $MediaID = IPS_CreateMedia(MEDIATYPE_DOCUMENT);
            IPS_SetParent($MediaID, $this->InstanceID);
            IPS_SetIdent($MediaID, $Ident);
            IPS_SetName($MediaID, $Ident);
            IPS_SetMediaCached($MediaID, true);
            $filename = 'media' . DIRECTORY_SEPARATOR . 'FRITZBOX_' . $MediaID . '.xml';
            IPS_SetMediaFile($MediaID, $filename, false);
            $this->SendDebug('Create Media', $filename, 0);
        }
        IPS_SetMediaContent($MediaID, base64_encode($Data));
    }

    private function GetMediaObjectID(string $Ident): int
    {
        $Ident = preg_replace('/[^a-z0-9_]+/i', '_', $Ident);
        $MediaID = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);
        if ($MediaID === false) {
            $this->SendDebug('MediaObject not Found', $Ident, 0);
            return 0;
        }
        $this->SendDebug('Get MediaObject(' . $MediaID . ')', $Ident, 0);
        return $MediaID;
    }

    private function GetFile(string $Filename): int
    {
        $this->SendDebug('Get File: ', $Filename, 0);
        return $this->GetMediaObjectID($Filename);
    }

    private function LoadXmls()
    {
        $this->CreateTempDir();
        @array_map('unlink', glob(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/*.xml'));
        $Url = $this->Url;
        $Xmls = ['tr64desc.xml', 'igd2desc.xml', 'igddesc.xml'];
        $Result = false;
        $Events = [];
        $this->WriteAttributeBoolean('HasTel', false);
        $this->WriteAttributeBoolean('HasIGD2', false);
        foreach ($Xmls as $Xml) {
            $XMLData = @Sys_GetURLContentEx($Url . '/' . $Xml, ['Timeout'=>3000, 'VerifyHost' => false, 'VerifyPeer' => false]);
            if ($XMLData === false) {
                $this->SendDebug('XML not found', $Xml, 0);
                continue;
            }

            $this->SendDebug('Load XML: ' . $Xml, $XMLData, 0);
            file_put_contents(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/' . $Xml, $XMLData);
            if (stripos($XMLData, 'WLANConfiguration') > 0) {
                for ($i = 5; $i != 0; $i--) {
                    if (stripos($XMLData, 'WLANConfiguration:' . $i) > 0) {
                        break;
                    }
                }
                $this->SendDebug('No of WLANs', $i, 0);
                $this->WriteAttributeInteger('NoOfWlan', $i);
            }
            //Nur bei Bedarf laden?

            $SCPD_Data = new \simpleXMLElement($XMLData);

            $Services = [];
            // service mit xpath suchen !
            $SCPD_Data->registerXPathNamespace('fritzbox', $SCPD_Data->getNameSpaces(false)['']);
            $SCPDURLs = $SCPD_Data->xpath('//fritzbox:SCPDURL');
            foreach ($SCPDURLs as $SCPDURL) {
                $XMLSCPDData = @Sys_GetURLContentEx($Url . (string) $SCPDURL, ['Timeout'=>3000, 'VerifyHost' => false, 'VerifyPeer' => false]);
                $SCPD = substr((string) $SCPDURL, 1);
                if ($XMLSCPDData === false) {
                    $this->SendDebug('SCPD not found', $SCPD, 0);
                    continue;
                }
                if ($SCPDURL == '/x_contactSCPD.xml') {
                    $this->WriteAttributeBoolean('HasTel', true);
                }
                $this->SendDebug('Load SCPD: ' . $SCPD, $XMLSCPDData, 0);
                file_put_contents(IPS_GetKErnelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/' . $SCPD, $XMLSCPDData);
                $Events[$SCPD] = (stripos($XMLSCPDData, '<stateVariable sendEvents="yes">') > 0);
            }
            if ($Xml == 'igd2desc.xml') {
                $this->SendDebug('Use IGD2', 'true', 0);
                $this->WriteAttributeBoolean('HasIGD2', true);
                break;
            }
            $Result = true;
        }
        $this->WriteAttributeArray('Events', $Events);
        return $Result;
    }

    private function GetConsumerAddress()
    {
        $Port = $this->ReadPropertyInteger('ReturnPort');
        if (IPS_GetOption('NATSupport')) {
            $ip = $this->ReadPropertyString('ReturnIP');
            if ($ip == '') {
                $ip = IPS_GetOption('NATPublicIP');
                if ($ip == '') {
                    $this->SendDebug('NAT enabled ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('EventHook', 'caption', $this->Translate('NATPublicIP is missing in special switches!'));
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    $this->ShowLastError('Error', $this->Translate('NAT support is active, but no public address is set.'));
                    return false;
                }
            }
            $Debug = 'NAT enabled ConsumerAddress';
        } else {
            $ip = $this->ReadPropertyString('ReturnIP');
            if ($ip == '') {
                $sock = socket_create(AF_INET6, SOCK_STREAM, SOL_TCP);
                $Host = parse_url($this->Url);
                @socket_connect($sock, $Host['host'], $Host['port']);
                $ip = '';
                $result = @socket_getsockname($sock, $ip);
                @socket_close($sock);
                if (($result == false) || (strpos($ip, '::') === 0)) {
                    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                    $Host = parse_url($this->Url);
                    @socket_connect($sock, $Host['host'], $Host['port']);
                    $ip = '';
                    $result = @socket_getsockname($sock, $ip);
                    @socket_close($sock);
                    if (($result == false) || ($ip == '0.0.0.0')) {
                        $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                        $this->UpdateFormField('EventHook', 'caption', $this->Translate('Invalid'));
                        $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                        return false;
                    }
                } else {
                    $ip = '[' . $ip . ']';
                }
            }
            $Debug = 'ConsumerAddress';
        }
        $Url = 'http://' . $ip . ':' . $Port . '/hook/FritzBoxIO' . $this->InstanceID;
        $this->SendDebug($Debug, $Url, 0);
        $this->UpdateFormField('EventHook', 'caption', $Url);
        $this->WriteAttributeString('ConsumerAddress', $Url);
        return true;
    }

    private function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELMESSAGE);
        $this->RegisterMessage($this->InstanceID, FM_CHILDADDED);
        $this->ForceLoadXML = true;
        $this->ApplyChanges();
    }

    private function ShowLastError(string $ErrorMessage, string $ErrorTitle = 'Error')
    {
        IPS_Sleep(500);
        $this->UpdateFormField('ErrorTitle', 'caption', $this->Translate($ErrorTitle));
        $this->UpdateFormField('ErrorText', 'caption', $this->Translate($ErrorMessage));
        $this->UpdateFormField('ErrorPopup', 'visible', true);
    }

    private function CheckHost(): bool
    {
        if (!$this->ReadPropertyBoolean('Open')) {
            return false;
        }
        $URL = $this->ReadPropertyString('Host');
        if ($URL == 'http://') {
            return false;
        }
        $Scheme = parse_url($URL, PHP_URL_SCHEME);
        if ($Scheme == null) {
            $Scheme = 'http';
        }
        $Host = parse_url($URL, PHP_URL_HOST);
        if ($Host == null) {
            $this->SetStatus(self::isURLnotValid);
            return false;
        }
        $Port = parse_url($URL, PHP_URL_PORT);
        if ($Port == null) {
            $Port = ($Scheme == 'https') ? 49443 : 49000;
        }
        //$Path = parse_url($URL, PHP_URL_PATH);
        $this->Url = $Scheme . '://' . $Host . ':' . $Port;
        return true;
    }

    private function GetLastUser()
    {
        $result = $this->CallSoapAction(
            $HttpCode,
            'urn:dslforum-org:service:LANConfigSecurity:1',
            '/upnp/control/lanconfigsecurity',
            'X_AVM-DE_GetUserList'
        );
        if (is_a($result, 'SoapFault')) {
            return '';
        }
        $xml = new \simpleXMLElement($result);
        if ($xml === false) {
            $this->SendDebug('XML decode error', $result, 0);
            return '';
        }
        $Xpath = $xml->xpath('/List/Username[@last_user="1"]');
        if (count($Xpath) > 0) {
            return (string) $Xpath[0];
        }
        return '';
    }

    private function getWANConnectionTyp(&$HttpCode): bool
    {
        $result = $this->CallSoapAction(
            $HttpCode,
            'urn:dslforum-org:service:Layer3Forwarding:1',
            '/upnp/control/layer3forwarding',
            'GetDefaultConnectionService'
        );
        if (is_a($result, 'SoapFault')) {
            return false;
        }
        $this->WriteAttributeBoolean('usePPP', strpos($result, 'WANIPConnection') === false);
        $this->setIPSVariable('ConnectionType', 'Connection Type', (string) $result, VARIABLETYPE_STRING);
        return true;
    }

    private function getAreaCodes()
    {
        $Area = $this->CallSoapAction(
            $HttpCode,
            'urn:dslforum-org:service:X_VoIP:1',
            '/upnp/control/x_voip',
            'X_AVM-DE_GetVoIPCommonAreaCode'
        );
        if (is_a($Area, 'SoapFault')) {
            return false;
        }
        $Country = $this->CallSoapAction(
            $HttpCode,
            'urn:dslforum-org:service:X_VoIP:1',
            '/upnp/control/x_voip',
            'X_AVM-DE_GetVoIPCommonCountryCode'
        );
        if (is_a($Country, 'SoapFault')) {
            return false;
        }
        $Areas = [
            'OKZ'       => $Area['NewX_AVM-DE_OKZ'],
            'OKZPrefix' => $Area['NewX_AVM-DE_OKZPrefix'],
            'LKZ'       => $Country['NewX_AVM-DE_LKZ'],
            'LKZPrefix' => $Country['NewX_AVM-DE_LKZPrefix']
        ];
        $this->WriteAttributeArray('AreaCodes', $Areas);
        return true;
    }

    private function checkCallMonitorPort()
    {
        $Host = parse_url($this->Url, PHP_URL_HOST);
        $CallMon = @fsockopen($Host, 1012, $errno, $errstr, 0.5);

        if (is_resource($CallMon)) {
            fclose($CallMon);
            return true;
        }
        return false;
    }

    private function setIPSVariable(string $ident, string $name, $value, $type)
    {
        $this->MaintainVariable($ident, $this->Translate($name), $type, '', 0, true);
        $this->SetValue($ident, $value);
    }

    # Parameter und Result  eines Dienste aus der FritzBox lesen
    /*
           private function getFunctionVars($SCPDURL)
            {
                $xmlDesc = @simplexml_load_file($this->Url . '/' . $SCPDURL);
                if ($xmlDesc === false) {
                    $this->SendDebug('Error load SCPD', $this->Url . '/' . $SCPDURL, 0);
                    return false;
                }
                $xmlDesc->registerXPathNamespace('fritzbox', $xmlDesc->getNameSpaces(false)['']);
                $xmlFunctionList = $xmlDesc->xpath('//fritzbox:actionList/fritzbox:action');
                $FunctionList = [];
                foreach ($xmlFunctionList as $xmlFunction) {
                    $FunctionList[(string) $xmlFunction->name]['Parameter'] = [];
                    $FunctionList[(string) $xmlFunction->name]['Result'] = [];
                    $xmlParameterList = $xmlDesc->xpath("//fritzbox:actionList/fritzbox:action[fritzbox:name='" . (string) $xmlFunction->name . "']/fritzbox:argumentList/fritzbox:argument[fritzbox:direction='in']");
                    foreach ($xmlParameterList as $xmlParameter) {
                        $xmlStateVariable = $xmlDesc->xpath("//fritzbox:stateVariable[fritzbox:name='" . (string) $xmlParameter->relatedStateVariable . "']");
                        $FunctionList[(string) $xmlFunction->name]['Parameter'][(string) $xmlParameter->name] = (string) $xmlStateVariable[0]->dataType;
                    }
                    $xmlResultList = $xmlDesc->xpath("//fritzbox:actionList/fritzbox:action[fritzbox:name='" . (string) $xmlFunction->name . "']/fritzbox:argumentList/fritzbox:argument[fritzbox:direction='out']");
                    foreach ($xmlResultList as $xmlResult) {
                        $xmlStateVariable = $xmlDesc->xpath("//fritzbox:stateVariable[fritzbox:name='" . (string) $xmlResult->relatedStateVariable . "']");
                        $FunctionList[(string) $xmlFunction->name]['Result'][(string) $xmlResult->name] = (string) $xmlStateVariable[0]->dataType;
                    }
                }
                $this->SendDebug('FunctionList', $FunctionList, 0);

                return $FunctionList;
            }
     */
    private function CallSoapAction(&$HttpCode, $serviceTyp, $controlURL, $function, $params = [], $retry = true)
    {
        $this->SendDebug('URL', $this->Url . $controlURL, 0);
        $this->SendDebug('Service', $serviceTyp, 0);
        $this->SendDebug('Action', $function, 0);
        $Options = [
            'uri'                    => $serviceTyp,
            'location'               => $this->Url . $controlURL,
            'noroot'                 => true,
            'trace'                  => true,
            'exceptions'             => true,
            'soap_version'           => SOAP_1_1,
            'connection_timeout'     => 10,
            'default_socket_timeout' => 10,
            'keep_alive'             => true,
            'login'                  => $this->Username,
            'password'               => $this->ReadPropertyString('Password'),
            'authentication'         => SOAP_AUTHENTICATION_DIGEST,
            'compression'            => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
            'stream_context'         => stream_context_create(
                [
                    'ssl'  => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true
                    ],
                    'http' => [
                        'protocol_version' => 1.1,
                        'timeout'          => 10
                    ]
                ]
            )
        ];
        $client = new SoapClient(null, $Options);
        try {
            if (count($params) == 0) {
                $Result = $client->{$function}();
            } else {
                $SoapParams = [];
                foreach ($params as $Name => $Value) {
                    $SoapParams[] = new SoapParam($Value, $Name);
                }
                $Result = $client->__soapCall($function, $SoapParams);
            }
            $Response = $client->__getLastResponse();
            $this->SendDebug('Soap Request Header', $client->__getLastRequestHeaders(), 0);
            $this->SendDebug('Soap Request', $client->__getLastRequest(), 0);
            $ResponseHeaders = $client->__getLastResponseHeaders();
            $this->SendDebug('Soap Response Headers', $ResponseHeaders, 0);
            $this->SendDebug('Soap Response', $Response, 0);
            if ($ResponseHeaders == null) {
                $HttpCode = 418;
            } else {
                $HttpCode = (int) explode(' ', explode("\r\n", $ResponseHeaders)[0])[1];
            }
            $this->SendDebug('Soap Response Code', $HttpCode, 0);
        } catch (\SoapFault $e) {
            $Response = $client->__getLastResponse();
            $this->SendDebug('Soap Request Headers', $client->__getLastRequestHeaders(), 0);
            $this->SendDebug('Soap Request', $client->__getLastRequest(), 0);
            $ResponseHeaders = $client->__getLastResponseHeaders();
            $this->SendDebug('Soap Response Error Header', $ResponseHeaders, 0);
            $this->SendDebug('Soap Response Error', $Response, 0);
            if (property_exists($e, 'detail')) {
                $Details = $e->detail;
                $Detail = $e->faultstring . '(' . $e->faultcode . ')';
                $this->SendDebug($Detail, $Details, 0);
            } else {
                $this->SendDebug('Error', $e, 0);
            }
            if ($ResponseHeaders == null) {
                $HttpCode = 418;
            } else {
                $HttpCode = (int) explode(' ', explode("\r\n", $ResponseHeaders)[0])[1];
            }
            $this->SendDebug('Soap Response Code (' . $HttpCode . ')', $e->faultstring, 0);
            if ($retry) {
                usleep(100000);
                $this->SendDebug('RETRY', '', 0);
                return $this->CallSoapAction($HttpCode, $serviceTyp, $controlURL, $function, $params, false);
            }
            if ($HttpCode == 418) {
                $this->SetStatus(self::$http_error[418][1]);
            }
            return $e;
        }
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    # Event Subscribe zusammenbauen, senden und Rückmeldung auswerten
    private function Subscribe(string $Uri, string $SID)
    {
        if ($this->ReadAttributeString('ConsumerAddress') == 'Invalid') {
            $this->LogMessage('ConsumerAddress Invalid' . "\r\n" . $this->ReadAttributeString('ConsumerAddress'), KL_ERROR);
            return false;
        }
        $stream = stream_context_create(
            [
                'ssl'  => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ]
        );

        if ($SID == '') {
            $SID = 'CALLBACK: <' . $this->ReadAttributeString('ConsumerAddress') . $Uri . ">\r\n" .
                        "NT: upnp:event\r\n";
        } else {
            $SID = 'SID: ' . $SID . "\r\n";
        }
        $content = 'SUBSCRIBE ' . $Uri . " HTTP/1.1\r\n" .
                      'HOST: ' . parse_url($this->Url, PHP_URL_HOST) . ':' . parse_url($this->Url, PHP_URL_PORT) . "\r\n" .
                      $SID .
                      'USER-AGENT: PHP/' . PHP_VERSION . ' UPnP/2.0 Symcon/' . IPS_GetKernelVersion() . "\r\n" .
                      "TIMEOUT: Second-3600\r\n" .
                      "Connection: Close\r\n" .
                      "Content-Length: 0\r\n\r\n";
        $this->SendDebug('Send SUBSCRIBE', $content, 0);
        $Prefix = (parse_url($this->Url, PHP_URL_SCHEME) == 'https') ? 'ssl://' : 'tcp://';
        $fp = @stream_socket_client($Prefix . parse_url($this->Url, PHP_URL_HOST) . ':' . parse_url($this->Url, PHP_URL_PORT), $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $stream);
        if (!$fp) {
            $this->LogMessage('Could not connect to eventSubURL' . "\r\n" . $Uri, KL_ERROR);
            $this->SendDebug('Could not connect to eventSubURL', $Uri, 0);
            return false;
        } else {
            for ($fwrite = 0, $written = 0, $max = strlen($content); $written < $max; $written += $fwrite) {
                $fwrite = @fwrite($fp, substr($content, $written));
                if ($fwrite === false) {
                    $this->LogMessage('Error on write to eventSubURL' . "\r\n" . $Uri, KL_ERROR);
                    $this->SendDebug('Error on write to eventSubURL', $Uri, 0);
                    @fclose($fp);
                    return false;
                }
            }
            $ret = stream_get_contents($fp);
            fclose($fp);
            $headers = $this->http_parse_headers($ret);
            if (!isset($headers[0])) {
                $this->LogMessage('Error on subscribe (parse headers)' . "\r\n" . $ret, KL_ERROR);
                $this->SendDebug('Error on subscribe (parse headers)', $headers[0], 0);
                return false;
            }
            if ($headers[0] != 'HTTP/1.1 200 OK') {
                $this->LogMessage('Error on subscribe (' . $headers[0] . ')' . "\r\n" . $ret, KL_ERROR);
                $this->SendDebug('Error on subscribe', $headers[0], 0);
                return false;
            }
            if (!array_key_exists('SID', $headers)) {
                $this->LogMessage('Error on subscribe (' . $headers[0] . ') No SID' . "\r\n" . $ret, KL_ERROR);
                $this->SendDebug('Error on subscribe', $ret, 0);
                return false;
            }
            $this->SendDebug('Subscribe successfully', $Uri, 0);
            $data['SID'] = $headers['SID'];
            $data['TIMEOUT'] = (int) substr($headers['TIMEOUT'], strpos($headers['TIMEOUT'], '-') + 1);
            return $data;
        }
    }

    private function Unsubscribe(string $Uri, string $SID)
    {
        $stream = stream_context_create(
            [
                'ssl'  => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true
                ]
            ]
        );

        $content = 'UNSUBSCRIBE ' . $Uri . " HTTP/1.1\r\n" .
                      'HOST: ' . parse_url($this->Url, PHP_URL_HOST) . ':' . parse_url($this->Url, PHP_URL_PORT) . "\r\n" .
                      'SID: ' . $SID . "\r\n" .
                      'USER-AGENT: PHP/' . PHP_VERSION . ' UPnP/2.0 Symcon/' . IPS_GetKernelVersion() . "\r\n" .
                      "Connection: Close\r\n" .
                      "Content-Length: 0\r\n\r\n";
        $this->SendDebug('Send UNSUBSCRIBE', $content, 0);
        $Prefix = (parse_url($this->Url, PHP_URL_SCHEME) == 'https') ? 'ssl://' : 'tcp://';
        $fp = @stream_socket_client($Prefix . parse_url($this->Url, PHP_URL_HOST) . ':' . parse_url($this->Url, PHP_URL_PORT), $errno, $errstr, 5, STREAM_CLIENT_CONNECT, $stream);
        if (!$fp) {
            $this->LogMessage('Could not connect to eventSubURL' . "\r\n" . $Uri, KL_ERROR);
            $this->SendDebug('Could not connect to eventSubURL', $Uri, 0);
            return false;
        } else {
            for ($fwrite = 0, $written = 0, $max = strlen($content); $written < $max; $written += $fwrite) {
                $fwrite = @fwrite($fp, substr($content, $written));
                if ($fwrite === false) {
                    $this->LogMessage('Error on write to eventSubURL' . "\r\n" . $Uri, KL_ERROR);
                    $this->SendDebug('Error on write to eventSubURL', $Uri, 0);
                    @fclose($fp);
                    return false;
                }
            }
            $ret = stream_get_contents($fp);
            fclose($fp);
            $headers = $this->http_parse_headers($ret);
            if (!isset($headers[0])) {
                $this->LogMessage('Error on unsubscribe (parse headers)' . "\r\n" . $ret, KL_ERROR);
                $this->SendDebug('Error on unsubscribe (parse headers)', $headers[0], 0);
                return false;
            }
            if ($headers[0] != 'HTTP/1.1 200 OK') {
                $this->LogMessage('Error on unsubscribe (' . $headers[0] . ')' . "\r\n" . $ret, KL_ERROR);
                $this->SendDebug('Error on unsubscribe', $headers[0], 0);
                return false;
            }
            $this->SendDebug('Unsubscribe successfully', $Uri, 0);
            return true;
        }
    }

    private function http_parse_headers($raw_headers)
    {
        $headers = [];
        $key = '';

        foreach (explode("\n", $raw_headers) as $i => $h) {
            $h = explode(':', $h, 2);

            if (isset($h[1])) {
                if (!isset($headers[$h[0]])) {
                    $headers[$h[0]] = trim($h[1]);
                } elseif (is_array($headers[$h[0]])) {
                    $headers[$h[0]] = array_merge($headers[$h[0]], [trim($h[1])]);
                } else {
                    $headers[$h[0]] = array_merge([$headers[$h[0]]], [trim($h[1])]);
                }

                $key = $h[0];
            } else {
                if (substr($h[0], 0, 1) == "\t") {
                    $headers[$key] .= "\r\n\t" . trim($h[0]);
                } elseif (!$key) {
                    $headers[0] = trim($h[0]);
                }
            }
        }
        $headers = array_change_key_case($headers, CASE_UPPER);
        return $headers;
    }
}
