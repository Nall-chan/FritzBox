<?php

declare(strict_types=1);

namespace FritzBox;

/**
 * Mesh-Übersicht der FritzBox (meshlist.lua, Pfad über X_AVM-DE_GetMeshListPath im Dienst Hosts).
 *
 * Die Liste enthält alle Knoten (FritzBox, Repeater, Powerline, Endgeräte) mit ihren Schnittstellen und
 * den Verbindungen zwischen den Schnittstellen. Build() macht daraus einen Baum ab dem Mesh-Master:
 * Knoten → Schnittstelle → verbundene Knoten. Ohne Symcon-Abhängigkeit, damit testbar.
 *
 * Datenraten (cur_data_rate_*, max_data_rate_*) liefert die Box in kbit/s (LAN 1 Gbit/s = 1000000, live geprüft).
 */
class Mesh
{
    /**
     * Baut den Baum ab dem Mesh-Master.
     *
     * @param array $List Dekodierte meshlist (Schlüssel `nodes`).
     *
     * @return array ['root' => Knoten|null, 'unlinked' => Anzahl Knoten ohne Verbindung zum Master]
     *               Knoten: name, model, role, meshed, ip, mac, ifaces[] (name, type, links[] (type, rx, tx, maxRx, maxTx, node)).
     */
    public static function Build(array $List): array
    {
        $Nodes = [];
        $InterfaceOwner = [];
        foreach ($List['nodes'] ?? [] as $Node) {
            if (!isset($Node['uid'])) {
                continue;
            }
            $Nodes[$Node['uid']] = $Node;
            foreach ($Node['node_interfaces'] ?? [] as $Interface) {
                $InterfaceOwner[$Interface['uid']] = $Interface;
            }
        }
        $Master = null;
        foreach ($Nodes as $Uid => $Node) {
            if (($Node['mesh_role'] ?? '') == 'master') {
                $Master = $Uid;
                break;
            }
        }
        if ($Master === null) {
            return ['root' => null, 'unlinked' => count($Nodes)];
        }
        // Verbindungen sind an beiden Schnittstellen eingetragen: je uid nur einmal, nur verbundene
        $Adjacent = [];
        $Seen = [];
        foreach ($Nodes as $Node) {
            foreach ($Node['node_interfaces'] ?? [] as $Interface) {
                foreach ($Interface['node_links'] ?? [] as $Link) {
                    if (isset($Seen[$Link['uid']]) || (($Link['state'] ?? '') != 'CONNECTED')) {
                        continue;
                    }
                    $Seen[$Link['uid']] = true;
                    $Adjacent[$Link['node_1_uid']][] = $Link;
                    $Adjacent[$Link['node_2_uid']][] = $Link;
                }
            }
        }
        // Breitensuche ab dem Master: jeder Knoten hängt an der ersten (kürzesten) Verbindung
        $Parent = [$Master => null];
        $Children = [];
        $Queue = [$Master];
        while (count($Queue) > 0) {
            $Uid = array_shift($Queue);
            foreach ($Adjacent[$Uid] ?? [] as $Link) {
                $First = ($Link['node_1_uid'] == $Uid);
                $Other = $First ? $Link['node_2_uid'] : $Link['node_1_uid'];
                if (array_key_exists($Other, $Parent) || !isset($Nodes[$Other])) {
                    continue;
                }
                $Parent[$Other] = $Uid;
                $Queue[] = $Other;
                $OwnInterface = $First ? $Link['node_interface_1_uid'] : $Link['node_interface_2_uid'];
                // rx/tx aus Sicht des Knotens, an dem die Verbindung hängt
                $Children[$Uid][$OwnInterface][] = [
                    'node' => $Other,
                    'type' => (string) ($Link['type'] ?? ''),
                    'rx'   => (int) ($First ? ($Link['cur_data_rate_rx'] ?? 0) : ($Link['cur_data_rate_tx'] ?? 0)),
                    'tx'   => (int) ($First ? ($Link['cur_data_rate_tx'] ?? 0) : ($Link['cur_data_rate_rx'] ?? 0)),
                    'maxRx'=> (int) ($First ? ($Link['max_data_rate_rx'] ?? 0) : ($Link['max_data_rate_tx'] ?? 0)),
                    'maxTx'=> (int) ($First ? ($Link['max_data_rate_tx'] ?? 0) : ($Link['max_data_rate_rx'] ?? 0))
                ];
            }
        }
        return [
            'root'     => self::Node($Master, $Nodes, $InterfaceOwner, $Children),
            'unlinked' => count($Nodes) - count($Parent)
        ];
    }

    /**
     * Name eines Knotens: Anzeigename, sonst Gerätename, sonst LLDP-Name, sonst Modell.
     */
    public static function NodeName(array $Node): string
    {
        foreach (['device_friendly_name', 'device_name', 'device_name_lldp', 'device_model'] as $Key) {
            if (!empty($Node[$Key])) {
                return (string) $Node[$Key];
            }
        }
        return '';
    }

    /**
     * IPv4-Adresse eines Knotens ohne Präfixlänge. Die FritzBox selbst hat mehrere (Heimnetz, Gastnetz,
     * Link-Local); bevorzugt wird die Adresse im Heimnetz, dann Gastnetz (`GUEST`), zuletzt Link-Local
     * (`LINK_LOCAL` bzw. 169.254.x.x).
     */
    public static function NodeIPv4(array $Node): string
    {
        $Best = '';
        $BestRank = PHP_INT_MAX;
        foreach ($Node['ip_addresses'] ?? [] as $Address) {
            if (($Address['version'] ?? '') != 'V4') {
                continue;
            }
            $Ip = explode('/', (string) ($Address['value'] ?? ''))[0];
            $Attributes = $Address['attributes'] ?? [];
            $Rank = (in_array('LINK_LOCAL', $Attributes) || str_starts_with($Ip, '169.254.')) ? 2 : (in_array('GUEST', $Attributes) ? 1 : 0);
            if ($Rank < $BestRank) {
                $Best = $Ip;
                $BestRank = $Rank;
            }
        }
        return $Best;
    }

    private static function Node(string $Uid, array $Nodes, array $Interfaces, array $Children): array
    {
        $Node = $Nodes[$Uid];
        $Result = [
            'name'   => self::NodeName($Node),
            'model'  => (string) ($Node['device_model'] ?? ''),
            'role'   => in_array($Node['mesh_role'] ?? '', ['master', 'slave']) ? $Node['mesh_role'] : '',
            'meshed' => (bool) ($Node['is_meshed'] ?? false),
            'ip'     => self::NodeIPv4($Node),
            'mac'    => (string) ($Node['device_mac_address'] ?? ''),
            'ifaces' => []
        ];
        foreach ($Children[$Uid] ?? [] as $InterfaceUid => $Links) {
            $Items = [];
            foreach ($Links as $Link) {
                $Child = $Link['node'];
                unset($Link['node']);
                $Link['node'] = self::Node($Child, $Nodes, $Interfaces, $Children);
                $Items[] = $Link;
            }
            // Knoten mit eigenen Geräten (Repeater, Powerline) zuerst, sonst nach Name
            usort($Items, function (array $A, array $B): int
            {
                $Sub = (count($B['node']['ifaces']) > 0) <=> (count($A['node']['ifaces']) > 0);
                return $Sub != 0 ? $Sub : strnatcasecmp($A['node']['name'], $B['node']['name']);
            });
            $Interface = $Interfaces[$InterfaceUid] ?? [];
            $Result['ifaces'][] = [
                'name'  => (string) ($Interface['name'] ?? ''),
                'type'  => (string) ($Interface['type'] ?? ($Items[0]['type'] ?? '')),
                'links' => $Items
            ];
        }
        usort($Result['ifaces'], fn (array $A, array $B): int => strnatcasecmp($A['type'] . $A['name'], $B['type'] . $B['name']));
        return $Result;
    }
}
