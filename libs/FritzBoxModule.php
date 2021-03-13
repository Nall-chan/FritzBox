<?php

declare(strict_types=1);

namespace FritzBox;

class Services
{
    public static $Data = [
        //Fertig (bis auf HTML-Tabellen)
        'urn:dslforum-org:service:WLANConfiguration:1'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>0],
        'urn:dslforum-org:service:WLANConfiguration:2'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>1],
        'urn:dslforum-org:service:WLANConfiguration:3'           => ['{B3D72623-556E-B6C6-25E0-B3DEFE41F031}'=>2],
        'urn:dslforum-org:service:Hosts:1'                       => ['{66495783-EAEF-7A90-B13C-399045E4790B}'=>0],
        'urn:dslforum-org:service:LANHostConfigManagement:1'     => ['{BDD8382D-00EF-4D84-8B3E-795584ABEB12}'=>0],
        'urn:dslforum-org:service:DeviceInfo:1'                  => ['{0E5BA3F0-4622-4C96-8D5F-F28DAB051C2F}'=>0],
        'urn:schemas-upnp-org:service:WANCommonInterfaceConfig:1'=> ['{FD564AAF-E00A-4CF8-A60D-7F60B4CDFC1B}'=>1], //okay, Timer fehlen
        //TODO
        //prüfen / Fertig bauen
        'urn:dslforum-org:service:WANPPPConnection:1'            => ['{9396D756-40EA-46C7-AA06-623B8DCB789B}'=>0], //bei DSL
        'urn:dslforum-org:service:WANIPConnection:1'             => ['{9396D756-40EA-46C7-AA06-623B8DCB789B}'=>1], //bei Cabel
        'urn:schemas-upnp-org:service:WANIPConnection:1'         => ['{61C9DC95-5A0F-7B9A-4427-82EDB57AA1B9}'=>0],
        'urn:schemas-upnp-org:service:WANIPConnection:2'         => ['{61C9DC95-5A0F-7B9A-4427-82EDB57AA1B9}'=>1],
        'urn:schemas-upnp-org:service:WANDSLLinkConfig:1'        => ['{061AEAF5-ADCD-AD9E-9BF9-AD40DF364EB6}'=>0], // TODO
        //Todo
        'urn:dslforum-org:service:Time:1'                        => ['{4BD2D88F-E56B-9DF1-19A2-E6A688C5EA70}'=>0],
        'urn:dslforum-org:service:Layer3Forwarding:1'            => [],
        'urn:dslforum-org:service:LANConfigSecurity:1'           => [],
        'urn:dslforum-org:service:ManagementServer:1'            => [],
        'urn:dslforum-org:service:UserInterface:1'               => [],
        'urn:dslforum-org:service:X_AVM-DE_Storage:1'            => [],
        'urn:dslforum-org:service:X_AVM-DE_WebDAVClient:1'       => [],
        'urn:dslforum-org:service:X_AVM-DE_UPnP:1'               => [],
        'urn:dslforum-org:service:X_AVM-DE_Speedtest:1'          => [],
        'urn:dslforum-org:service:X_AVM-DE_RemoteAccess:1'       => [],
        'urn:dslforum-org:service:X_AVM-DE_MyFritz:1'            => [],
        'urn:dslforum-org:service:X_VoIP:1'                      => [],
        'urn:dslforum-org:service:X_AVM-DE_OnTel:1'              => ['{AD0B22A7-C71C-71DD-A40B-B70334C5AB3C}'=>0],
        'urn:dslforum-org:service:X_AVM-DE_Dect:1'               => [],
        'urn:dslforum-org:service:X_AVM-DE_TAM:1'                => [],
        'urn:dslforum-org:service:X_AVM-DE_AppSetup:1'           => [],
        //'urn:dslforum-org:service:X_AVM-DE_Homeauto:1'           => [],
        //'urn:dslforum-org:service:X_AVM-DE_Homeplug:1'           => [],
        'urn:dslforum-org:service:X_AVM-DE_Filelinks:1'          => [],
        //'urn:dslforum-org:service:X_AVM-DE_Auth:1'               => [],
        //'urn:dslforum-org:service:LANEthernetInterfaceConfig:1'  => [], //statistik. unnötig?
        //'urn:dslforum-org:service:WANDSLInterfaceConfig:1'       => [], //statistik. unnötig?
        //'urn:dslforum-org:service:WANEthernetLinkConfig:1'       => [], // unnötig?
        //'urn:dslforum-org:service:WANDSLLinkConfig:1'            => [], // unnötig ? DSL Daten?
        'urn:schemas-upnp-org:service:WANIPv6FirewallControl:1'  => [],
        // Dummy
        'urn:schemas-any-com:service:Any:1'                      => []
        // unnötig
        //'urn:dslforum-org:service:WANCommonInterfaceConfig:1'    => ['{FD564AAF-E00A-4CF8-A60D-7F60B4CDFC1B}'=>0], //unnötig!
        //'urn:dslforum-org:service:DeviceConfig:1'                  => [],
    ];
}
