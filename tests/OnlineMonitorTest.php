<?php

declare(strict_types=1);

use FritzBox\OnlineMonitor;
use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxOnlineMonitor.php';

/**
 * Prüft die Auswertung des Online-Monitors (libs/FritzBoxOnlineMonitor.php) im Format der FritzBox:
 * Kabel aktiv, Internet über LAN 1 ungenutzt (wie live an der 6690, Werte gekürzt) sowie Fallback auf LTE (erfunden).
 */
class OnlineMonitorTest extends TestCase
{
    public function testCableActiveLanUnused(): void
    {
        $Groups = OnlineMonitor::Groups([
            $this->group('sync_cable', 'CABLE', 143750000, 7088750, '149029,3858,3858', '23340,50914,3727'),
            $this->group('sync_lan0', 'NETIF', 0, 0, '0,0,0', '0,0,0')
        ]);
        $this->assertCount(2, $Groups);
        $this->assertSame([149029, 3858, 3858], $Groups[0]['down']);
        $this->assertSame(143750000, $Groups[0]['maxDown']);
        $this->assertSame(0, OnlineMonitor::ActiveGroup($Groups));
    }

    public function testFallbackOnSecondGroup(): void
    {
        // Kabel gestört (Maximalwert noch gemeldet, kein Verkehr), LTE übernimmt
        $Groups = OnlineMonitor::Groups([
            $this->group('sync_cable', 'CABLE', 143750000, 7088750, '0,0,0', '0,0,0'),
            $this->group('sync_lte', 'LTE', 6250000, 1250000, '50000,40000,30000', '8000,7000,6000')
        ]);
        $this->assertSame(1, OnlineMonitor::ActiveGroup($Groups));
    }

    public function testNoMaximum(): void
    {
        $Groups = OnlineMonitor::Groups([$this->group('sync_lan0', 'NETIF', 0, 0, '', '')]);
        $this->assertSame([], $Groups[0]['down']);
        $this->assertSame(0, OnlineMonitor::ActiveGroup($Groups));
        $this->assertSame(0, OnlineMonitor::ActiveGroup([]));
    }

    private function group(string $Name, string $Mode, int $MaxDown, int $MaxUp, string $Down, string $Up): array
    {
        return [
            'NewSyncGroupName'  => $Name,
            'NewSyncGroupMode'  => $Mode,
            'Newmax_ds'         => (string) $MaxDown,
            'Newmax_us'         => (string) $MaxUp,
            'Newds_current_bps' => $Down,
            'Newus_current_bps' => $Up
        ];
    }
}
