<?php

declare(strict_types=1);

eval('declare(strict_types=1);namespace FritzBoxDiscovery {?>' . file_get_contents(__DIR__ . '/../libs/helper/DebugHelper.php') . '}');

class FritzBoxDiscovery extends IPSModule
{
    use \FritzBoxDiscovery\DebugHelper;
    /**
     * The maximum number of seconds that will be allowed for the discovery request.
     */
    const WS_DISCOVERY_TIMEOUT = 3;

    /**
     * The multicast address to use in the socket for the discovery request.
     */
    const WS_DISCOVERY_MULTICAST_ADDRESS = '239.255.255.250';
    const WS_DISCOVERY_MULTICAST_ADDRESSV6 = '[ff02::c]';

    /**
     * The port that will be used in the socket for the discovery request.
     */
    const WS_DISCOVERY_MULTICAST_PORT = 1900;

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
        if (IPS_GetOption('NATSupport') && strpos(IPS_GetKernelPlatform(), 'Docker')) {
            // not supported. Docker cannot forward Multicast :(
            $Form['actions'][1]['popup']['items'][1]['caption'] = $this->Translate("The combination of Docker and NAT is not supported because Docker does not support multicast.\r\nPlease run the container in the host network.\r\nOr create and configure the required FritzBox Configurator instance manually.");
            $Form['actions'][1]['visible'] = true;
            $this->SendDebug('FORM', json_encode($Form), 0);
            $this->SendDebug('FORM', json_last_error_msg(), 0);
            return json_encode($Form);
        }
        $Devices = $this->Discover();
        $InstanceIDListConfigurators = IPS_GetInstanceListByModuleID('{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}');
        $DevicesAddress = [];
        $DeviceValues = [];
        foreach ($InstanceIDListConfigurators as $InstanceIDConfigurator) {
            $Splitter = IPS_GetInstance($InstanceIDConfigurator)['ConnectionID'];
            if ($Splitter > 0) {
                $DevicesAddress[$InstanceIDConfigurator] = parse_url(IPS_GetProperty($Splitter, 'Host'), PHP_URL_HOST);
            }
        }
        foreach ($Devices as $UUID => $Data) {
            ksort($Data['Hosts']);
            $AddDevice = [
                'instanceID'        => 0,
                'uuid'              => $UUID,
                'host'              => $Data['Hosts'][array_key_first($Data['Hosts'])],
                'name'              => $Data['Server']
            ];
            foreach ($Data['Hosts'] as $Host) {
                $InstanceIDConfigurator = array_search($Host, $DevicesAddress);
                if ($InstanceIDConfigurator !== false) {
                    $AddDevice['name'] = IPS_GetLocation($InstanceIDConfigurator);
                    $AddDevice['instanceID'] = $InstanceIDConfigurator;
                    unset($DevicesAddress[$InstanceIDConfigurator]);
                    break;
                }
            }
            if ($AddDevice['instanceID'] == 0) {
                $Host = $Data['Hosts'][array_key_first($Data['Hosts'])];
            }
            $AddDevice['create']['https://' . $Host] = [
                [
                    'moduleID'      => '{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}',
                    'configuration' => new stdClass()
                ],
                [
                    'moduleID'      => '{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}',
                    'configuration' => [
                        'Host'     => 'https://' .
                        $Host
                    ]
                ]
            ];
            $AddDevice['create']['http://' . $Host] = [
                [
                    'moduleID'      => '{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}',
                    'configuration' => new stdClass()
                ],
                [
                    'moduleID'      => '{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}',
                    'configuration' => [
                        'Host'     => 'http://' .
                        $Host
                    ]
                ]

            ];

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

    protected function Discover(): array
    {
        $Interfaces = $this->getIPAdresses();
        $DevicesData = [];
        $Index = 0;
        foreach ($Interfaces['ipv6'] as $IP => $Interface) {
            $socket = socket_create(AF_INET6, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
                socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket, IPPROTO_IPV6, IPV6_MULTICAST_HOPS, 4);
                socket_set_option($socket, IPPROTO_IPV6, IPV6_MULTICAST_IF, $Interface);
                socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
                socket_bind($socket, $IP, 1901);
                $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
                $message = [
                    'M-SEARCH * HTTP/1.1',
                    'ST: urn:dslforum-org:device:InternetGatewayDevice:1',
                    'MAN: "ssdp:discover"',
                    'MX: 5',
                    'HOST: ' . self::WS_DISCOVERY_MULTICAST_ADDRESSV6 . ':1900',
                    'Content-Length: 0'
                ];
                $SendData = implode("\r\n", $message) . "\r\n\r\n";
                $this->SendDebug('Start Discovery(' . $Interface . ')', $IP, 0);
                $this->SendDebug('Search', $SendData, 0);
                if (@socket_sendto($socket, $SendData, strlen($SendData), 0, self::WS_DISCOVERY_MULTICAST_ADDRESSV6, self::WS_DISCOVERY_MULTICAST_PORT) === false) {
                    $this->SendDebug('Error on send discovery message', $IP, 0);
                    @socket_close($socket);
                    continue;
                }
                $response = '';
                $IPAddress = '';
                $Port = 0;
                do {
                    if (0 == @socket_recvfrom($socket, $response, 2048, 0, $IPAddress, $Port)) {
                        continue;
                    }
                    $this->SendDebug('Receive (' . $IPAddress . ')', $response, 0);
                    $Data = $this->parseHeader($response);
                    if (!array_key_exists('SERVER', $Data)) {
                        continue;
                    }
                    if (!array_key_exists('USN', $Data)) {
                        continue;
                    }
                    if (strpos($Data['SERVER'], 'AVM') === false) {
                        continue;
                    }
                    if (!in_array(parse_url($Data['LOCATION'], PHP_URL_PATH), ['/igddesc.xml', '/igd2desc.xml', '/tr64desc.xml'])) {
                        continue;
                    }
                    $USN = explode(':', $Data['USN'])[1];
                    $IPAddress = parse_url($Data['LOCATION'], PHP_URL_HOST);
                    $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $IPAddress, 20 + $Index);
                    $Host = gethostbyaddr(substr($IPAddress, 1, -1));
                    if ($Host != substr($IPAddress, 1, -1)) {
                        $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $Host, $Index);
                    }
                    $this->SendDebug('Receive (' . explode(':', $Data['USN'])[1] . ')', ['SERVER' => $Data['SERVER'], 'HOST' => $Host], 0);
                    $Index++;
                } while (time() < $discoveryTimeout);
                socket_close($socket);
            } else {
                $this->SendDebug('Error on create Socket ipv6', $IP, 0);
            }
        }
        $Index = 0;
        foreach ($Interfaces['ipv4'] as $IP => $Interface) {
            $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket) {
                socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
                socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
                socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_TTL, 4);
                socket_set_option($socket, IPPROTO_IP, IP_MULTICAST_IF, $Interface);
                socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1);
                socket_bind($socket, $IP, 1901);
                $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
                $message = [
                    'M-SEARCH * HTTP/1.1',
                    'ST: urn:dslforum-org:device:InternetGatewayDevice:1',
                    'MAN: "ssdp:discover"',
                    'MX: 5',
                    'HOST: ' . self::WS_DISCOVERY_MULTICAST_ADDRESS . ':1900',
                    'Content-Length: 0'
                ];
                $SendData = implode("\r\n", $message) . "\r\n\r\n";
                $this->SendDebug('Start Discovery(' . $Interface . ')', $IP, 0);
                $this->SendDebug('Search', $SendData, 0);
                if (@socket_sendto($socket, $SendData, strlen($SendData), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT) === false) {
                    $this->SendDebug('Error on send discovery message', $IP, 0);
                    @socket_close($socket);
                    continue;
                }
                $response = '';
                $IPAddress = '';
                $Port = 0;
                do {
                    if (0 == @socket_recvfrom($socket, $response, 2048, 0, $IPAddress, $Port)) {
                        continue;
                    }
                    $this->SendDebug('Receive (' . $IPAddress . ')', $response, 0);
                    $Data = $this->parseHeader($response);
                    if (!array_key_exists('SERVER', $Data)) {
                        continue;
                    }
                    if (!array_key_exists('USN', $Data)) {
                        continue;
                    }
                    if (strpos($Data['SERVER'], 'AVM') === false) {
                        continue;
                    }
                    if (!in_array(parse_url($Data['LOCATION'], PHP_URL_PATH), ['/igddesc.xml', '/igd2desc.xml', '/tr64desc.xml'])) {
                        continue;
                    }
                    $USN = explode(':', $Data['USN'])[1];
                    $IPAddress = parse_url($Data['LOCATION'], PHP_URL_HOST);
                    $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $IPAddress, 60 + $Index);
                    $Host = gethostbyaddr($IPAddress);
                    if ($Host != $IPAddress) {
                        $this->AddDiscoveryEntry($DevicesData, $USN, $Data['SERVER'], $Host, 40 + $Index);
                    }
                    $this->SendDebug('Receive (' . explode(':', $Data['USN'])[1] . ')', ['SERVER' => $Data['SERVER'], 'HOST' => $Host], 0);
                    $Index++;
                } while (time() < $discoveryTimeout);
                socket_close($socket);
            } else {
                $this->SendDebug('Error on create Socket ipv4', $IP, 0);
            }
        }
        $this->SendDebug('DevicesData', $DevicesData, 0);
        return $DevicesData;
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
        $InterfaceIndexes = array_column($Interfaces, 'Description', 'InterfaceIndex');
        $Networks = net_get_interfaces();
        $Addresses = [];
        foreach ($Networks as $Interface) {
            if (!$Interface['up']) {
                continue;
            }
            $InterfaceIndex = array_search($Interface['description'], $InterfaceIndexes);
            foreach ($Interface['unicast'] as $Address) {
                switch ($Address['family']) {
                    case 23:
                        $family = 'ipv6';
                        if ($Address['address'] == '::1') {
                            continue 2;
                        }
                        $Address['address'] = '[' . $Address['address'] . ']';
                        break;
                    case 2:
                        if ($Address['address'] == '127.0.0.1') {
                            continue 2;
                        }
                        $family = 'ipv4';
                        break;
                    default:
                        continue 2;
                }
                $Addresses[$family][$Address['address']] = $InterfaceIndex; //$Interface['description'];
            }
        }
        return $Addresses;
    }
    private function AddDiscoveryEntry(&$DevicesData, $USN, $Server, $Host, $Index)
    {
        if (array_key_exists($USN, $DevicesData)) {
            if (!in_array($Host, $DevicesData[$USN]['Hosts'])) {
                $DevicesData[$USN]['Hosts'][$Index] = $Host;
            }
        } else {
            $DevicesData[$USN]['Server'] = $Server;
            $DevicesData[$USN]['Hosts'][$Index] = $Host;
        }
    }
}
