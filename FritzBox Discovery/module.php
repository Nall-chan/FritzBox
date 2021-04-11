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
        $Devices = $this->Discover();
        $Form = json_decode(file_get_contents(__DIR__ . '/form.json'), true);
        $InstanceIDListConfigurators = IPS_GetInstanceListByModuleID('{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}');
        $DevicesAddress = [];
        $DeviceValues = [];
        foreach ($InstanceIDListConfigurators as $InstanceIDConfigurator) {
            $Splitter = IPS_GetInstance($InstanceIDConfigurator)['ConnectionID'];
            if ($Splitter > 0) {
                $DevicesAddress[$InstanceIDConfigurator] = IPS_GetProperty($Splitter, 'Host');
            }
        }
        /*array(1) {
            ["http://192.168.201.1:49000"]=>
            string(76) "FRITZ!Box 6591 Cable (kdg) UPnP/1.0 AVM FRITZ!Box 6591 Cable (kdg) 161.07.03"
          }*/

        foreach ($Devices as $Url => $Device) {
            $AddDevice = [
                'instanceID'      => 0,
                'url'             => $Url,
                'name'            => $Device
            ];

            $Config = [
                'Host'     => $Url
            ];
            $InstanceIDConfigurator = array_search($Url, $DevicesAddress);

            if ($InstanceIDConfigurator !== false) {
                $AddDevice['name'] = IPS_GetLocation($InstanceIDConfigurator);
                $AddDevice['instanceID'] = $InstanceIDConfigurator;
                unset($DevicesAddress[$InstanceIDConfigurator]);
            }
            $AddDevice['create'] = [

                [
                    'moduleID'      => '{32CF40DC-51DA-6C63-8BD7-55E82F64B9E7}',
                    'configuration' => new stdClass()
                ],
                [
                    'moduleID'      => '{6FF9A05D-4E49-4371-23F1-7F58283FB1D9}',
                    'configuration' => $Config
                ]

            ];
            $DeviceValues[] = $AddDevice;
        }
        foreach ($DevicesAddress as $id => $Url) {
            $AddDevice = [
                'instanceID'      => $id,
                'url'             => $Url,
                'name'            => IPS_GetLocation($id)
            ];
            $DeviceValues[] = $AddDevice;
        }
        $Form['actions'][0]['values'] = $DeviceValues;
        $this->SendDebug('FORM', json_encode($Form), 0);
        $this->SendDebug('FORM', json_last_error_msg(), 0);
        return json_encode($Form);
    }

    protected function Discover(): array
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!$socket) {
            return [];
        }
        socket_set_option($socket, SOL_SOCKET, SO_RCVTIMEO, ['sec' => 2, 'usec' => 100000]);
        socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($socket, '0.0.0.0', 0);
        $discoveryTimeout = time() + self::WS_DISCOVERY_TIMEOUT;
        $message = [
            'M-SEARCH * HTTP/1.1',
            'ST: urn:dslforum-org:device:InternetGatewayDevice:1',
            'MAN: "ssdp:discover"',
            'MX: 5',
            'HOST: 239.255.255.250:1900',
            'Content-Length: 0'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
        $this->SendDebug('Search', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT) === false) {
            return [];
        }
        $message = [
            'M-SEARCH * HTTP/1.1',
            'ST: upnp:rootdevice',
            'MAN: "ssdp:discover"',
            'MX: 5',
            'HOST: 239.255.255.250:1900',
            'Content-Length: 0'
        ];
        $SendData = implode("\r\n", $message) . "\r\n\r\n";
        $this->SendDebug('Search', $SendData, 0);
        if (@socket_sendto($socket, $SendData, strlen($SendData), 0, self::WS_DISCOVERY_MULTICAST_ADDRESS, self::WS_DISCOVERY_MULTICAST_PORT) === false) {
            return [];
        }
        $response = '';
        $IPAddress = '';
        $Port = 0;
        $DevicesData = [];
        do {
            if (0 == @socket_recvfrom($socket, $response, 2048, 0, $IPAddress, $Port)) {
                continue;
            }
            $this->SendDebug('Receive', $response, 0);
            $Data = $this->parseHeader($response);
            if (!array_key_exists('SERVER', $Data)) {
                continue;
            }
            if (strpos($Data['SERVER'], 'AVM') === false) {
                continue;
            }

            if (!in_array(parse_url($Data['LOCATION'], PHP_URL_PATH), ['/igddesc.xml', '/igd2desc.xml', '/tr64desc.xml'])) {
                continue;
            }

            $Url = parse_url($Data['LOCATION'], PHP_URL_SCHEME) . '://' .
                    parse_url($Data['LOCATION'], PHP_URL_HOST) . ':' .
                    parse_url($Data['LOCATION'], PHP_URL_PORT);
            if (!array_key_exists($Data['SERVER'], $DevicesData)) {
                $DevicesData[$Url] = $Data['SERVER'];
            }
        } while (time() < $discoveryTimeout);
        socket_close($socket);
        /*
                $Devices = [];
                foreach ($DevicesData as $DeviceData) {
                    $XMLData = @Sys_GetURLContent($DeviceData);
                    if ($XMLData === false) {
                        $this->SendDebug('Location Error', $DeviceData, 0);
                        continue;
                    }
                    $this->SendDebug($DeviceData, $XMLData, 0);
                    try {
                        $Xml = new SimpleXMLElement($XMLData);
                    } catch (Exception $ex) {
                        $this->SendDebug('XML Error', $Xml->error_get_last(), 0);
                        continue;
                    }
                    $URL = parse_url($DeviceData);
                    $Devices[] = [
                        'location'   => $DeviceData,
                        'modelName'  => (string) $Xml->device->modelName,
                        'devicename' => (string) $Xml->device->friendlyName,
                        'deviceType' => implode(':', array_chunk(explode(':', (string) $Xml->device->deviceType), 3)[1])
                    ];
                }
                foreach ($Devices as &$Device) {
                    $SCPD_XML = @Sys_GetURLContent($Device['location']);
                    if ($SCPD_XML === false) {
                        $this->SendDebug('SCPD Error', $Device['location'], 0);
                        continue;
                    }
                    $this->SendDebug($Device['location'], $SCPD_XML, 0);
                    try {
                        $SCPD_Data = new SimpleXMLElement($SCPD_XML);
                    } catch (Exception $ex) {
                        $this->SendDebug('XML Error', $Xml->error_get_last(), 0);
                        continue;
                    }
                    $Services = [];
                    // service mit xpath suchen !
                    $SCPD_Data->registerXPathNamespace('fritzbox', $SCPD_Data->getNameSpaces(false)['']);
                    $Services = $SCPD_Data->xpath('//fritzbox:service');
                    foreach ($Services as $Service) {
                        $Device['service'][] = (array) $Service;
                    }
                }
                return $Devices;
         */
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
}
