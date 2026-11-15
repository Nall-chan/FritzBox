<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxConstants.php';

/**
 * Prüft, dass kein Profilname in der Library mit unterschiedlichen Variablentypen registriert wird.
 * Sonst scheitert die zweite Instanz mit „Variable profile type does not match“ (z.B. FB.dBm bis 0.93).
 */
class ProfileTest extends TestCase
{
    public function testProfileNamesHaveOneType(): void
    {
        $Types = [];
        foreach ($this->findRegistrations() as [$File, $Name, $Type]) {
            $Types[$Name][$Type][] = $File;
        }
        $this->assertNotEmpty($Types, 'No profile registrations found.');
        foreach ($Types as $Name => $ByType) {
            $this->assertCount(1, $ByType, 'Profile ' . $Name . ' is registered with different types: ' . json_encode($ByType));
        }
    }

    public function testWANFiberAndMobileUseDifferentProfiles(): void
    {
        $this->assertNotSame(\FritzBox\WANFiber\Profile::Dbm, \FritzBox\MobileConnection\Profile::Dbm);
        $this->assertSame(\FritzBox\MobileConnection\Profile::Dbm, \FritzBox\WANFiber\Profile::LegacyDbm);
    }

    /**
     * Liefert alle Aufrufe von RegisterProfile<Typ>(Ex) mit Datei, aufgelöstem Profilnamen und Typ.
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    private function findRegistrations(): array
    {
        $Result = [];
        $Files = array_merge(glob(dirname(__DIR__) . '/*/module.php'), glob(dirname(__DIR__) . '/libs/*.php'));
        foreach ($Files as $File) {
            $Content = file_get_contents($File);
            preg_match_all('/RegisterProfile(Boolean|Integer|Float|String)(?:Ex)?\(\s*(\'[^\']+\'|\\\\?[A-Za-z_\\\\]+::[A-Za-z_]+)/', $Content, $Matches, PREG_SET_ORDER);
            foreach ($Matches as $Match) {
                $Name = $Match[2][0] === '\'' ? trim($Match[2], '\'') : constant(ltrim($Match[2], '\\'));
                $Result[] = [basename(dirname($File)), $Name, $Match[1]];
            }
        }
        return $Result;
    }
}
