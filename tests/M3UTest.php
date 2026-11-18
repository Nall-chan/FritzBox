<?php

declare(strict_types=1);

use FritzBox\M3U;
use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxM3U.php';

/**
 * Prüft das Lesen der DVB-C Senderlisten (libs/FritzBoxM3U.php) im Format der FritzBox
 * (`#EXTINF`, `#EXTVLCOPT`, RTSP-Adresse; Adressen und Sender erfunden).
 */
class M3UTest extends TestCase
{
    public function testFritzBoxList(): void
    {
        $Content = "#EXTM3U\r\n" .
            "#EXTINF:0,Sender Eins HD\r\n" .
            "#EXTVLCOPT:network-caching=1000\r\n" .
            "rtsp://192.0.2.1:554/?avm=1&freq=330&bw=8&msys=dvbc&mtype=256qam&sr=6900&specinv=1&pids=0,16,17,18,20,100,101\r\n" .
            "\r\n" .
            "#EXTINF:0,Radio, mit Komma\n" .
            "rtsp://192.0.2.1:554/?avm=1&freq=338&pids=0,16,200\n";
        $Result = M3U::Parse($Content);
        $this->assertCount(2, $Result);
        $this->assertSame('Sender Eins HD', $Result[0]['name']);
        $this->assertStringStartsWith('rtsp://192.0.2.1:554/?avm=1&freq=330', $Result[0]['url']);
        // Nur das erste Komma trennt Dauer und Name
        $this->assertSame('Radio, mit Komma', $Result[1]['name']);
    }

    public function testEntryWithoutName(): void
    {
        $Result = M3U::Parse("#EXTM3U\nrtsp://192.0.2.1:554/?a=1\n");
        $this->assertSame([['name' => 'rtsp://192.0.2.1:554/?a=1', 'url' => 'rtsp://192.0.2.1:554/?a=1']], $Result);
    }

    public function testEmpty(): void
    {
        $this->assertSame([], M3U::Parse(''));
        $this->assertSame([], M3U::Parse("#EXTM3U\n"));
    }
}
