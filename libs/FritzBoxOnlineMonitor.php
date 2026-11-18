<?php

declare(strict_types=1);

namespace FritzBox;

/**
 * Wertet den Online-Monitor der FritzBox aus (`X_AVM-DE_GetOnlineMonitor`, je Synchronisationsgruppe = Zugang).
 * Messpunkte in Byte/s, der neueste zuerst; `max_ds`/`max_us` in Byte/s (laut AVM).
 */
class OnlineMonitor
{
    /**
     * @param array $Results Antworten der FritzBox je Gruppe (Index 0 … n-1).
     *
     * @return array<int, array{name: string, mode: string, maxDown: int, maxUp: int, down: int[], up: int[]}>
     */
    public static function Groups(array $Results): array
    {
        $Split = fn (string $List): array => array_map('intval', array_values(array_filter(explode(',', $List), 'strlen')));
        $Groups = [];
        foreach ($Results as $Result) {
            $Groups[] = [
                'name'    => (string) ($Result['NewSyncGroupName'] ?? ''),
                'mode'    => (string) ($Result['NewSyncGroupMode'] ?? ''),
                'maxDown' => (int) ($Result['Newmax_ds'] ?? 0),
                'maxUp'   => (int) ($Result['Newmax_us'] ?? 0),
                'down'    => $Split((string) ($Result['Newds_current_bps'] ?? '')),
                'up'      => $Split((string) ($Result['Newus_current_bps'] ?? ''))
            ];
        }
        return $Groups;
    }

    /**
     * Index der aktiven Gruppe: unter den Gruppen mit Maximalwert die mit dem meisten Verkehr in den Messpunkten,
     * sonst die erste Gruppe mit Maximalwert, sonst 0.
     * Annahme (nicht von AVM dokumentiert): Ein ungenutzter Zugang meldet Maximalwert 0 oder keinen Verkehr
     * (live: `sync_lan0` ohne Internet über LAN 1 meldet überall 0).
     */
    public static function ActiveGroup(array $Groups): int
    {
        $Active = -1;
        $Traffic = -1;
        foreach ($Groups as $Index => $Group) {
            if (($Group['maxDown'] <= 0) && ($Group['maxUp'] <= 0)) {
                continue;
            }
            $Sum = array_sum($Group['down']) + array_sum($Group['up']);
            if ($Sum > $Traffic) {
                $Active = $Index;
                $Traffic = $Sum;
            }
        }
        return max(0, $Active);
    }
}
