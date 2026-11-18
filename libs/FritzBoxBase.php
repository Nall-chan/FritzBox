<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/BufferHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
eval('declare(strict_types=1);namespace FritzBoxModulBase {?>' . file_get_contents(__DIR__ . '/../libs/helper/ParentIOHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxModule.php';
/**
 * @property string $SID
 * @property string $SubscribedURL EventSubURL, mit der die aktuelle SID angemeldet wurde (die Eigenschaft Index kann sich zwischenzeitlich geändert haben)
 * @property bool $isSubscribed
 * @property int $ParentID
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 * @method int RegisterParent()
 */
class FritzBoxModulBase extends IPSModuleStrict
{
    use \FritzBoxModulBase\BufferHelper;
    use \FritzBoxModulBase\DebugHelper;
    use \FritzBoxModulBase\InstanceStatus {
        \FritzBoxModulBase\InstanceStatus::MessageSink as IOMessageSink;
        \FritzBoxModulBase\InstanceStatus::RequestAction as IORequestAction;
    }

    protected static $ControlUrlArray = [];
    protected static $ServiceTypeArray = [];
    protected static $EventSubURLArray = [];
    protected static $SecondEventGUID = '';
    protected static $DefaultIndex = -1;
    /**
     * Untervariablen, die bis 0.95 unterhalb der Statusvariablen je Gerät angelegt wurden:
     * Ident => [Namensschlüssel (Standardname bis 0.95 und in 1.00), Eigenschaft der Auswahl der Zusatzvariable].
     * Seit 1.00 sind es eigene Statusvariablen der Instanz; Migrate verschiebt die alten einmalig dorthin.
     * Was dabei nicht verschoben wurde, löscht nur der Nutzer (Schaltfläche im Formular mit Sicherheitsabfrage).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    protected static $LegacySubVariables = [];

    /**
     * true, solange der aktuelle Aufruf über RequestAction läuft.
     * Bewusst kein Buffer: Symcon erzeugt je Aufruf ein neues Objekt.
     */
    private bool $IsRequestAction = false;
    /** Fehler einer optionalen Zusatzabfrage nur per SendDebug melden (SendExQuiet). */
    private bool $QuietErrors = false;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->SID = '';
        $this->SubscribedURL = '';
        $this->ParentID = 0;
        $this->isSubscribed = false;
        if (count(static::$EventSubURLArray) > 0) {
            $this->RegisterTimer(\FritzBox\Base\Timer::RenewSubscription, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::Subscribe . '", true);');
        }
        $this->RegisterPropertyInteger(\FritzBox\Base\Property::Index, static::$DefaultIndex);
        if (count(static::$LegacySubVariables) > 0) {
            // Neue Instanzen haben keine alten Untervariablen
            $this->RegisterAttributeBoolean(\FritzBox\Base\Attribute::LegacySubVariablesMigrated, true);
        }
    }

    /**
     * Wird beim Laden der gespeicherten Konfiguration aufgerufen (Start von Symcon, Modul-Update), nach Create und vor
     * ApplyChanges; der Objektbaum ist dann vorhanden. Instanzen bis 0.95 (ohne Attribut LegacySubVariablesMigrated)
     * migrieren ihre alten Untervariablen einmalig, danach ist das Attribut gesetzt und es passiert nichts mehr.
     *
     * @param string $JSONData Gespeicherte Konfiguration und Attribute der Instanz.
     *
     * @return string Geänderte Konfiguration/Attribute, leer ohne Änderung.
     */
    public function Migrate(string $JSONData): string
    {
        // Module ohne alte Untervariablen (z.B. WAN IP-Verbindung mit eigener Migration) brauchen hier nichts
        if (count(static::$LegacySubVariables) === 0) {
            return '';
        }
        parent::Migrate($JSONData);
        // Als Objekt dekodieren, damit leere Objekte (attributes, configuration) nicht zu [] werden
        $Data = json_decode($JSONData);
        if (!is_object($Data)) {
            return '';
        }
        if (!isset($Data->attributes) || !is_object($Data->attributes)) {
            $Data->attributes = new \stdClass();
        }
        if (property_exists($Data->attributes, \FritzBox\Base\Attribute::LegacySubVariablesMigrated)) {
            return '';
        }
        // Auswahl der Zusatzvariablen aus der Konfiguration; fehlt die Eigenschaft (Instanz bis 0.95), gilt der Standard „an“
        $Enabled = [];
        foreach (static::$LegacySubVariables as $Info => [, $Property]) {
            $Enabled[$Info] = (bool) ($Data->configuration->{$Property} ?? true);
        }
        try {
            $this->MigrateLegacySubVariables($Enabled);
        } catch (\Throwable $th) {
            // Nicht migrierte Variablen bleiben als alte Untervariablen stehen (Schaltfläche „Alte Untervariablen löschen“)
            $this->LogMessage(sprintf($this->Translate('Migration of old sub variables failed: %s'), $th->getMessage()), KL_WARNING);
        }
        // Nur einmal, auch wenn einzelne Variablen nicht verschoben werden konnten
        $Data->attributes->{\FritzBox\Base\Attribute::LegacySubVariablesMigrated} = true;
        return json_encode($Data);
    }

    /**
     * Alle Geräte-Module hängen an der FritzBox IO.
     */
    public function GetCompatibleParents(): string
    {
        return json_encode(['type' => 'connect', 'moduleIDs' => [\FritzBox\GUID::FritzBoxIO]]);
    }

    public function ApplyChanges(): void
    {
        if ($this->SID !== '') {
            $this->Unsubscribe();
        }
        $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
        //Never delete this line!
        parent::ApplyChanges();
        $this->RegisterMessage($this->InstanceID, FM_CONNECT);
        $this->RegisterMessage($this->InstanceID, FM_DISCONNECT);
        $this->RegisterMessage(0, IPS_KERNELSHUTDOWN);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            $this->RegisterMessage(0, IPS_KERNELSTARTED);
            return;
        }

        $this->RegisterParent();
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        if (count(static::$EventSubURLArray) == 0) {
            $Index = -1;
        }
        if ($Index > -1) {
            $Filter = preg_quote(substr(json_encode(static::$EventSubURLArray[$Index]), 1, -1));
            if (static::$SecondEventGUID != '') {
                $Filter .= '".*|.*"DataID":"' . preg_quote(static::$SecondEventGUID);
            }
            $this->SetReceiveDataFilter('.*"EventSubURL":"' . $Filter . '".*');
            $this->SendDebug('Filter', '.*"EventSubURL":"' . $Filter . '".*', 0);
        } else {
            if (static::$SecondEventGUID != '') {
                $Filter = '.*"DataID":"' . preg_quote(static::$SecondEventGUID) . '".*';
                $this->SetReceiveDataFilter($Filter);
                $this->SendDebug('FilterSecondEventGUID', $Filter, 0);
            } else {
                $this->SendDebug('Filter', 'NOTHINGTORECEIVE', 0);
                $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
            }
        }
        $this->Subscribe();
        $this->RemoveUnusedLegacyProfiles();
    }

    /**
     * IPSModuleStrict: RequestAction hat keinen Rückgabewert. Die Module überschreiben daher
     * ProcessRequestAction, das zurückmeldet, ob der Ident behandelt wurde.
     */
    public function RequestAction(string $Ident, mixed $Value): void
    {
        $this->IsRequestAction = true;
        $this->ProcessRequestAction($Ident, $Value);
    }

    public function MessageSink(int $TimeStamp, int $SenderID, int $Message, array $Data): void
    {
        $myState = IPS_GetInstance($this->InstanceID)['InstanceStatus']; // NICHT $this->GetStatus benutzen, da MessageSink auch aufgerufen wird, wenn die Instanz noch nicht erstellt wurde (z.B. beim Modul Update)
        if ($myState == IS_CREATING || $myState == IS_DELETING) {
            return;
        }

        $this->IOMessageSink($TimeStamp, $SenderID, $Message, $Data);

        switch ($Message) {
            case IPS_KERNELSTARTED:
                $this->KernelReady();
                break;
            case IPS_KERNELSHUTDOWN:
                if (IPS_InstanceExists($this->InstanceID)) {
                    if ($this->SID !== '') {
                        $this->Unsubscribe();
                    }
                }
                break;
        }
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == \FritzBox\GUID::SendEventToChildren) {
            unset($data['DataID']);
            if (!array_key_exists('EventData', $data)) {
                return '';
            }
            $this->isSubscribed = true;
            $this->SendDebug('Event', \FritzBox\DebugMask::Values($data['EventData']), 0);
            $this->DecodeEvent($data['EventData']);
        }
        return '';
    }

    /**
     * Behandelt die Aktionen der Basisklasse (Statuswechsel der IO, Erneuern der Ereignisse).
     *
     * @return mixed true bzw. Ergebnis der Aktion, wenn der Ident behandelt wurde, sonst false.
     */
    protected function ProcessRequestAction(string $Ident, mixed $Value): mixed
    {
        if ($this->IORequestAction($Ident, $Value)) {
            return true;
        }
        if ($Ident == \FritzBox\Base\Action::Subscribe) {
            $this->Subscribe();
            return true;
        }
        if ($Ident == \FritzBox\Base\Action::DeleteLegacySubVariables) {
            $this->DeleteLegacySubVariables();
            return true;
        }
        return false;
    }

    /**
     * Blendet die Schaltfläche zum Löschen der alten Untervariablen ein, wenn es welche gibt.
     *
     * @param array $Form Formular aus form.json.
     *
     * @return array Angepasstes Formular.
     */
    protected function ShowLegacySubVariablesButton(array $Form): array
    {
        if (count($this->GetLegacySubVariables()) === 0) {
            return $Form;
        }
        foreach ($Form['actions'] as &$Action) {
            if (($Action['name'] ?? '') === \FritzBox\Base\Action::DeleteLegacySubVariables) {
                $Action['visible'] = true;
            }
        }
        return $Form;
    }

    /**
     * Liefert die alten Untervariablen (bis 0.95) unterhalb der eigenen Statusvariablen.
     *
     * @return int[] IDs der Untervariablen.
     */
    protected function GetLegacySubVariables(): array
    {
        if (count(static::$LegacySubVariables) === 0) {
            return [];
        }
        $Result = [];
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ParentID) {
            if (!IPS_VariableExists($ParentID)) {
                continue;
            }
            foreach (IPS_GetChildrenIDs($ParentID) as $ChildID) {
                if (IPS_VariableExists($ChildID) && array_key_exists(IPS_GetObject($ChildID)['ObjectIdent'], static::$LegacySubVariables)) {
                    $Result[] = $ChildID;
                }
            }
        }
        return $Result;
    }

    /**
     * Verschiebt die alten Untervariablen (bis 0.95, Kind der Gerätevariable mit Ident z.B. `Speed`) an die Instanz und
     * gibt ihnen den Ident der Zusatzvariable (`<Info>_<Ident der Gerätevariable>`). ID, Archivdaten, Ereignisse und Links
     * bleiben erhalten. Übersprungen werden abgewählte Arten und Geräte, für die die Zusatzvariable schon existiert.
     * Den Namen setzt die Migration nur, wenn er noch der Standardname des Moduls ist (sonst gehört er dem Nutzer).
     *
     * @param array $Enabled Art der Information => gewünscht (Eigenschaft der Instanz).
     */
    protected function MigrateLegacySubVariables(array $Enabled): void
    {
        $Count = 0;
        foreach (IPS_GetChildrenIDs($this->InstanceID) as $ParentID) {
            if (!IPS_VariableExists($ParentID)) {
                continue;
            }
            $DeviceIdent = IPS_GetObject($ParentID)['ObjectIdent'];
            if ($DeviceIdent === '') {
                continue;
            }
            foreach (IPS_GetChildrenIDs($ParentID) as $ChildID) {
                $Info = IPS_GetObject($ChildID)['ObjectIdent'];
                if (!IPS_VariableExists($ChildID) || !array_key_exists($Info, static::$LegacySubVariables) || !($Enabled[$Info] ?? false)) {
                    continue;
                }
                $Ident = $Info . '_' . $DeviceIdent;
                if (@IPS_GetObjectIDByIdent($Ident, $this->InstanceID) !== false) {
                    continue;
                }
                // Erst den Ident (eindeutig unter der Gerätevariable), dann verschieben; misslingt das, Ident zurück
                if (!@IPS_SetIdent($ChildID, $Ident)) {
                    continue;
                }
                if (!@IPS_SetParent($ChildID, $this->InstanceID)) {
                    @IPS_SetIdent($ChildID, $Info);
                    continue;
                }
                $NameKey = static::$LegacySubVariables[$Info][0];
                if (in_array(IPS_GetName($ChildID), [$NameKey, $this->Translate($NameKey)], true)) {
                    IPS_SetName($ChildID, IPS_GetName($ParentID) . ' - ' . $this->Translate($NameKey));
                }
                $Count++;
            }
        }
        if ($Count > 0) {
            $this->LogMessage(sprintf($this->Translate('%d old sub variables migrated.'), $Count), KL_MESSAGE);
        }
    }

    /**
     * Löscht die alten Untervariablen (bis 0.95). Nur über die Schaltfläche im Formular,
     * die vorher eine Sicherheitsabfrage zeigt.
     */
    protected function DeleteLegacySubVariables(): void
    {
        $Count = 0;
        foreach ($this->GetLegacySubVariables() as $VariableID) {
            if (in_array($VariableID, $this->GetReferenceList())) {
                $this->UnregisterReference($VariableID);
            }
            if (IPS_DeleteVariable($VariableID)) {
                $Count++;
            }
        }
        $this->UpdateFormField(\FritzBox\Base\Action::DeleteLegacySubVariables, 'visible', false);
        echo sprintf($this->Translate('%d old sub variables deleted.'), $Count);
    }

    /**
     * Entfernt alle Zusatzvariablen je Gerät (Ident <Info>_…) einer Info, die der Nutzer abgewählt hat.
     * Aufruf in ApplyChanges, damit das Abwählen sofort wirkt und auch Geräte erfasst, welche die FritzBox nicht mehr meldet.
     *
     * @param array<string, bool> $Infos Info (Präfix des Idents) => Eigenschaft der Instanz, ob gewünscht.
     */
    protected function RemoveDisabledDeviceInfoVariables(array $Infos): void
    {
        foreach ($Infos as $Info => $Enabled) {
            if ($Enabled) {
                continue;
            }
            foreach (IPS_GetChildrenIDs($this->InstanceID) as $ChildID) {
                $Ident = IPS_GetObject($ChildID)['ObjectIdent'];
                if (IPS_VariableExists($ChildID) && str_starts_with($Ident, $Info . '_')) {
                    $this->UnregisterVariable($Ident);
                }
            }
        }
    }

    /**
     * Legt eine Zusatzvariable je Gerät an (z.B. Modell, Signal) oder entfernt sie, wenn der Nutzer sie abgewählt hat.
     * Ident: <Info>_<Ident der Gerätevariable>. So bleiben die Idents der Gerätevariablen (z.B. MAC…) eindeutig erkennbar.
     *
     * @param bool   $Enabled     Eigenschaft der Instanz, ob die Variable gewünscht ist.
     * @param string $Info        Art der Information (Konstante aus Variables), Präfix des Idents.
     * @param string $DeviceIdent Ident der Gerätevariable.
     * @param string $Name        Name der Variable (bereits übersetzt).
     * @param mixed  $Value       Wert.
     * @param int    $Type        Variablentyp.
     * @param string $Profile     Profilname für die Darstellung.
     * @param bool   $Rename      Eigenschaft der Instanz, ob Variablen automatisch umbenannt werden (wie die Gerätevariable).
     */
    protected function SetDeviceInfoVariable(bool $Enabled, string $Info, string $DeviceIdent, string $Name, mixed $Value, int $Type, string $Profile = '', bool $Rename = false): void
    {
        $Ident = $Info . '_' . $DeviceIdent;
        if (!$Enabled) {
            if (@$this->GetIDForIdent($Ident) > 0) {
                $this->UnregisterVariable($Ident);
            }
            return;
        }
        $this->setIPSVariable($Ident, $Name, $Value, $Type, $Profile);
        if ($Rename) {
            $VarId = $this->GetIDForIdent($Ident);
            if (IPS_GetName($VarId) != $Name) {
                IPS_SetName($VarId, $Name);
            }
        }
    }

    protected function DecodeEvent(array $Event): void
    {
        foreach ($Event as $Ident => $EventData) {
            $vid = @$this->GetIDForIdent($Ident);
            if ($vid > 0) {
                // Ereigniswerte kommen als Text, passend zum Variablentyp umwandeln
                switch (IPS_GetVariable($vid)['VariableType']) {
                    case VARIABLETYPE_BOOLEAN:
                        $EventData = (string) $EventData !== '0';
                        break;
                    case VARIABLETYPE_INTEGER:
                        $EventData = (int) $EventData;
                        break;
                    case VARIABLETYPE_FLOAT:
                        $EventData = (float) $EventData;
                        break;
                    default:
                        $EventData = (string) $EventData;
                        break;
                }
                $this->SetValue($Ident, $EventData);
            }
        }
    }

    protected function KernelReady(): void
    {
        $this->UnregisterMessage(0, IPS_KERNELSTARTED);
        $this->ApplyChanges();
    }

    /**
     * Wird ausgeführt wenn sich der Status vom Parent ändert.
     */
    protected function IOChangeState(int $State): void
    {
        switch ($State) {
            case IS_ACTIVE:
                if ($this->SID !== '') {
                    $this->Unsubscribe();
                }
                $this->SetReceiveDataFilter('.*NOTHINGTORECEIVE.*');
                $this->ApplyChanges();
                break;
            case IS_INACTIVE:
            case IS_EBASE + 1:
            case IS_EBASE + 2:
            case IS_EBASE + 3:
            case IS_EBASE + 4:
            case IS_EBASE + 5:
            case IS_EBASE + 6:
                $this->SID = '';
                $this->isSubscribed = false;
                if (count(static::$EventSubURLArray) > 0) {
                    $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, 0);
                }
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
        $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
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
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => \FritzBox\IOFunction::Subscribe,
                'EventSubURL'=> static::$EventSubURLArray[$Index],
                'SID'        => $this->SID
            ]
        ));
        if ($Ret === '') {
            $this->SID = '';
            $this->SendDebug('Error on subscribe (parse)', static::$EventSubURLArray[$Index], 0);
            trigger_error($this->Translate('Error on subscribe (parse)') . "\r\n" . static::$EventSubURLArray[$Index], E_USER_NOTICE);
            $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, 60000);
            return false;
        }
        $Result = \FritzBox\IOResult::Decode($Ret);
        if ($Result === false) {
            $this->SID = '';
            $this->SendDebug('Error on subscribe (Result)', static::$EventSubURLArray[$Index], 0);
            trigger_error($this->Translate('Error on subscribe (Result)') . "\r\n" . static::$EventSubURLArray[$Index], E_USER_NOTICE);
            $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, 60000);
            return false;
        }
        $this->SendDebug('Result', $Result, 0);
        $isNewSubscription = ($this->SID === '');
        // SID sofort merken, damit sie auch bei fehlendem Event wieder abgemeldet werden kann
        $this->SID = $Result['SID'];
        $this->SubscribedURL = static::$EventSubURLArray[$Index];
        if ($isNewSubscription) {
            if (!$this->WaitForEvent()) {
                $this->SendDebug('No event after subscribe', static::$EventSubURLArray[$Index], 0);
                $this->LogMessage('No event after subscribe', KL_ERROR);
                $this->Unsubscribe();
                $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, 60000);
                return false;
            }
        }
        $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, ((int) $Result['TIMEOUT'] - 300) * 1000);
        return true;
    }

    protected function WaitForEvent(): bool
    {
        for ($i = 0; $i < 1000; $i++) {
            if ($this->isSubscribed) {
                return true;
            } else {
                usleep(5000);
            }
        }
        return false;
    }

    protected function RefreshHostXML(): bool
    {
        $this->SendDebug('RefreshHostList', '', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => \FritzBox\IOFunction::RefreshHostList
            ]
        ));
        if (\FritzBox\IOResult::Decode($Ret) === false) {
            $this->SendDebug('Error on RefreshHostList', '', 0);
            return false;
        }
        return true;
    }

    protected function GetConfiguratorID(): int
    {
        $GUID = \FritzBox\GUID::Configurator;
        $Instances = IPS_GetInstanceListByModuleID($GUID);
        $AllInstancesOfParent = array_filter($Instances, [$this, 'FilterInstances']);
        if (count($AllInstancesOfParent) > 0) {
            return $AllInstancesOfParent[0];
        }
        return 1;
    }

    protected function LoadAndGetData(string $Uri): mixed
    {
        return $this->LoadAndSaveFile($Uri, '');
    }

    /**
     * Lädt eine Datei vom Webserver der FritzBox (Schema und Host der IO, Standardport 80/443 statt TR-064-Port).
     *
     * @param string $Uri Pfad auf der FritzBox, z.B. `/dvb/m3u/radio.m3u`.
     *
     * @return string|false Inhalt der Datei, false bei Fehler.
     */
    protected function LoadWebData(string $Uri): string|false
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Load web', $Uri, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => \FritzBox\IOFunction::LoadWebData,
                'Uri'        => $Uri
            ]
        ));
        $Result = is_string($Ret) ? \FritzBox\IOResult::Decode($Ret) : false;
        $this->SendDebug('Load web', is_string($Result) ? strlen($Result) . ' bytes' : 'failed', 0);
        return is_string($Result) ? $Result : false;
    }

    protected function LoadAndSaveFile(string $Uri, string $Filename): mixed
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Function', 'Load', 0);
        $this->SendDebug('Uri', \FritzBox\DebugMask::Url($Uri), 0);
        $this->SendDebug('Filename', $Filename, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => ($Filename == '' ? \FritzBox\IOFunction::LoadAndGetData : \FritzBox\IOFunction::LoadAndSaveFile),
                'Uri'        => $Uri,
                'Filename'   => $Filename
            ]
        ));
        $Result = \FritzBox\IOResult::Decode($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    protected function GetFile(string $Filename): string|false
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        $this->SendDebug('Function', 'Get', 0);
        $this->SendDebug('Filename', $Filename, 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => \FritzBox\IOFunction::GetFile,
                'Filename'   => $Filename
            ]
        ));
        $MediaID = \FritzBox\IOResult::Decode($Ret);
        if (!$MediaID) {
            return false;
        }
        // Die Datei des Medienobjekts kann fehlen (z.B. nach Wiederherstellung eines Backups)
        $Content = @IPS_GetMediaContent($MediaID);
        if ($Content === false) {
            $this->SendDebug('Media file missing', $MediaID, 0);
            return false;
        }
        $Result = base64_decode($Content);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    protected function Send(string $Function, array $Parameter = []): mixed
    {
        // Während module_reload kann ein Timer laufen, bevor die Instanz bereit ist
        // („InstanceInterface is not available“).
        try {
            $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
        } catch (\Throwable $th) {
            return false;
        }
        if ($Index < 0) {
            return false;
        }
        return $this->SendEx($Function, $Index, $Parameter);
    }

    protected function SendEx(string $Function, int $Index, array $Parameter = []): mixed
    {
        if (!$this->HasActiveParent()) {
            return false;
        }
        if ($this->GetStatus() != IS_ACTIVE) {
            return false;
        }
        $this->SendDebug('Function', $Function, 0);
        $this->SendDebug('Params', \FritzBox\DebugMask::Values($Parameter), 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'    => \FritzBox\GUID::SendToFritzBoxIO,
                'ServiceTyp'=> static::$ServiceTypeArray[$Index],
                'ControlUrl'=> static::$ControlUrlArray[$Index],
                'Function'  => $Function,
                'Parameter' => $Parameter
            ]
        ));
        // Während module_reload liefert SendDataToParent ggf. false („Cannot find interface for instance“)
        if (!is_string($Ret) || ($Ret === '')) {
            return false;
        }
        $Result = \FritzBox\IOResult::Decode($Ret);
        if (is_a($Result, 'SoapFault')) {
            if (property_exists($Result, 'detail')) {
                $Details = $Result->detail;
                if ($Details) {
                    if (property_exists($Details, $Result->faultstring)) {
                        $UPnPError = $Details->{$Result->faultstring};
                        $Details = (property_exists($UPnPError, 'errorCode') ? 'Error code ' . $UPnPError->errorCode . ': ' : '') .
                            (property_exists($UPnPError, 'errorDescription') ? $UPnPError->errorDescription : '') . "\r\n";
                    } else {
                        $Details = '';
                    }
                } else {
                    $Details = '';
                }
                $Detail = $Result->faultstring . '(' . $Result->faultcode . ')';
                $this->SendDebug($Detail, $Details, 0);
                $this->ReportError($Detail . "\r\n" . $Details);
                return false;
            }
            $this->SendDebug('SoapFault', $Result->getMessage(), 0);
            $this->ReportError($Result->getMessage());
            return false;
        }
        $this->SendDebug('Result', \FritzBox\DebugMask::Result($Function, $Result), 0);
        return $Result;
    }

    /**
     * Meldet einen Fehler an den Aufrufer.
     * Aufrufe über RequestAction (Bedienung aus der Visualisierung) erhalten die Meldung per echo,
     * alle anderen Aufrufer (Skripte, Instanz-Funktionen) per trigger_error.
     *
     * @param string $Message Fehlermeldung
     */
    /**
     * Wie SendEx, aber für optionale Zusatzabfragen: Fehler nur per SendDebug, ohne Meldung an den Aufrufer.
     */
    protected function SendExQuiet(string $Function, int $Index, array $Parameter = []): mixed
    {
        $this->QuietErrors = true;
        try {
            return $this->SendEx($Function, $Index, $Parameter);
        } finally {
            $this->QuietErrors = false;
        }
    }

    protected function ReportError(string $Message): void
    {
        if ($this->QuietErrors) {
            $this->SendDebug('Error (optional query)', $Message, 0);
            return;
        }
        if ($this->IsRequestAction) {
            echo $Message;
            return;
        }
        trigger_error($Message, E_USER_NOTICE);
    }

    /**
     * Darstellung zum früheren Profilnamen (siehe \FritzBox\Presentation).
     */
    protected function GetPresentation(string $Profile, int $Type, bool $Action = false): array|string
    {
        return \FritzBox\Presentation::Get($Profile, $Type, $Action, [$this, 'Translate']);
    }

    /**
     * Löscht die eigenen Profile bis 0.95, sobald keine Variable sie mehr nutzt
     * (weder als Profil noch als eigenes Profil des Nutzers).
     */
    protected function RemoveUnusedLegacyProfiles(): void
    {
        $Legacy = array_filter(\FritzBox\Presentation::LegacyProfiles, 'IPS_VariableProfileExists');
        if (count($Legacy) === 0) {
            return;
        }
        $Used = [];
        foreach (IPS_GetVariableList() as $VarID) {
            $Variable = IPS_GetVariable($VarID);
            $Used[$Variable['VariableProfile']] = true;
            $Used[$Variable['VariableCustomProfile']] = true;
        }
        foreach ($Legacy as $Profile) {
            if (!isset($Used[$Profile])) {
                IPS_DeleteVariableProfile($Profile);
                $this->SendDebug('Profile deleted', $Profile, 0);
            }
        }
    }

    protected function setIPSVariable(string $ident, string $name, mixed $value, int $type, string $profile = '', bool $action = false, int $pos = 0): void
    {
        $this->MaintainVariable($ident, $this->Translate($name), $type, $this->GetPresentation($profile, $type, $action), $pos, true);
        if ($action) {
            $this->EnableAction($ident);
        }
        $this->SetValue($ident, $value);
    }

    protected function ConvertRuntime(int $Time): string
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

    protected function ConvertIdent(string $Ident): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '', $Ident);
    }

    protected function DelSubObjects(int $ObjectId): void
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

    protected function ServeFile(string $path): void
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

    protected function IsCompressionAllowed(string $mimeType): bool
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

    protected function GetMimeType(string $extension): string
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

    private function Unsubscribe(): void
    {
        $this->SendDebug('Unsubscribe', $this->SID, 0);
        $this->isSubscribed = false;
        $SID = $this->SID;
        $SubscribedURL = $this->SubscribedURL;
        $this->SID = '';
        $this->SubscribedURL = '';
        if (count(static::$EventSubURLArray) > 0) {
            $this->SetTimerInterval(\FritzBox\Base\Timer::RenewSubscription, 0);
        } else {
            return;
        }
        // Mit der URL abmelden, mit der angemeldet wurde: Bei geänderter Eigenschaft Index wäre es sonst die falsche
        if ($SubscribedURL === '') {
            $Index = $this->ReadPropertyInteger(\FritzBox\Base\Property::Index);
            if ($Index < 0) {
                return;
            }
            $SubscribedURL = static::$EventSubURLArray[$Index];
        }
        if (!$this->HasActiveParent()) {
            return;
        }

        $Ret = @$this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => \FritzBox\IOFunction::Unsubscribe,
                'EventSubURL'=> $SubscribedURL,
                'SID'        => $SID
            ]
        ));
        if ($Ret === '') {
            $this->SendDebug('Error on Unsubscribe (parse)', $SubscribedURL, 0);
        } else {
            $Result = \FritzBox\IOResult::Decode($Ret);
            if ($Result === false) {
                $this->SendDebug('Error on Unsubscribe (Result)', $SubscribedURL, 0);
            }
            $this->SendDebug('Unsubscribe', $Result, 0);
        }
    }

    private function FilterInstances(int $InstanceID): bool
    {
        return IPS_GetInstance($InstanceID)['ConnectionID'] == $this->ParentID;
    }
}
