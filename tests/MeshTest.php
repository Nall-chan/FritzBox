<?php

declare(strict_types=1);

use FritzBox\Mesh;
use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxMesh.php';

/**
 * Prüft den Aufbau der Mesh-Übersicht (libs/FritzBoxMesh.php) mit erfundenen Daten im Format der meshlist.lua:
 * Master mit LAN, WLAN und Powerline, ein Mesh-Repeater per WLAN mit eigenem Gerät, ein Knoten ohne Verbindung.
 */
class MeshTest extends TestCase
{
    public function testTreeFromMaster(): void
    {
        $Result = Mesh::Build($this->meshList());
        $Root = $Result['root'];
        $this->assertSame('FRITZ!Box', $Root['name']);
        $this->assertSame('master', $Root['role']);
        $this->assertSame(1, $Result['unlinked']);
        $this->assertSame(['LAN:1', 'PLC', 'AP:5G:0'], array_column($Root['ifaces'], 'name'));
        // Verbindungen nur einmal, getrennte nicht
        $this->assertCount(2, $Root['ifaces'][0]['links']);
    }

    public function testRepeaterHasOwnDevices(): void
    {
        $Root = Mesh::Build($this->meshList())['root'];
        $Wlan = $Root['ifaces'][2];
        $this->assertSame('WLAN', $Wlan['type']);
        // Repeater mit eigenen Geräten steht vor einfachen Geräten
        $Repeater = $Wlan['links'][0]['node'];
        $this->assertSame('Repeater', $Repeater['name']);
        $this->assertSame('slave', $Repeater['role']);
        $this->assertTrue($Repeater['meshed']);
        $this->assertSame('Phone', $Repeater['ifaces'][0]['links'][0]['node']['name']);
        $this->assertSame('Laptop', $Wlan['links'][1]['node']['name']);
    }

    public function testRatesFromViewOfParent(): void
    {
        $Root = Mesh::Build($this->meshList())['root'];
        // Verbindung ist am Gerät als node_1 eingetragen: rx/tx aus Sicht der FritzBox getauscht
        $Laptop = $Root['ifaces'][2]['links'][1];
        $this->assertSame(400000, $Laptop['rx']);
        $this->assertSame(866000, $Laptop['tx']);
        $Nas = $Root['ifaces'][0]['links'][0];
        $this->assertSame('NAS', $Nas['node']['name']);
        $this->assertSame(1000000, $Nas['rx']);
        $this->assertSame('192.168.178.20', $Nas['node']['ip']);
    }

    public function testNameFallback(): void
    {
        $this->assertSame('Model X', Mesh::NodeName(['device_name' => '', 'device_model' => 'Model X']));
        $this->assertSame('', Mesh::NodeIPv4(['ip_addresses' => [['version' => 'V6', 'value' => 'fe80::1/64']]]));
        // Link-Local nur als Rückfall
        $this->assertSame('192.168.178.1', Mesh::NodeIPv4(['ip_addresses' => [['version' => 'V4', 'value' => '169.254.1.1/16'], ['version' => 'V4', 'value' => '192.168.178.1/24']]]));
        $this->assertSame('169.254.1.1', Mesh::NodeIPv4(['ip_addresses' => [['version' => 'V4', 'value' => '169.254.1.1/16']]]));
        // Gastnetz nach dem Heimnetz (Reihenfolge wie bei der FritzBox)
        $this->assertSame('192.168.178.1', Mesh::NodeIPv4(['ip_addresses' => [
            ['version' => 'V4', 'value' => '169.254.1.1/16', 'attributes' => ['LINK_LOCAL']],
            ['version' => 'V4', 'value' => '192.168.179.1/24', 'attributes' => ['GUEST']],
            ['version' => 'V4', 'value' => '192.168.178.1/24', 'attributes' => ['MANAGEMENT']]
        ]]));
    }

    public function testWithoutMaster(): void
    {
        $this->assertSame(['root' => null, 'unlinked' => 1], Mesh::Build(['nodes' => [['uid' => 'n1', 'mesh_role' => 'unknown']]]));
    }

    private function meshList(): array
    {
        $Node = fn (string $Uid, string $Name, string $Role, bool $Meshed, array $Interfaces, string $Ip = ''): array => [
            'uid'                => $Uid,
            'device_name'        => $Name,
            'device_model'       => $Role == 'master' ? 'FRITZ!Box 7590' : '',
            'device_mac_address' => '00:11:22:33:44:' . substr(md5($Uid), 0, 2),
            'mesh_role'          => $Role,
            'is_meshed'          => $Meshed,
            'ip_addresses'       => $Ip == '' ? [] : [['version' => 'V4', 'value' => $Ip . '/24']],
            'node_interfaces'    => $Interfaces
        ];
        $Link = fn (string $Uid, string $Type, string $N1, string $I1, string $N2, string $I2, int $Rx, int $Tx, string $State = 'CONNECTED'): array => [
            'uid'                  => $Uid, 'type' => $Type, 'state' => $State, 'node_1_uid' => $N1, 'node_2_uid' => $N2,
            'node_interface_1_uid' => $I1, 'node_interface_2_uid' => $I2,
            'cur_data_rate_rx'     => $Rx, 'cur_data_rate_tx' => $Tx, 'max_data_rate_rx' => $Rx, 'max_data_rate_tx' => $Tx
        ];
        $Lan1 = $Link('l1', 'LAN', 'box', 'box-lan1', 'nas', 'nas-eth', 1000000, 1000000);
        $Lan2 = $Link('l2', 'LAN', 'box', 'box-lan1', 'pc', 'pc-eth', 100000, 100000);
        $Down = $Link('l3', 'LAN', 'box', 'box-lan1', 'old', 'old-eth', 0, 0, 'DISCONNECTED');
        $Plc = $Link('l4', 'PLC', 'box', 'box-plc', 'tv', 'tv-plc', 300000, 200000);
        $Rep = $Link('l5', 'WLAN', 'rep', 'rep-sta', 'box', 'box-ap5', 1200000, 1200000);
        $Lap = $Link('l6', 'WLAN', 'laptop', 'lap-wlan', 'box', 'box-ap5', 866000, 400000);
        $Pho = $Link('l7', 'WLAN', 'rep', 'rep-ap5', 'phone', 'pho-wlan', 433000, 433000);
        return ['schema_version' => '1.0', 'nodes' => [
            $Node('box', 'FRITZ!Box', 'master', true, [
                ['uid' => 'box-lan1', 'name' => 'LAN:1', 'type' => 'LAN', 'node_links' => [$Lan1, $Lan2, $Down]],
                ['uid' => 'box-plc', 'name' => 'PLC', 'type' => 'PLC', 'node_links' => [$Plc]],
                ['uid' => 'box-ap5', 'name' => 'AP:5G:0', 'type' => 'WLAN', 'node_links' => [$Rep, $Lap]]
            ]),
            $Node('nas', 'NAS', 'unknown', false, [['uid' => 'nas-eth', 'name' => '', 'type' => 'LAN', 'node_links' => [$Lan1]]], '192.168.178.20'),
            $Node('pc', 'PC', 'unknown', false, [['uid' => 'pc-eth', 'name' => '', 'type' => 'LAN', 'node_links' => [$Lan2]]]),
            $Node('old', 'Old', 'unknown', false, [['uid' => 'old-eth', 'name' => '', 'type' => 'LAN', 'node_links' => [$Down]]]),
            $Node('tv', 'TV', 'unknown', false, [['uid' => 'tv-plc', 'name' => '', 'type' => 'PLC', 'node_links' => [$Plc]]]),
            $Node('rep', 'Repeater', 'slave', true, [
                ['uid' => 'rep-sta', 'name' => 'STA:5G:0', 'type' => 'WLAN', 'node_links' => [$Rep]],
                ['uid' => 'rep-ap5', 'name' => 'AP:5G:0', 'type' => 'WLAN', 'node_links' => [$Pho]]
            ]),
            $Node('laptop', 'Laptop', 'unknown', false, [['uid' => 'lap-wlan', 'name' => '', 'type' => 'WLAN', 'node_links' => [$Lap]]]),
            $Node('phone', 'Phone', 'unknown', false, [['uid' => 'pho-wlan', 'name' => '', 'type' => 'WLAN', 'node_links' => [$Pho]]])
        ]];
    }
}
