<?php

declare(strict_types=1);
namespace FritzBoxModul;

trait TelHelper
{
    private function DoReverseSearch(int $ReverseSearchInstanceID, int $CustomSearchScriptID, string $Number, string $UnknownName, string $SearchMarker, int $MaxNameSize)
    {
        if ($CustomSearchScriptID !=0) {
            return IPS_RunScriptWaitEx($CustomSearchScriptID, ['SENDER'=>'FritzBox','NUMBER'=>$Number]);
        }

        if ($ReverseSearchInstanceID !=0) {
            $Name = CIRS_GetName($ReverseSearchInstanceID, $Number);
            if ($Name === false) {
                return $UnknownName;
            }
            if (strlen($Name)>$MaxNameSize) {
                $Name=substr($Name, 0, $MaxNameSize);
            }
            return $SearchMarker.$Name;
        }
        return $UnknownName;
    }

    private function DoPhonebookSearch(string $Number)
    {
        $Name = false;
        $Files = $this->GetPhoneBookFiles();
        foreach ($Files as $File) {
            $XMLData = $this->GetFile($File);
            if ($XMLData === false) {
                $this->SendDebug('XML not found', $File, 0);
                continue;
            }
            $XMLPhoneBook = new \simpleXMLElement($XMLData);
            if ($XMLPhoneBook === false) {
                $this->SendDebug('XML decode error', $XMLData, 0);
                continue;
            }
            $Contact = $XMLPhoneBook->xpath("//contact[telephony/number ='".$Number."']");
            if (sizeof($Contact) > 0) {
                try {
                    $Name = (string)$Contact[0]->person->realName;
                    break;
                } catch (\Exception $exc) {
                    continue;
                }
            }
        }
        return $Name;
    }

    private function SetPhoneDevices(array $PhoneDevices)
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        $this->SendDebug('Function', 'SetPhoneDevices', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'SETPHONEDEVICES',
                'Devices'    => $PhoneDevices
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    private function GetPhoneDeviceNameByID(int $DeviceID)
    {
        if (!$this->HasActiveParent()) {
            return '';
        }
        $this->SendDebug('Function', 'GetPhoneDevices', 0);
        $Ret = $this->SendDataToParent(json_encode(
        [
            'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
            'Function'   => 'GETPHONEDEVICE',
            'DeviceID'   => $DeviceID
        ]
        ));
        if ($Ret === false) {
            return '';
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    private function GetPhoneDevices()
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        $this->SendDebug('Function', 'GetPhoneDevices', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'GETPHONEDEVICES'
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    private function GetPhoneBookFiles() : array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        $this->SendDebug('Function', 'GetPhoneBookFiles', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => '{D62D4515-7689-D1DB-EE97-F555AD9433F0}',
                'Function'   => 'GETPHONEBOOKS'
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    protected function ArrayWithCurlyBracketsKey($Source)
    {
        $Target = [];
        foreach ($Source as $key=>$value) {
            $Target['{'. strtoupper($key).'}'] = $value;
        }
        return $Target;
    }
    protected function ArrayKeyToUpper($Source)
    {
        $Target = [];
        foreach ($Source as $key=>$value) {
            $Target[strtoupper($key)]= $value;
        }
        return $Target;
    }
    protected function GetIconsList()
    {
        $id = IPS_GetInstanceListByModuleID('{B69010EA-96D5-46DF-B885-24821B8C8DBD}')[0];
        $Icons = array();
        $Icons[] = ['caption' => '<none>', 'value' => ''];
        foreach (UC_GetIconList($id) as $Icon) {
            $Icons[] = ['caption' => $Icon, 'value' => $Icon];
        }
        return $Icons;
    }
}
