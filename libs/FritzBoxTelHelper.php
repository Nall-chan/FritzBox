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
        $id = IPS_GetInstanceListByModuleID(\FritzBox\GUID::UtilControl)[0];
        $Icons = [];
        $Icons[] = ['caption' => '<none>', 'value' => ''];
        foreach (UC_GetIconList($id) as $Icon) {
            $Icons[] = ['caption' => $Icon, 'value' => $Icon];
        }
        return $Icons;
    }

    private function DoReverseSearch(string $Number, string $SearchMarker, string $UnknownName, int $MaxNameSize)
    {
        $ReverseSearchInstanceID = $this->ReadPropertyInteger('ReverseSearchInstanceID');
        $CustomSearchScriptID = $this->ReadPropertyInteger('CustomSearchScriptID');

        if ($CustomSearchScriptID > 1) {
            return IPS_RunScriptWaitEx($CustomSearchScriptID, ['SENDER'=>'FritzBox', 'NUMBER'=>$Number]);
        }

        if ($ReverseSearchInstanceID > 1) {
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

    private function DoPhonebookSearch(string $Number, int $MaxNameSize, string $AreaCode = '', string $CountryCode = '')
    {
        $Name = false;
        $Files = $this->GetPhoneBookFiles();
        $SerachNumbers[] = $Number;
        switch ($Number[0]) {
            case '+':
                break;
            case '0':
                if ($CountryCode != '') {
                    $SerachNumbers[] = $CountryCode . substr($Number, 1);
                }
                if ($AreaCode != '') {
                    if (strpos($Number, $AreaCode) === 0) {
                        $SerachNumbers[] = substr($Number, strlen($AreaCode));
                    }
                }
                break;
            default:
                if ($AreaCode != '') {
                    $SerachNumbers[] = $AreaCode . $Number;
                    if ($CountryCode != '') {
                        $SerachNumbers[] = $CountryCode . substr($AreaCode, 1) . $Number;
                    }
                }
                break;
        }
        foreach ($Files as $File) {
            $XMLData = $this->GetFile($File);
            if ($XMLData === false) {
                $this->SendDebug('XML not found', $File, 0);
                continue;
            }
            try {
                $XMLPhoneBook = new \simpleXMLElement($XMLData);
            } catch (\Throwable $th) {
                $this->SendDebug('XML decode error', $XMLData, 0);
                continue;
            }
            foreach ($SerachNumbers as $SerachNumber) {
                $this->SendDebug('Search for', $SerachNumber, 0);
                $Contact = $XMLPhoneBook->xpath("//contact[telephony/number ='" . $SerachNumber . "']");
                if (count($Contact) > 0) {
                    try {
                        $Name = (string) $Contact[0]->person->realName;
                        if (strlen($Name) > $MaxNameSize) {
                            $Name = substr($Name, 0, $MaxNameSize);
                        }
                        break 2;
                    } catch (\Exception $exc) {
                        continue;
                    }
                }
            }
        }
        return $Name;
    }

    private function GetNameByNumber(string $Number, string $AreaCode = '', string $CountryCode = '')
    {
        if ($Number == '') {
            return $this->ReadPropertyString('UnknownNumberName');
        }
        $Name = $this->DoPhonebookSearch($Number, $this->ReadPropertyInteger('MaxNameSize'), $AreaCode, $CountryCode);
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
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'SetPhoneDevices',
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
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'GetPhoneDevice',
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
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'GetPhoneDevices'
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
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'GetPhonebooks'
            ]
        ));
        if ($Ret === false) {
            return false;
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }

    private function GetAreaCodes(): array
    {
        if (!$this->HasActiveParent()) {
            return [];
        }
        $this->SendDebug('Function', 'GetAreaCodes', 0);
        $Ret = $this->SendDataToParent(json_encode(
            [
                'DataID'     => \FritzBox\GUID::SendToFritzBoxIO,
                'Function'   => 'GetAreaCodes'
            ]
        ));
        if ($Ret === false) {
            return [];
        }
        $Result = unserialize($Ret);
        $this->SendDebug('Result', $Result, 0);
        return $Result;
    }
    /**
     * @param SimpleXMLElement $xml
     * @return array
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $parser = function (\SimpleXMLElement $xml, array $collection = []) use (&$parser)
        {
            $nodes = $xml->children();
            $attributes = $xml->attributes();

            if (0 !== count($attributes)) {
                foreach ($attributes as $attrName => $attrValue) {
                    $collection['attributes'][$attrName] = strval($attrValue);
                }
            }

            if (0 === $nodes->count()) {
                $collection['value'] = strval($xml);
                return $collection;
            }

            foreach ($nodes as $nodeName => $nodeValue) {
                if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                    $collection[$nodeName] = $parser($nodeValue);
                    continue;
                }

                $collection[$nodeName][] = $parser($nodeValue);
            }

            return $collection;
        };

        return [
            $xml->getName() => $parser($xml)
        ];
    }
}
