<?php

declare(strict_types=1);

namespace FritzBoxModul;

trait TelHelper
{
    protected static $NumberToID = [
        50  => 50,
        51  => 51,
        52  => 52,
        53  => 53,
        54  => 54,
        55  => 55,
        56  => 56,
        57  => 57,
        58  => 58,
        329 => 5,
        610 => 10,
        611 => 11,
        612 => 12,
        613 => 13,
        614 => 14,
        615 => 15,
        616 => 16,
        617 => 17,
        618 => 18,
        619 => 19,
        620 => 20,
        621 => 21,
        622 => 22,
        623 => 23,
        624 => 24,
        625 => 25,
        626 => 26,
        627 => 27,
        628 => 28,
        629 => 29,
        600 => 40,
        601 => 41,
        602 => 42,
        603 => 43,
        604 => 44,
        605 => 45,
        606 => 46,
        607 => 47,
        608 => 48,
        609 => 49
    ];

    protected function ArrayWithCurlyBracketsKey($Source)
    {
        $Target = [];
        foreach ($Source as $key=>$value) {
            $Target['{' . strtoupper($key) . '}'] = $value;
        }
        return $Target;
    }
    protected function ArrayKeyToUpper($Source)
    {
        $Target = [];
        foreach ($Source as $key=>$value) {
            $Target[strtoupper($key)] = $value;
        }
        return $Target;
    }
    protected function GetIconsList()
    {
        $id = IPS_GetInstanceListByModuleID('{B69010EA-96D5-46DF-B885-24821B8C8DBD}')[0];
        $Icons = [];
        $Icons[] = ['caption' => '<none>', 'value' => ''];
        foreach (UC_GetIconList($id) as $Icon) {
            $Icons[] = ['caption' => $Icon, 'value' => $Icon];
        }
        return $Icons;
    }
    private function DoReverseSearch(int $ReverseSearchInstanceID, int $CustomSearchScriptID, string $Number, string $UnknownName, string $SearchMarker, int $MaxNameSize)
    {
        if ($CustomSearchScriptID != 0) {
            return IPS_RunScriptWaitEx($CustomSearchScriptID, ['SENDER'=>'FritzBox', 'NUMBER'=>$Number]);
        }

        if ($ReverseSearchInstanceID != 0) {
            $Name = CIRS_GetName($ReverseSearchInstanceID, $Number);
            if ($Name === false) {
                return $UnknownName;
            }
            if (strlen($Name) > $MaxNameSize) {
                $Name = substr($Name, 0, $MaxNameSize);
            }
            return $SearchMarker . $Name;
        }
        return $UnknownName;
    }

    private function DoPhonebookSearch(string $Number, int $MaxNameSize)
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
            $Contact = $XMLPhoneBook->xpath("//contact[telephony/number ='" . $Number . "']");
            if (count($Contact) > 0) {
                try {
                    $Name = (string) $Contact[0]->person->realName;
                    if (strlen($Name) > $MaxNameSize) {
                        $Name = substr($Name, 0, $MaxNameSize);
                    }
                    break;
                } catch (\Exception $exc) {
                    continue;
                }
            }
        }
        return $Name;
    }
    private function GetPhoneDeviceNumberByID(int $ID)
    {
        return array_search($ID, self::$NumberToID, false);
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
            $Number = $this->GetPhoneDeviceNumberByID($DeviceID);
            $Result = $this->DoPhonebookSearch('**' . $Number, 50);
            $this->SendDebug('Result', $Result, 0);
            if ($Result === false) {
                $Result = '';
            }
        } else {
            $Result = unserialize($Ret);
            $this->SendDebug('Result', $Result, 0);
        }
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
    private function GetPhoneBookFiles(): array
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
}
