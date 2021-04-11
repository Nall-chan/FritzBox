<?php

declare(strict_types=1);
namespace FritzBoxModul;


trait TelHelper
{
    private function DoReverseSearch(int $ReverseSearchInstanceID, int $CustomSearchScriptID, string $Number, string $UnknownName, string $SearchMarker, int $MaxNameSize)
    {
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
        if ($CustomSearchScriptID !=0) {
            $Name = IPS_RunScriptWaitEx($CustomSearchScriptID, ['NUMBER'=>$Number]);
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

    private function DoPhonebookSearch(string $Number){

    }

    private function SetPhoneDevice(int $DeviceID, string $DeviceName){

    }
    private function GetPhoneDeviceName(int $DeviceID){
        
    }

}