<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');
require_once __DIR__ . '/../libs/FritzBoxModule.php';
/**
 * @method bool SendDebug(string $Message, mixed $Data, int $Format)
 */
class FritzBoxDiscovery extends IPSModule
{
    use \FritzBoxDiscovery\DebugHelper;
    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    public const WS_DISCOVERY_TIMEOUT = 3;

    /**
     * The multicast address to use in the socket for the discovery request.
     */
    public const WS_DISCOVERY_MULTICAST_ADDRESS = '239.255.255.250';
    public const WS_DISCOVERY_MULTICAST_ADDRESSV6 = '[ff02::c]';

    /**
     * The port that will be used in the socket for the discovery request.
     */
    public const WS_DISCOVERY_MULTICAST_PORT = 1900;

    public function Create()
    {
        //Never delete this line!
        parent::Create();
    }

    public function Destroy()
    {
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
    }

    public function GetConfigurationForm()
    {
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        if ($this->GetStatus() == IS_CREATING) {
            return json_encode($Form);
        }
        if (IPS_GetOption('NATSupport') && strpos(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Multicast :(
            $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate("The combination of Docker and NAT is not supported because Docker does not support multicast.\r\nPlease run the container in the host network.\r\nOr create and configure the required FritzBox Configurator instance manually.");
            $Form['actions'][1]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $Devices = $this->Discover();
        $InstanceIDListConfigurators = IPS_GetInstanceListByModuleID(\FritzBox\GUID::Configurator);
        $DevicesAddress = [];
        $DeviceValues = [];
        foreach ($InstanceIDListConfigurators as $InstanceIDConfigurator) {
            $Splitter = IPS_GetInstance($InstanceIDConfigurator)['ConnectionID'];
            if ($Splitter > 0) {
                $Url = parse_url(IPS_GetProperty($Splitter, \FritzBox\IO\Property::Host), PHP_URL_HOST);
                $DevicesAddress[$InstanceIDConfigurator] = ($Url ? $Url : '');
            }
        }
        foreach ($Devices as $UUID => $Data) {
            $Data['Hosts'] = array_unique($Data['Hosts']);
            ksort($Data['Hosts']);
            $AddDevice = [
                'instanceID'        => 0,
                'uuid'              => $UUID,
                'host'              => $Data['Hosts'][array_key_last($Data['Hosts'])],
                'name'              => $Data['Server']
            ];
            foreach ($Data['Hosts'] as $Host) {
                $InstanceIDConfigurator = array_search($Host, $DevicesAddress);
                if ($InstanceIDConfigurator !== false) {
                    $AddDevice['name'] = IPS_GetLocation($InstanceIDConfigurator);
                    $AddDevice['instanceID'] = $InstanceIDConfigurator;
                    $AddDevice['host'] = $Host;
                    unset($DevicesAddress[$InstanceIDConfigurator]);
                }

                // Die IO fordert einen Client Socket als Parent (nur für den Anrufmonitor).
                // Er wird geschlossen angelegt und erst geöffnet, wenn ein Anrufmonitor angelegt wird.
                $CallMonitorSocket = [
                    'moduleID'      => \FritzBox\GUID::ClientSocket,
                    'configuration' => [
                        \FritzBox\ClientSocket\Property::Host => trim($Host, '[]'),
                        \FritzBox\ClientSocket\Property::Port => 1012,
                        \FritzBox\ClientSocket\Property::Open => false
                    ]
                ];
                $AddDevice['create']['https://' . $Host] = [
                    [
                        'moduleID'      => \FritzBox\GUID::Configurator,
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => \FritzBox\GUID::FritzBoxIO,
                        'configuration' => [
                            \FritzBox\IO\Property::Host     => 'https://' .
                            $Host
                        ]
                    ],
                    $CallMonitorSocket
                ];
                $AddDevice['create']['http://' . $Host] = [
                    [
                        'moduleID'      => \FritzBox\GUID::Configurator,
                        'configuration' => new stdClass()
                    ],
                    [
                        'moduleID'      => \FritzBox\GUID::FritzBoxIO,
                        'configuration' => [
                            \FritzBox\IO\Property::Host     => 'http://' .
                            $Host
                        ]
                    ],
                    $CallMonitorSocket
                ];
            }
            $DeviceValues[] = $AddDevice;
        }
        foreach ($DevicesAddress as $id => $Url) {
            $AddDevice = [
                'instanceID'        => $id,
                'uuid'              => '',
                'host'              => $Url,
                'name'              => IPS_GetLocation($id)
            ];
            $DeviceValues[] = $AddDevice;
        }
        $Form['actions'][0]['values'] = $DeviceValues;
        if (count($Devices) == 0) {
            $Form['actions'][1]['visible'] = true;
        }
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    /**
     * Sucht per SSDP auf allen lokalen Adressen gleichzeitig nach FritzBoxen.
     *
     * Erst werden alle Sockets geöffnet und die M-SEARCH gesendet, danach wird
     * auf allen Sockets gemeinsam bis zum Timeout auf Antworten gewartet.
     * So hängt die Laufzeit nicht von der Anzahl der lokalen Adressen ab
     * (Skript-Limit von 30 Sekunden unter Symcon 9.1).
     */
    protected function Discover(): array
    {
        $Interfaces = $this->getIPAdresses();
        $Sockets = [];
        $SocketFamily = [];
        foreach (['ipv6', 'ipv4'] as $Family) {
            foreach ($Interfaces[$Family] as $IP => $Interface) {
                $Socket = $this->SendSearch($Family, (string) $IP, $Interface);
                if ($Socket !== false) {
                    $Sockets[] = $Socket;
                    $SocketFamily[] = $Family;
                }
            }
        }
        $Responses = $this->ReceiveResponses($Sockets, $SocketFamily);
        foreach ($Sockets as $Socket) {
            socket_close($Socket);
        }

        $DevicesData = [];
        $HostNames = [];
        $Index = ['ipv6' => 0, 'ipv4' => 0];
        foreach ($Responses as $Response) {
            $Family = $Response['Family'];
            $Data = $Response['Data'];
            $USN = explode(':', $Data['USN'])[1];
            $IPAddress = parse_url($Data['LOCATION'], PHP_URL_HOST);
            $PlainIP = ($Family == 'ipv6') ? substr($IPAddress, 1, -1) : $IPAddress;
            // Link-local (fe80::/10) ist ohne Zonen-ID als Host nicht nutzbar.
            if (($Family == 'ipv6') && $this->IsLinkLocalIPv6($PlainIP)) {
                $this->SendDebug('Skip link-local', $PlainIP, 0);
                continue;
            }
            // Rückwärtsauflösung nur einmal je Adresse, sie kann bei fehlendem DNS-Eintrag lange dauern.
            if (!array_key_exists($PlainIP, $HostNames)) {
                $HostNames[$PlainIP] = gethostbyaddr($PlainIP);
            }
            $Host = $HostNames[$PlainIP];
            $i = $Index[$Family]++;
            if ($Family == 'ipv6') {
                $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $IPAddress, 20 + $i);
                if ($Host != $PlainIP) {
                    $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $Host, $i);
                }
            } else {
                $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $IPAddress, 60 + $i);
                if ($Host != $PlainIP) {
                    $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $Host, 40 + $i);
                }
            }
            $this->SendDebug('Receive (' . $USN . ')', ['SERVER' => $Data['SERVER'], 'HOST' => $Host], 0);
        }
        $this->SendDebug('DevicesData', $DevicesData, 0);
        return $DevicesData;
    }

    /**
     * Öffnet einen Socket auf der lokalen Adresse und sendet die M-SEARCH.
     *
     * @param string $Family    'ipv6' oder 'ipv4'
     * @param string $IP        Lokale Adresse (IPv6 in eckigen Klammern)
     * @param mixed  $Interface Index der Netzwerkschnittstelle
     *
     * @return \Socket|false Der Socket, oder false bei einem Fehler
     */
    private function SendSearch(string $Family, string $IP, mixed $Interface): \Socket|false
    {
        $IsV6 = ($Family == 'ipv6');
        $Socket = socket_create($IsV6 ? AF_INET6 : AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$Socket) {
            $this->SendDebug('Error on create Socket ' . $Family, $IP, 0);
            return false;
        }
        socket_set_option($Socket, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($IsV6) {
            socket_set_option($Socket, IPPROTO_IPV6, IPV6_MULTICAST_HOPS, 4);
            socket_set_option($Socket, IPPROTO_IPV6, IPV6_MULTICAST_IF, $Interface);
        } else {
            socket_set_option($Socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);
            socket_set_option($Socket, IPPROTO_IP, IP_MULTICAST_IF, $Interface);
        }
        socket_set_option($Socket, SOL_SOCKET, SO_BROADCAST, 1);
        if (@socket_bind($Socket, $IP, 1901) == false) {
            socket_close($Socket);
            return false;
        }
        $MulticastAddress = $IsV6 ? self::WS_DISCOVERY_MULTICAST_ADDRESSV6 : self::WS_DISCOVERY_MULTICAST_ADDRESS;
        $Message = [
            'M-SEARCH * HTTP/1.1',
            'ST: urn:dslforum-org:device:InternetGatewayDevice:1',
            'MAN: "ssdp:discover"',
            'MX: 5',
            'HOST: ' . $MulticastAddress . ':1900',
            'Content-Length: 0'
        ];
        $SendData = implode("\r\n", $Message) . "\r\n\r\n";
        $this->SendDebug('Start Discovery(' . $Interface . ')', $IP, 0);
        $this->SendDebug('Search', $SendData, 0);
        if (@socket_sendto($Socket, $SendData, strlen($SendData), 0, $MulticastAddress, self::WS_DISCOVERY_MULTICAST_PORT) === false) {
            $this->SendDebug('Error on send discovery message', $IP, 0);
            socket_close($Socket);
            return false;
        }
        socket_set_nonblock($Socket);
        return $Socket;
    }

    /**
     * Wartet auf allen Sockets gemeinsam bis zum Timeout auf Antworten von FritzBoxen.
     *
     * @param array $Sockets      Die Sockets aus SendSearch
     * @param array $SocketFamily 'ipv6' oder 'ipv4' je Socket (gleicher Index)
     *
     * @return array Liste mit ['Family' => ..., 'Data' => Header der Antwort]
     */
    private function ReceiveResponses(array $Sockets, array $SocketFamily): array
    {
        $Responses = [];
        $DiscoveryTimeout = microtime(true) + self::WS_DISCOVERY_TIMEOUT;
        while (count($Sockets) > 0) {
            $Remaining = $DiscoveryTimeout - microtime(true);
            if ($Remaining <= 0) {
                break;
            }
            $Read = $Sockets;
            $Write = null;
            $Except = null;
            $Seconds = (int) $Remaining;
            if (@socket_select($Read, $Write, $Except, $Seconds, (int) (($Remaining - $Seconds) * 1000000)) < 1) {
                continue;
            }
            foreach ($Read as $Socket) {
                $Key = array_search($Socket, $Sockets, true);
                $Response = '';
                $IPAddress = '';
                $Port = 0;
                while (@socket_recvfrom($Socket, $Response, 2048, 0, $IPAddress, $Port) > 0) {
                    $this->SendDebug('Receive (' . $IPAddress . ')', $Response, 0);
                    $Data = $this->parseHeader($Response);
                    if (!array_key_exists('SERVER', $Data)) {
                        continue;
                    }
                    if (!array_key_exists('USN', $Data)) {
                        continue;
                    }
                    if (!array_key_exists('LOCATION', $Data)) {
                        continue;
                    }
                    if (strpos($Data['SERVER'], 'AVM') === false) {
                        continue;
                    }
                    if (!in_array(parse_url($Data['LOCATION'], PHP_URL_PATH), ['/igddesc.xml', '/igd2desc.xml', '/tr64desc.xml'])) {
                        continue;
                    }
                    $Responses[] = ['Family' => $SocketFamily[$Key], 'Data' => $Data];
                }
            }
        }
        return $Responses;
    }

    /**
     * Prüft, ob eine IPv6-Adresse (ohne eckige Klammern) im Bereich fe80::/10 liegt.
     */
    private function IsLinkLocalIPv6(string $IP): bool
    {
        $Binary = @inet_pton($IP);
        if (($Binary === false) || (strlen($Binary) != 16)) {
            return false;
        }
        return (ord($Binary[0]) == 0xFE) && ((ord($Binary[1]) & 0xC0) == 0x80);
    }

    private function parseHeader(string $Data): array
    {
        $Lines = explode("\r\n", $Data);
        array_shift($Lines);
        array_pop($Lines);
        $Header = [];
        foreach ($Lines as $Line) {
            $line_array = explode(':', $Line);
            $Header[strtoupper(trim(array_shift($line_array)))] = trim(implode(':', $line_array));
        }
        return $Header;
    }

    private function getIPAdresses()
    {
        $Interfaces = SYS_GetNetworkInfo();
        $InterfaceDescriptions = array_column($Interfaces, 'Description', 'InterfaceIndex');
        $Networks = net_get_interfaces();
        $Addresses = [];
        $Addresses['ipv6'] = [];
        $Addresses['ipv4'] = [];
        foreach ($Networks as $InterfaceDescription => $Interface) {
            if (!$Interface['up']) {
                continue;
            }
            if (array_key_exists('description', $Interface)) {
                $InterfaceDescription = array_search($Interface['description'], $InterfaceDescriptions);
            }
            foreach ($Interface['unicast'] as $Address) {
                // Einträge ohne Adresse (z.B. WireGuard/tun unter Linux) enthalten nur 'flags'
                if (!isset($Address['family'], $Address['address'])) {
                    continue;
                }
                switch ($Address['family']) {
                    case AF_INET6:
                        if ($Address['address'] == '::1') {
                            continue 2;
                        }
                        $Address['address'] = '[' . $Address['address'] . ']';
                        $family = 'ipv6';
                        break;
                    case AF_INET:
                        if ($Address['address'] == '127.0.0.1') {
                            continue 2;
                        }
                        $family = 'ipv4';
                        break;
                    default:
                        continue 2;
                }
                $Addresses[$family][$Address['address']] = $InterfaceDescription;
            }
        }
        return $Addresses;
    }

    private function AddDiscoveryEntry(&$DevicesData, $USN, $Server, $Host, $Index)
    {
        if (array_key_exists($USN, $DevicesData)) {
            if (!in_array($Host, $DevicesData[$USN]['Hosts'])) {
                $DevicesData[$USN]['Hosts'][$Index] = strtolower($Host);
            }
        } else {
            $DevicesData[$USN]['Server'] = $Server;
            $DevicesData[$USN]['Hosts'][$Index] = strtolower($Host);
        }
    }
}
