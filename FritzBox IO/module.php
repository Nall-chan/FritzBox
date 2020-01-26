<?php

declare(strict_types=1);
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/AttributeArrayHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/WebhookHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxIO {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
/**
 * @property string $Url
 * @property self $State
 * @property array $StateVariables
 */
    class FritzBoxIO extends IPSModule
    {
        use \FritzBoxIO\AttributeArrayHelper;
        use \FritzBoxIO\BufferHelper;
        use \FritzBoxIO\DebugHelper;
        use \FritzBoxIO\WebhookHelper;

        const isConnected = IS_ACTIVE;
        const isInActive = IS_INACTIVE;
        const isDisconnected = IS_EBASE + 1;
        const isUnauthorized = IS_EBASE + 2;
        const isURLnotValid = IS_EBASE + 3;
        const isServicenotValid = IS_EBASE + 4;
        private static $http_error =
        [
            418 => ['Could not connect to host, mabye i am a teapot?', self::isDisconnected],
            404 => ['Service not Found', self::isServicenotValid],
            401 => ['Unauthorized', self::isUnauthorized],
            500 => ['UPnPError', self::isDisconnected]
        ];

        public function Create()
        {
            //Never delete this line!
            parent::Create();
            $this->RegisterPropertyBoolean('Open', false);
            $this->RegisterPropertyString('Host', 'http://');
            $this->RegisterPropertyString('Username', '');
            $this->RegisterPropertyString('Password', '');
            $this->RegisterPropertyBoolean('UseConnect', false);
            $this->RegisterAttributeString('ConsumerAddress', 'Invalid');
            $this->RegisterAttributeArray('Events', []);
            $this->Url = '';
            //$this->StateVariables = [];
            //$this->State = self::isInActive;
            //$this->RequireParent("{6179ED6A-FC31-413C-BB8E-1204150CF376}");
            if (IPS_GetKernelRunlevel() == KR_READY) {
                $this->doNotLoadXML = false;
                $this->RegisterMessage($this->InstanceID, FM_CHILDADDED);
                @mkdir(IPS_GetKernelDir() . 'FritzBoxTemp');
                @mkdir(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID);
            } else {
                $this->RegisterMessage(0, IPS_KERNELMESSAGE);
                $this->doNotLoadXML = true;
            }
        }

        public function Destroy()
        {
            if (!IPS_InstanceExists($this->InstanceID)) {
                $this->UnregisterHook('/hook/FritzBoxIO' . $this->InstanceID);
                @array_map('unlink', glob(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/*.xml'));
                @rmdir(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID);
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
                    //prüfen ob CS benötigt wird und dann anlegen.
                    break;
            }
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
            //$this->StateVariables = [];

            //Never delete this line!
            parent::ApplyChanges();
            if (IPS_GetKernelRunlevel() != KR_READY) { // IPS läuft dann gleich Daten abholen
                return;
            }
            $this->RegisterHook('/hook/FritzBoxIO' . $this->InstanceID);
            $this->SetStatus(IS_INACTIVE);
            if ($this->CheckHost()) {
                $this->SetSummary($this->Url);
                if (($this->Url != $OldUrl) && !$this->doNotLoadXML) {
                    $this->doNotLoadXML = false;
                    if (!$this->LoadXMLs()) {
                        $this->ShowLastError('Could not connect to host, mabye i am a teapot?');
                        $this->SetStatus(IS_EBASE + 3);
                        return;
                    }
                }
                /*                if (!$this->getDeviceStateVars()) {
                                    $this->ShowLastError(self::$http_error[418][0]);
                                    $this->SetStatus(self::isURLnotValid);
                                    return;
                                }*/

                if (!$this->getDeviceInfo($HttpCode)) {
                    $this->SetStatus(IS_EBASE + 3);
                    $this->ShowLastError(self::$http_error[$HttpCode][0]);
                    return;
                }
                $this->GetConsumerAddress(); // todo prüfen
                // Eigene Events holen?
                // Prüfen ob Antwort kommt ?
                $this->SetStatus(IS_ACTIVE);
            } else {
                $this->Url = '';
                $this->SetSummary('');
                return;
            }
        }

        public function ForwardData($JSONString)
        {
            $data = json_decode($JSONString, true);
            switch ($data['Function']) {
				case 'SCPD':
					$ret = $this->ReadAttributeArray('Events');
                case 'SUBSCRIBE':
                    $ret = $this->Subscribe($data['EventSubURL'], $data['SID']);
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
            IPS_LogMessage('Splitter RECV', utf8_decode($data->Buffer));
        }
        public function GetConfigurationForm()
        {
            $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
            if (IPS_GetOption('NATSupport')) {
                $Form['elements'][4]['visible'] = true;
            }
            $ConsumerAddress = $this->ReadAttributeString('ConsumerAddress');
            if ($ConsumerAddress == 'Invalid') {
                $Form['actions'][1]['visible'] = true;
                $Form['actions'][1]['popup']['items'][0]['caption'] = 'Error';
                $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate('Couldn\'t determine webhook');
            }
            $Form['actions'][0]['items'][1]['caption'] = $this->Translate($ConsumerAddress);
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
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
            if (!isset($_GET['eventSubURL'])) {
                http_response_code(400);
                $this->SendHeaders();
                echo 'Bad Request!';
                return;
            }
            if (!isset($_SERVER['HTTP_SID'])) {
                http_response_code(400);
                $this->SendHeaders();
                echo 'Bad Request!';
                return;
            }
            $eventSubUrl = $_GET['eventSubURL'];
            $SID = $_SERVER['HTTP_SID'];

            http_response_code(200);
            $this->SendHeaders();
            $Data = file_get_contents('php://input');
            $this->SendDebug('EVENT', $Data, 0);
            $xml = new simpleXMLElement($Data);
            $xml->registerXPathNamespace('event', $xml->getNameSpaces(false)['e']);
            $xmlPropertys = $xml->xpath('//event:property');
            foreach ($xmlPropertys as &$property) {
                $property = (array) $property;
            }
            $this->SendDebug('EVENT XML', $xmlPropertys, 0);
            //todo Send to Childs
            $this->SendDataToChildren(json_encode(
                    [
                        'DataID'     => '{CBD869A0-869B-3D4C-7EA8-D917D935E647}',
                        'EventSubURL'=> $eventSubUrl,
                        'EventData'  => $xmlPropertys
                    ])
                    );
            $this->SendDebug('Child', json_encode(
                        [
                            'DataID'     => '{CBD869A0-869B-3D4C-7EA8-D917D935E647}',
                            'EventSubURL'=> $eventSubUrl,
                            'EventData'  => $xmlPropertys
                        ]), 0);
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
        private function LoadXmls()
        {
            @array_map('unlink', glob(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/*.xml'));
            $Url = $this->Url;
            $Xmls = ['igddesc.xml', 'igd2desc.xml', 'tr64desc.xml'];
			$Result = false;
			$Events= [];
            foreach ($Xmls as $Xml) {
                /*$stream = stream_context_create(
                    [
                        'ssl'  => [
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true
                        ],
                        'http' => [
                            'protocol_version' => 1.1,
                            'timeout'          => 3,
                            'header'           => [
                                'Connection: close'
                            ]
                        ]
                    ]
                    );
                if (!@copy($Url . '/' . $Xml . '.xml', IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/' . $Xml . '.xml', $stream)) {
                    $this->SendDebug('XML not found', $Xml, 0);
                    continue;
                }*/
                $Result = true;
                $XMLData = @Sys_GetURLContentEx($Url . '/' . $Xml, ['Timeout'=>3000]);
                
                if ($XMLData === false) {
                    $this->SendDebug('XML not found', $Xml, 0);
                    continue;
                }
                $this->SendDebug('Load XML: ' . $Xml, $XMLData, 0);
                file_put_contents(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/' . $Xml, $XMLData);

                //Nur bei Bedarf laden?

                $SCPD_Data = new SimpleXMLElement($XMLData);

                $Services = [];
                // service mit xpath suchen !
                $SCPD_Data->registerXPathNamespace('fritzbox', $SCPD_Data->getNameSpaces(false)['']);
                $SCPDURLs = $SCPD_Data->xpath('//fritzbox:SCPDURL');
                foreach ($SCPDURLs as $SCPDURL) {
                    $XMLSCPDData = @Sys_GetURLContentEx($Url . (string) $SCPDURL, ['Timeout'=>3000]);
                    $SCPD = substr((string) $SCPDURL, 1);
                    if ($XMLSCPDData === false) {
                        //$this->DeleteXmlMedia($SCPDIdent);
                        $this->SendDebug('SCPD not found', $SCPD, 0);
                        continue;
                    }
                    $this->SendDebug('Load SCPD: ' . $SCPD, $XMLSCPDData, 0);
                    file_put_contents(IPS_GetKernelDir() . 'FritzBoxTemp/' . $this->InstanceID . '/' . $SCPD, $XMLSCPDData);
                    $Events[$SCPD] = (stripos('<stateVariable sendEvents="no">', $XMLSCPDData) > 0);
                }
			}
			$this->WriteAttributeArray('Events', $Events);
            return $Result;
        }

        private function DeleteXmlMedia(string $Ident)
        {
            $oID = @$this->GetIDForIdent($Ident);
            if ($oID > 0) {
                IPS_DeleteMedia($oID, true);
            }
        }
        private function GetConsumerAddress()
        {
            if ($this->ReadPropertyBoolean('UseConnect')) {
                $ids = IPS_GetInstanceListByModuleID('{9486D575-BE8C-4ED8-B5B5-20930E26DE6F}');
                $Url = '';
                if (count($ids) > 0) {
                    $Url = CC_GetConnectURL($ids[0]);
                }
                if ($Url == '') {
                    $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                    $this->UpdateFormField('Eventhook', 'caption', 'Invalid');
                    $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                    return false;
                }
                $Url .= '/hook/FritzBoxIO' . $this->InstanceID;
                $this->SendDebug('Symcon Connect enabled ConsumerAddress', $Url, 0);
            } else {
                if (IPS_GetOption('NATSupport')) {
                    $parsed_url = parse_url($this->ReadPropertyString('NATAddress'));
                    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] . '://' : 'http://';
                    $host = isset($parsed_url['host']) ? $parsed_url['host'] : '';
                    $port = isset($parsed_url['port']) ? ':' . $parsed_url['port'] : ':3777';
                    $Url = $scheme . $host . $port . '/hook/FritzBoxIO' . $this->InstanceID;
                    $this->SendDebug('NAT enabled ConsumerAddress', $Url, 0);
                } else {
                    $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                    socket_bind($sock, '0.0.0.0', 0);
                    $Host = parse_url($this->Url);
                    @socket_connect($sock, $Host['host'], $Host['port']);
                    $ip = '';
                    socket_getsockname($sock, $ip);
                    @socket_close($sock);
                    if ($ip == '0.0.0.0') {
                        $this->SendDebug('ConsumerAddress', 'Invalid', 0);
                        $this->UpdateFormField('Eventhook', 'caption', 'Invalid');
                        $this->WriteAttributeString('ConsumerAddress', 'Invalid');
                        return false;
                    }
                    $Url = 'http://' . $ip . ':3777/hook/FritzBoxIO' . $this->InstanceID;
                    $this->SendDebug('ConsumerAddress', $Url, 0);
                }
            }
            $this->UpdateFormField('Eventhook', 'caption', $Url);
            $this->WriteAttributeString('ConsumerAddress', $Url);
            return true;
        }
        private function KernelReady()
        {
            $this->UnregisterMessage(0, IPS_KERNELMESSAGE);
            $this->RegisterMessage($this->InstanceID, FM_CHILDADDED);
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
                //$this->SetStatus(IS_INACTIVE);
                //$this->State = self::isInActive;
                return false;
            }
            $URL = $this->ReadPropertyString('Host');
            if ($URL == 'http://') {
                //$this->SetStatus(IS_INACTIVE);
                //$this->State = self::isInActive;
                return false;
            }
            $Scheme = parse_url($URL, PHP_URL_SCHEME);
            if ($Scheme == null) {
                $Scheme = 'http';
            }
            $Host = parse_url($URL, PHP_URL_HOST);
            if ($Host == null) {
                $this->SetStatus(IS_EBASE + 3);
                //$this->State = self::isDisconnected;
                return false;
            }
            $Port = parse_url($URL, PHP_URL_PORT);
            if ($Port == null) {
                $Port = ($Scheme == 'https') ? 49443 : 49000;
            }
            $Path = parse_url($URL, PHP_URL_PATH);
            $this->Url = $Scheme . '://' . $Host . ':' . $Port;
            return true;
        }
        /*private function getDeviceStateVars(): bool
        {
            $StateVariables = $this->getFunctionVars('deviceinfoSCPD.xml');
            if ($StateVariables === false) {
                return false;
            }
            $this->StateVariables = $StateVariables;
            return true;
        }*/
        private function getDeviceInfo(&$HttpCode): bool
        {
            $result = $this->CallSoapAction(
                $HttpCode,
                'urn:dslforum-org:service:DeviceInfo:1',
                '/upnp/control/deviceinfo',
                'GetInfo');
            if (is_a($result, 'SoapFault')) {
                return false;
            }
            /*$StateVariables =  $this->getResultVariables('GetInfo');
            if ($StateVariables==false){
                return false;
            }*/

            $this->setIPSVariable('Hersteller', 'Manufacturer', (string) $result['NewManufacturerName'], VARIABLETYPE_STRING);
            $this->setIPSVariable('Model', 'Model', (string) $result['NewModelName'], VARIABLETYPE_STRING);
            $this->setIPSVariable('Seriennummer', 'SerialNumber', (string) $result['NewSerialNumber'], VARIABLETYPE_STRING);
            $this->setIPSVariable('SoftwareVersion', 'Software-Version', (string) $result['NewSoftwareVersion'], VARIABLETYPE_STRING);
            $this->setIPSVariable('LetzterNeustart', 'last reboot', time() - (int) $result['NewUpTime'], VARIABLETYPE_INTEGER, '~UnixTimestamp');
            $this->setIPSVariable('RunTimeRAW', 'UpTime seconds', (int) $result['NewUpTime'], VARIABLETYPE_INTEGER);
            $this->setIPSVariable('Laufzeit', 'Uptime', $this->ConvertRunTime((int) $result['NewUpTime']), VARIABLETYPE_STRING);
            $this->setIPSVariable('DeviceLog', 'Logfile', (string) $result['NewDeviceLog'], VARIABLETYPE_STRING, '~TextBox');
            return true;
        }
        /*private function getResultVariables(string $Function){
            $StateVariables = $this->StateVariables;
            if (!array_key_exists($Function, $StateVariables)) {
                $this->SendDebug('Function in StateVars not found', $Function, 0);
                return false;
            }
        return $StateVariables[$Function]['Result'];
        }*/
        private function setIPSVariable(string $ident, string $name, $value, $type, string $profile = '', bool $action = false, int $pos = 0)
        {
            $this->MaintainVariable($ident, $this->Translate($name), $type, $profile, $pos, true);
            if ($action) {
                $this->EnableAction($ident);
            }
            $this->SetValue($ident, $value);
        }
        # Dynamische Anzeige von Laufzeiten
        private function ConvertRuntime($Time)
        {
            date_default_timezone_set('UTC');
            $strtime = '';
            $sec = intval(date('s', $Time));
            if ($sec != 0) {
                $strtime = $sec . ' Sek';
            }
            if ($Time > 60) {
                $strtime = intval(date('i', $Time)) . ' Min ' . $strtime;
            }
            if ($Time > 3600) {
                $strtime = date('G', $Time) . ' Std ' . $strtime;
            }
            if ($Time > 3600 * 24) {
                $strtime = date('z', $Time) . ' Tg ' . $strtime;
            }
            return $strtime;
        }

        # Parameter und Resulr  eines Dienste aus der FritzBox lesen
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
        private function CallSoapAction(&$HttpCode, $serviceTyp, $controlURL, $function, $params = [])
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
                'ssl_method '            => SOAP_SSL_METHOD_TLS,
                'soap_version'           => SOAP_1_1,
                'connection_timeout'     => 5,
                'default_socket_timeout' => 5,
                'keep_alive'             => false,
                'login'                  => $this->ReadPropertyString('Username'),
                'password'               => $this->ReadPropertyString('Password'),
                'authentication'         => SOAP_AUTHENTICATION_DIGEST,
                'compression'            => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP,
                'stream_context'         => $stream = stream_context_create(
                    [
                        'ssl'  => [
                            'verify_peer'       => false,
                            'verify_peer_name'  => false,
                            'allow_self_signed' => true
                        ],
                        'http' => [
                            'protocol_version' => 1.1,
                            'timeout'          => 4,
                            'header'           => [
                                'Connection: close',
                            ]
                        ]
                    ]
                )
            ];
            $client = new SoapClient(null, $Options);
            try {
                if (count($params) == 0) {
                    $Result = $client->{$function}();
                } else {
                    $Result = $client->{$function}($params);
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
            } catch (SoapFault $e) {
                $Response = $client->__getLastResponse();
                $this->SendDebug('Soap Request Headers', $client->__getLastRequestHeaders(), 0);
                $this->SendDebug('Soap Request', $client->__getLastRequest(), 0);
                $ResponseHeaders = $client->__getLastResponseHeaders();
                $this->SendDebug('Soap Response Error Header', $ResponseHeaders, 0);
                $this->SendDebug('Soap Response Error', $Response, 0);
                $this->SendDebug('Soap Response Error Message', $e->getMessage(), 0);
                if ($ResponseHeaders == null) {
                    $HttpCode = 418;
                } else {
                    $HttpCode = (int) explode(' ', explode("\r\n", $ResponseHeaders)[0])[1];
                }
                $this->SendDebug('Soap Response Code', $HttpCode, 0);
                $this->SetStatus(self::$http_error[$HttpCode][1]);
                return $e;
            }
            $this->SendDebug('Result', $Result, 0);
            return $Result;
        }

        # Event Subscribe zusammenbauen, senden und Rückmeldung auswerten
        private function Subscribe(string $Uri, string $SID)
        {
            if ($this->ReadAttributeString('ConsumerAddress') == 'Invalid') {
                return false;
            }
            if ($SID == '') {
                $SID = 'CALLBACK: <' . $this->ReadAttributeString('ConsumerAddress') . '?' . http_build_query(['eventSubURL'=>$Uri]) . ">\r\n" .
                        "NT: upnp:event\r\n";
            } else {
                $SID = 'SID: ' . $SID . "\r\n";
            }
            $content = 'SUBSCRIBE ' . $Uri . " HTTP/1.1\r\n" .
                      'HOST: ' . parse_url($this->Url, PHP_URL_HOST) . ':' . parse_url($this->Url, PHP_URL_PORT) . "\r\n" .
                      $SID .
                      'USER-AGENT: PHP/' . PHP_VERSION . ' UPnP/1.1 Symcon/' . IPS_GetKernelVersion() . "\r\n" .
                      "TIMEOUT: Second-3600\r\n" .
                      "Connection: Close\r\n" .
                      "Content-Length: 0\r\n\r\n";
            $this->SendDebug('Send SUBSCRIBE', $content, 0);
            $fp = @fsockopen(parse_url($this->Url, PHP_URL_HOST), parse_url($this->Url, PHP_URL_PORT), $errno, $errstr, 5);
            if (!$fp) {
                $this->SendDebug('Could not connect to eventSubURL', $Uri, 0);
                return false;
            } else {
                $fwrite = 0;
                for ($written = 0; $written < strlen($content); $written += $fwrite) {
                    $fwrite = @fwrite($fp, substr($content, $written));
                    if ($fwrite === false) {
                        $this->SendDebug('Error on write to eventSubURL', $Uri, 0);
                        return false;
                    }
                }
                $ret = '';
                while (!feof($fp)) {
                    $ret .= fgets($fp, 128);
                }
                fclose($fp);
                $headers = $this->http_parse_headers($ret);
                if ($headers[0] != 'HTTP/1.1 200 OK') {
                    $this->SendDebug('Error on subscribe', $headers[0], 0);
                    return false;
                } else {
                    $this->SendDebug('Subscribe successfully', $Uri, 0);
                    $data['SID'] = $headers['SID'];
                    $data['TIMEOUT'] = substr($headers['TIMEOUT'], strpos($headers['TIMEOUT'], '-') + 1);
                    return $data;
                }
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

            return $headers;
        }
    }