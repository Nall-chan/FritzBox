<?php

declare(strict_types=1);
require_once __DIR__ . '/../libs/FritzBoxBase.php';
require_once __DIR__ . '/../libs/FritzBoxTile.php';

/**
 * DECT-Geräte (Mobilteile) der FritzBox.
 * Je Gerät eine Variable (angemeldet/aktiv) und, abschaltbar, je eine Variable für Modell und verfügbares Update.
 * Die Instanz-Kachel (HTML-SDK) zeigt die Geräte mit Modell und Update (libs/tile/devices.js).
 *
 * @property array $TileDevices Geräte für die Kachel (Ident der Variable, Kennung in der FritzBox)
 */
class FritzBoxDECT extends FritzBoxModulBase
{
    use \FritzBoxModul\Tile;
    protected static $ControlUrlArray = [
        '/upnp/control/x_dect'
    ];

    protected static $LegacySubVariables = [
        \FritzBox\DECT\Variables::Model           => ['Model', \FritzBox\DECT\Property::ModelVariables],
        \FritzBox\DECT\Variables::UpdateAvailable => ['Update available', \FritzBox\DECT\Property::UpdateAvailableVariables]
    ];
    protected static $ServiceTypeArray = [
        'urn:dslforum-org:service:X_AVM-DE_Dect:1'
    ];
    protected static $DefaultIndex = 0;

    public function Create(): void
    {
        //Never delete this line!
        parent::Create();
        $this->TileDevices = [];

        $this->RegisterPropertyInteger(\FritzBox\Base\Property::RefreshInterval, 300);
        $this->RegisterPropertyBoolean(\FritzBox\DECT\Property::RenameDeviceVariables, false);
        $this->RegisterPropertyBoolean(\FritzBox\DECT\Property::ModelVariables, true);
        $this->RegisterPropertyBoolean(\FritzBox\DECT\Property::UpdateAvailableVariables, true);
        $this->RegisterTimer(\FritzBox\Base\Timer::RefreshInfo, 0, 'IPS_RequestAction(' . $this->InstanceID . ',"' . \FritzBox\Base\Action::RefreshInfo . '",true);');
    }

    public function Destroy(): void
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function GetConfigurationForm(): string
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        return json_encode($this->ShowLegacySubVariablesButton($Form));
    }

    public function ApplyChanges(): void
    {
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, 0);
        $this->TileDevices = [];
        parent::ApplyChanges();
        $this->RegisterTile();
        $this->RemoveDisabledDeviceInfoVariables([
            \FritzBox\DECT\Variables::Model           => $this->ReadPropertyBoolean(\FritzBox\DECT\Property::ModelVariables),
            \FritzBox\DECT\Variables::UpdateAvailable => $this->ReadPropertyBoolean(\FritzBox\DECT\Property::UpdateAvailableVariables)
        ]);
        $this->SetTimerInterval(\FritzBox\Base\Timer::RefreshInfo, $this->ReadPropertyInteger(\FritzBox\Base\Property::RefreshInterval) * 1000);
        if (IPS_GetKernelRunlevel() != KR_READY) {
            return;
        }
        $this->UpdateInfo();
    }

    /**
     * Liefert die Anzahl der DECT-Geräte.
     *
     * @return int|false
     */
    public function GetNumberOfDectEntries(): int|false
    {
        $Result = $this->Send(__FUNCTION__);
        if ($Result === false) {
            return false;
        }
        return (int) $Result;
    }

    /**
     * Liefert die Daten eines DECT-Geräts anhand seiner Position in der Liste.
     *
     * @param int $Index Position (0 bis Anzahl - 1)
     *
     * @return array|false
     */
    public function GetGenericDectEntry(int $Index): mixed
    {
        if ($Index < 0) {
            $this->ReportError($this->Translate('Invalid index.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewIndex' => $Index
        ]);
    }

    /**
     * Liefert die Daten eines DECT-Geräts anhand seiner ID.
     *
     * @param string $ID ID des Geräts (NewID aus FB_GetGenericDectEntry)
     *
     * @return array|false
     */
    public function GetSpecificDectEntry(string $ID): mixed
    {
        if ($ID === '') {
            $this->ReportError($this->Translate('Invalid device ID.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewID' => $ID
        ]);
    }

    /**
     * Startet das Firmware-Update eines DECT-Geräts.
     *
     * @param string $ID ID des Geräts (NewID aus FB_GetGenericDectEntry)
     *
     * @return bool
     */
    public function DectDoUpdate(string $ID): bool
    {
        if ($ID === '') {
            $this->ReportError($this->Translate('Invalid device ID.'));
            return false;
        }
        return $this->Send(__FUNCTION__, [
            'NewID' => $ID
        ]) !== false;
    }

    /**
     * Liefert den Pfad zur XML-Liste aller DECT-Geräte.
     *
     * @return string|false
     */
    public function GetDectListPath(): mixed
    {
        return $this->Send(__FUNCTION__);
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
            case \FritzBox\Base\Action::RefreshInfo:
                return $this->UpdateInfo();
            case \FritzBox\Tile\Action::DeviceUpdate:
                // Nur für Geräte der Liste (Kennung aus der FritzBox), die online sind
                if (!$this->TileDeviceReadyForUpdate($this->TileDevices, (string) $Value)) {
                    return false;
                }
                return $this->DectDoUpdate((string) $Value);
        }
        trigger_error($this->Translate('Invalid Ident.'), E_USER_NOTICE);
        return false;
    }

    /**
     * Daten der Kachel: je Gerät die Gerätevariable und, sofern angelegt, Modell und verfügbares Update.
     */
    protected function GetTileData(): array
    {
        $Devices = [];
        foreach ($this->TileDevices as $Device) {
            $Variable = $this->TileVariable($Device['ident']);
            if ($Variable === null) {
                continue;
            }
            $Devices[] = [
                'key'    => $Device['key'],
                'device' => $Variable,
                'model'  => $this->TileVariable(\FritzBox\DECT\Variables::Model . '_' . $Device['ident']),
                'update' => $this->TileVariable(\FritzBox\DECT\Variables::UpdateAvailable . '_' . $Device['ident'])
            ];
        }
        return ['devices' => $Devices];
    }

    protected function GetTileTextKeys(): array
    {
        return ['Devices', 'active', 'inactive', 'Update available', 'Start update', 'Start the update of %s?', 'No devices'];
    }

    protected function GetTileScriptFile(): string
    {
        return __DIR__ . '/../libs/tile/devices.js';
    }

    private function UpdateInfo()
    {
        $NumberOfEntries = $this->GetNumberOfDectEntries();
        if ($NumberOfEntries === false) {
            return false;
        }
        $ReturnValue = true;
        $Rename = $this->ReadPropertyBoolean(\FritzBox\DECT\Property::RenameDeviceVariables);
        $ShowModel = $this->ReadPropertyBoolean(\FritzBox\DECT\Property::ModelVariables);
        $TileDevices = [];
        $ShowUpdate = $this->ReadPropertyBoolean(\FritzBox\DECT\Property::UpdateAvailableVariables);
        for ($i = 0; $i < $NumberOfEntries; $i++) {
            $Result = $this->GetGenericDectEntry($i);
            if ($Result === false) {
                $ReturnValue = false;
                continue;
            }
            $Ident = 'DECT' . $this->ConvertIdent((string) $Result['NewID']);
            $Name = (string) $Result['NewName'];
            $this->setIPSVariable($Ident, $Name, (int) $Result['NewActive'] > 0, VARIABLETYPE_BOOLEAN, '~Switch', false);
            $VarId = $this->GetIDForIdent($Ident);
            if ($Rename && ($Name != '') && (IPS_GetName($VarId) != $Name)) {
                IPS_SetName($VarId, $Name);
            }
            $DeviceName = IPS_GetName($VarId);
            $this->SetDeviceInfoVariable($ShowModel, \FritzBox\DECT\Variables::Model, $Ident, $DeviceName . ' - ' . $this->Translate('Model'), (string) $Result['NewModel'], VARIABLETYPE_STRING, Rename: $Rename);
            $this->SetDeviceInfoVariable($ShowUpdate, \FritzBox\DECT\Variables::UpdateAvailable, $Ident, $DeviceName . ' - ' . $this->Translate('Update available'), (int) $Result['NewUpdateAvailable'] > 0, VARIABLETYPE_BOOLEAN, Rename: $Rename);
            $TileDevices[] = ['ident' => $Ident, 'key' => (string) $Result['NewID']];
        }
        $this->TileDevices = $TileDevices;
        $this->PushTile();
        return $ReturnValue;
    }
}
