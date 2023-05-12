<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/VariableProfileHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');

/**
 * @property string $SID
 * @property bool $isSubscribed
 */
class FritzBoxModulBase extends IPSModule
{
    use \FritzBoxModulBase\BufferHelper;
    use \FritzBoxModulBase\DebugHelper;
    use \FritzBoxModulBase\VariableProfileHelper;
    use \FritzBoxModulBase\InstanceStatus {
        \FritzBoxModulBase\InstanceStatus::MessageSink as IOMessageSink; // MessageSink gibt es sowohl hier in der Klasse, als auch im Trait InstanceStatus. Hier wird für die Methode im Trait ein Alias benannt.
        //\FritzBoxModulBase\InstanceStatus::RegisterParent as IORegisterParent;
        \FritzBoxModulBase\InstanceStatus::RequestAction as IORequestAction;
    }
    protected static $ControlUrlArray = [];
    protected static $ServiceTypeArray = [];
    protected static $EventSubURLArray = [];
    protected static $DefaultIndex = -1;
    public function Create()
    {
        //Never delete this line!
        parent::Create();
        $this->SID = '';
        $this->ParentID = 0;
        $this->ConnectParent('{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}');
        if (count(static::$EventSubURLArray) > 0) {
            $this->RegisterTimer('RenewSubscription', 0, 'IPS_RequestAction(' . $this->InstanceID . ',"Subscribe",true);');
        }
        $this->RegisterPropertyInteger('Index', static::$DefaultIndex);
    }

    public function Destroy()
    {
        if (IPS_InstanceExists($this->InstanceID)) {
            if ($this->isSubscribed) {
                $this->Unsubscribe();
            }
        }
        //Never delete this line!
        parent::Destroy();
    }
    public function ApplyChanges()
    {
        //Never delete this line!
        if ($this->isSubscribed) {
            $this->Unsubscribe();
        }
        $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        parent::ApplyChanges();
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }
        $this->RegisterParent();
        $Index = $this->ReadPropertyInteger('Index');
        if (count(static::$EventSubURLArray) == 0) {
            $Index = -1;
        }
        if ($Index > -1) {
            $Filter = preg_quote(substr(json_encode(static::$EventSubURLArray[$Index]), 1, -1));
            if (property_exists($this, 'SecondEventGUID')) {
                $Filter .= '".*|.*"DataID":"' . preg_quote(static::$SecondEventGUID);
            }
            $this->SetReceiveDataFilter('.*"EventSubURL":"' . $Filter . '".*');
            $this->SendDebug('Filter', '.*"EventSubURL":"' . $Filter . '".*', 0);
        } else {
            if (property_exists($this, 'SecondEventGUID')) {
                $Filter = '.*"DataID":"' . preg_quote(static::$SecondEventGUID) . '".*';
                $this->SetReceiveDataFilter($Filter);
                $this->SendDebug('FilterSecondEventGUID', $Filter, 0);
            } else {
                $this->SendDebug('Filter', 'NOTHINGTORECEIVE', 0);
                $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
            }
        }
        $this->Subscribe();
    }

    public function RequestAction($Ident, $Value)
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == 'Subscribe') {
            $this->Subscribe();
            return true;
        }
        return false;
    }
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
        }
    }
    // TESTING TODO
    /*public function Test(string $Function)
    {
        $result = $this->SendEx($Function, 1);
        if ($result === false) {
            return false;
        }
        return $result;
    }
    public function TestValue(string $Function, $Value)
    {
        $result = $this->SendEx($Function, 1, $Value);
        if ($result === false) {
            return false;
        }
        return $result;
    }*/

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == '{CBD869A0-869B-3D4C-7EA8-D917D935E647}') {
            unset($data['DataID']);
            if (!array_key_exists('EventData', $data)) {
                return false;
            }
            $this->isSubscribed = true;
            $this->SendDebug('Event', $data['EventData'], 0);
            $this->DecodeEvent($data['EventData']);
            return true;
        }
        return null;
    }
    protected function DecodeEvent($Event)
    {
        foreach ($Event as $Ident => $EventData) {
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid > 0) {
                if (IPS_GetVariable($vid)['VariableType'] == VARIABLETYPE_BOOLEAN) {
                    $EventData = (string) $EventData !== '0';
                }
                $this->SetValue($Ident, $EventData);
            }
        }
    }
    protected function KernelReady()
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    /*protected function RegisterParent()
    {
        $this->IORegisterParent();
    }*/
    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState($State)
    {
        switch ($State) {
        case IS_ACTIVE:
            $this->ApplyChanges();
            break;
        case IS_INACTIVE:
        case IS_EBASE + 1:
        case IS_EBASE + 2:
        case IS_EBASE + 3:
        case IS_EBASE + 4:
            $this->SID = '';
            $this->isSubscribed = false;
            $this->SetTimerInterval('RenewSubscription', 0);
            break;
        }
    }

    protected function Subscribe(): bool
    {
        $Result = $this->DoSubscribe();
        if ($Result) {
            $this->SetStatus(IS_ACTIVE);
        } else {
            $this->SetStatus(IS_EBASE + 1);
        }
        return $Result;
    }
    protected function DoSubscribe(): bool
    {
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index < 0) {
            return true;
        }
        if (count(static::$EventSubURLArray) == 0) {
            return true;
        }
        if (!$this->HasActiveParent()) {
            $this->SID = '';
            return true;
        }
        $this->isSubscribed = false;

        $this->SendDebug('Subscribe', $this->SID, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'SUBSCRIBE',
                'EventSubURL'=> static::$EventSubURLArray[$Index],
                'SID'        => $this->SID
            ]
        ));
        if ($Ret === false) {
            $this->SID = '';
            $this->SendDebug('Error on subscribe (parse)', static::$EventSubURLArray[$Index], 0);
            trigger_error('Error on subscribe (parse)' . "\r\n" . static::$EventSubURLArray[$Index], E_USER_WARNING);
            $this->SetTimerInterval('RenewSubscription', 60000);
            return false;
        }
        $Result = unserialize($Ret);
        if ($Result === false) {
            $this->SID = '';
            $this->SendDebug('Error on subscribe (Result)', static::$EventSubURLArray[$Index], 0);
            trigger_error('Error on subscribe (Result)' . "\r\n" . static::$EventSubURLArray[$Index], E_USER_WARNING);
            $this->SetTimerInterval('RenewSubscription', 60000);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        if ($this->SID === '') {
            if (!$this->WaitForEvent()) {
                $this->SID = '';
                $this->SendDebug('No event after subscribe', static::$EventSubURLArray[$Index], 0);
                $this->LogMessage('No event after subscribe', KL_ERROR);
                $this->SetTimerInterval('RenewSubscription', 60000);
                return false;
            }
            $this->SID = $Result['SID'];
        }
        $this->SetTimerInterval('RenewSubscription', ((int) $Result['TIMEOUT'] - 300) * 1000);
        return true;
    }
    protected function WaitForEvent()
    {
        for ($i = 0; $i < 1000; $i++) {
            if ($this->isSubscribed) {
                return true;
            } else {
                IPS_Sleep(5);
            }
        }
        return false;
    }
    protected function RefreshHostXML()
    {
        $this->SendDebug('RefreshHostList', '', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'RefreshHostList'
            ]
        ));
        if ($Ret === false) {
            $this->SendDebug('Error on RefreshHostList', '', 0);
            return false;
        }
        return true;
    }
    protected function GetConfiguratorID()
    {
        $GUID = '{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}';
        $Instances = IPS_GetInstanceListByModuleID($GUID);
        $AllInstancesOfParent = array_filter($Instances, [$this, 'FilterInstances']);
        if (count($AllInstancesOfParent) > 0) {
            return $AllInstancesOfParent[0];
        }
        return 1;
    }

    protected function LoadAndGetData(string $Uri)
    {
        return $this->LoadAndSaveFile($Uri, '');
    }

    protected function LoadAndSaveFile(string $Uri, string $Filename)
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Function', 'Load', 0);
        $this->SendDebug('Uri', $Uri, 0);
        $this->SendDebug('Filename', $Filename, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => ($Filename == '' ? 'LoadAndGetData' : 'LoadAndSaveFile'),
                'Uri'        => $Uri,
                'Filename'   => $Filename
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    protected function GetFile(string $Filename)
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Function', 'Get', 0);
        $this->SendDebug('Filename', $Filename, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'GetFile',
                'Filename'   => $Filename
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $MediaID = unserialize($Ret);
        if ($MediaID == 0) {
            return false;
        }
        $Result = base64_decode(IPS_GetMediaContent($MediaID));
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    protected function Send(string $Function, array $Parameter = [])
    {
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index < 0) {
            return false;
        }
        return $this->SendEx($Function, $Index, $Parameter);
    }
    protected function SendEx(string $Function, int $Index, array $Parameter = [])
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        if ($this->GetStatus() != IS_ACTIVE) {
            return false;
        }
        $this->SendDebug('Function', $Function, 0);
        $this->SendDebug('Params', $Parameter, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'    => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'ServiceTyp'=> static::$ServiceTypeArray[$Index],
                'ControlUrl'=> static::$ControlUrlArray[$Index],
                'Function'  => $Function,
                'Parameter' => $Parameter
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        if (is_a($Result, 'SoapFault')) {
            if (property_exists($Result, 'detail')) {
                $Details = $Result->detail->{$Result->faultstring};
                $Detail = $Result->faultstring . '(' . $Details->errorCode . ')';
                $this->SendDebug($Detail, $Details->errorDescription, 0);
                trigger_error($Detail . "\r\n" . (new Exception())->getTraceAsString() . "\r\n" . $Details->errorDescription, E_USER_WARNING);
                return false;
            }
            $this->SendDebug('SoapFault', $Result->getMessage(), 0);
            trigger_error($Result->getMessage(), E_USER_WARNING);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    protected function setIPSVariable(string $ident, string $name, $value, $type, string $profile = '', bool $action = false, int $pos = 0)
    {
        $this->MaintainVariable($ident, $this->Translate($name), $type, $profile, $pos, true);
        if ($action) {
            $this->EnableAction($ident);
        }
        $this->SetValue($ident, $value);
    }

    protected function ConvertRuntime(int $Time)
    {
        $strtime = '';
        $sec = intval(gmdate('s', $Time));
        if ($sec != 0) {
            $strtime = $sec . $this->Translate(' sec');
        }
        if ($Time >= 60) {
            $strtime = intval(gmdate('i', $Time)) . $this->Translate(' min ') . $strtime;
        }
        if ($Time >= 3600) {
            $strtime = gmdate('G', $Time) . $this->Translate(' h ') . $strtime;
        }
        if ($Time >= 3600 * 24) {
            $strtime = gmdate('z', $Time) . $this->Translate(' d ') . $strtime;
        }
        return $strtime;
    }

    protected function ConvertIdent(string $Ident)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $Ident);
    }
    /**
     * Erstellt eine Untervariable in IPS.
     *
     * @param int    $ParentID IPS-ID der übergeordneten Variable.
     * @param string $Ident    IDENT der neuen Statusvariable.
     * @param string $Name     Name der neuen Statusvariable.
     * @param int    $Type     Der zu erstellende Typ von Variable.
     * @param string $Profile  Das dazugehörige Variabelprofil.
     * @param int    $Position Position der Variable.
     *
     * @throws Exception Wenn Variable nicht erstellt werden konnte.
     *
     * @return int IPS-ID der neuen Variable.
     */
    protected function RegisterSubVariable($ParentID, $Ident, $Name, $Type, $Profile = '', $Position = 0)
    {
        if ($Profile != '') {
            if (IPS_VariableProfileExists('~' . $Profile)) {
                $Profile = '~' . $Profile;
            }
            if (!IPS_VariableProfileExists($Profile)) {
                throw new Exception('Profile with name ' . $Profile . ' does not exist', E_USER_NOTICE);
            }
        }

        $vid = @IPS_GetObjectIDByIdent($Ident, $ParentID);

        if ($vid === false) {
            $vid = 0;
        }

        if ($vid > 0) {
            if (!IPS_VariableExists($vid)) {
                throw new Exception('Ident with name ' . $Ident . ' is used for wrong object type', E_USER_NOTICE); //bail out
            }
            if (IPS_GetVariable($vid)['VariableType'] != $Type) {
                IPS_DeleteVariable($vid);
                $vid = 0;
            }
        }

        if ($vid == 0) {
            $vid = IPS_CreateVariable($Type);

            IPS_SetParent($vid, $ParentID);
            IPS_SetIdent($vid, $Ident);
            IPS_SetName($vid, $this->Translate($Name));
            IPS_SetPosition($vid, $Position);
            //IPS_SetReadOnly($vid, true);
        }

        IPS_SetVariableCustomProfile($vid, $Profile);
        if (!in_array($vid, $this->GetReferenceList())) {
            $this->RegisterReference($vid);
        }
        return $vid;
    }
    protected function DelSubObjects(int $ObjectId)
    {
        foreach (IPS_GetChildrenIDs($ObjectId) as $Id) {
            $this->DelSubObjects($Id);
            switch (IPS_GetObject($Id)['ObjectType']) {
                case OBJECTTYPE_CATEGORY:
                    IPS_DeleteCategory($Id);
                    break;
                case OBJECTTYPE_INSTANCE:
                    IPS_DeleteInstance($Id);
                    break;
                case OBJECTTYPE_VARIABLE:
                    IPS_DeleteVariable($Id);
                    break;
                case OBJECTTYPE_SCRIPT:
                    IPS_DeleteScript($Id, true);
                    break;
                case OBJECTTYPE_EVENT:
                    IPS_DeleteEvent($Id);
                    break;
                case OBJECTTYPE_MEDIA:
                    IPS_DeleteMedia($Id, true);
                    break;
                case OBJECTTYPE_LINK:
                    IPS_DeleteLink($Id);
                    break;
                }
        }
    }
    protected function ServeFile(string $path)
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $mimeType = $this->GetMimeType($extension);
        header('Content-Type: ' . $mimeType);

        //Add caching support
        $etag = md5_file($path);
        header('ETag: ' . $etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && (trim($_SERVER['HTTP_IF_NONE_MATCH']) == $etag)) {
            http_response_code(304);
            return;
        }

        //Add gzip compression
        if (strstr($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') && $this->IsCompressionAllowed($mimeType)) {
            $compressed = gzencode(file_get_contents($path));
            header('Content-Encoding: gzip');
            header('Content-Length: ' . strlen($compressed));
            echo $compressed;
        } else {
            header('Content-Length: ' . filesize($path));
            readfile($path);
        }
    }
    protected function IsCompressionAllowed($mimeType)
    {
        return in_array($mimeType, [
            'text/plain',
            'text/html',
            'text/xml',
            'text/css',
            'text/javascript',
            'application/xml',
            'application/xhtml+xml',
            'application/rss+xml',
            'application/json',
            'application/json; charset=utf-8',
            'application/javascript',
            'application/x-javascript',
            'image/svg+xml'
        ]);
    }

    protected function GetMimeType($extension)
    {
        $lines = file(IPS_GetKErnelDir() . 'mime.types');
        foreach ($lines as $line) {
            $type = explode("\t", $line, 2);
            if (count($type) == 2) {
                $types = explode(' ', trim($type[1]));
                foreach ($types as $ext) {
                    if ($ext == $extension) {
                        return $type[0];
                    }
                }
            }
        }
        return 'text/plain';
    }
    private function Unsubscribe()
    {
        $this->SendDebug('Unsubscribe', $this->SID, 0);
        $this->isSubscribed = false;
        $SID = $this->SID;
        $this->SID = '';
        $this->SetTimerInterval('RenewSubscription', 0);
        $Index = $this->ReadPropertyInteger('Index');
        if ($Index < 0) {
            return;
        }
        if (count(static::$EventSubURLArray) == 0) {
            return;
        }
        if (!$this->HasActiveParent()) {
            return;
        }

        $Ret = @$this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'UNSUBSCRIBE',
                'EventSubURL'=> static::$EventSubURLArray[$Index],
                'SID'        => $SID
            ]
        ));
        if ($Ret === false) {
            $this->SID = '';
            $this->SendDebug('Error on Unsubscribe (parse)', static::$EventSubURLArray[$Index], 0);
        } else {
            $Result = unserialize($Ret);
            if ($Result === false) {
                $this->SID = '';
                $this->SendDebug('Error on Unsubscribe (Result)', static::$EventSubURLArray[$Index], 0);
            }
            $this->SendDebug('Unsubscribe', $Result, 0);
        }
    }
    private function FilterInstances(int $InstanceID)
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }
}
