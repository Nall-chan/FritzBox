<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxModule.php';

class DebugMaskTest extends TestCase
{
    public function testHeadersBasic(): void
    {
        $Headers = "POST /upnp/control/hosts HTTP/1.1\r\nHost: 192.168.178.1:49000\r\nAuthorization: Basic dXNlcjpzZWNyZXQ=\r\nContent-Length: 414\r\n\r\n";
        $Masked = \FritzBox\DebugMask::Headers($Headers);
        $this->assertStringNotContainsString('dXNlcjpzZWNyZXQ=', $Masked);
        $this->assertStringContainsString("Authorization: Basic ***\r\n", $Masked);
        $this->assertStringContainsString("Content-Length: 414\r\n", $Masked);
    }

    public function testHeadersDigest(): void
    {
        $Headers = "POST /upnp/control/x_upnp HTTP/1.1\r\nAuthorization: Digest username=\"user\", realm=\"HTTPS Access\", nonce=\"946C02CBB2BA2518\", response=\"366f24e71b26baaf5c244a99d1a9ed60\", algorithm=\"MD5\"\r\n\r\n";
        $Masked = \FritzBox\DebugMask::Headers($Headers);
        $this->assertStringNotContainsString('366f24e71b26baaf5c244a99d1a9ed60', $Masked);
        $this->assertStringContainsString("Authorization: Digest ***\r\n", $Masked);
    }

    public function testHeadersNull(): void
    {
        $this->assertNull(\FritzBox\DebugMask::Headers(null));
        $this->assertFalse(\FritzBox\DebugMask::Xml(false));
    }

    public function testXmlResponse(): void
    {
        $Xml = '<u:GetSecurityKeysResponse xmlns:u="urn:dslforum-org:service:WLANConfiguration:1"><NewWEPKey0></NewWEPKey0><NewPreSharedKey>5735D9E5</NewPreSharedKey><NewKeyPassphrase>geheim%1</NewKeyPassphrase></u:GetSecurityKeysResponse>';
        $Masked = \FritzBox\DebugMask::Xml($Xml);
        $this->assertStringContainsString('<NewWEPKey0></NewWEPKey0>', $Masked);
        $this->assertStringContainsString('<NewPreSharedKey>***</NewPreSharedKey>', $Masked);
        $this->assertStringContainsString('<NewKeyPassphrase>***</NewKeyPassphrase>', $Masked);
    }

    public function testXmlRequestWithAttributes(): void
    {
        $Xml = '<ns1:SetSecurityKeys><NewKeyPassphrase xsi:type="xsd:string">geheim</NewKeyPassphrase><NewChannel xsi:type="xsd:int">6</NewChannel></ns1:SetSecurityKeys>';
        $Masked = \FritzBox\DebugMask::Xml($Xml);
        $this->assertStringContainsString('<NewKeyPassphrase xsi:type="xsd:string">***</NewKeyPassphrase>', $Masked);
        $this->assertStringContainsString('<NewChannel xsi:type="xsd:int">6</NewChannel>', $Masked);
    }

    public function testValuesArray(): void
    {
        $Data = [
            'NewPassword'      => 'geheim',
            'NewUsername'      => 'user',
            'NewWEPKey1'       => '',
            'NewSSID'          => 'MyWLAN',
            'Nested'           => ['NewPreSharedKey' => 'abc']
        ];
        $Masked = \FritzBox\DebugMask::Values($Data);
        $this->assertSame('***', $Masked['NewPassword']);
        $this->assertSame('user', $Masked['NewUsername']);
        $this->assertSame('', $Masked['NewWEPKey1']);
        $this->assertSame('MyWLAN', $Masked['NewSSID']);
        $this->assertSame('***', $Masked['Nested']['NewPreSharedKey']);
    }

    public function testValuesObjectIsNotModified(): void
    {
        $Data = new stdClass();
        $Data->NewKeyPassphrase = 'geheim';
        $Masked = \FritzBox\DebugMask::Values($Data);
        $this->assertSame('***', $Masked->NewKeyPassphrase);
        $this->assertSame('geheim', $Data->NewKeyPassphrase);
    }

    public function testUrlSessionId(): void
    {
        $this->assertSame(
            'https://192.168.178.1:49443/calllist.lua?sid=***&max=999',
            \FritzBox\DebugMask::Url('https://192.168.178.1:49443/calllist.lua?sid=0123456789abcdef&max=999')
        );
        $this->assertSame(
            'http://fritz.box:49000/phonebook.lua?pbid=0&sid=***',
            \FritzBox\DebugMask::Url('http://fritz.box:49000/phonebook.lua?pbid=0&sid=0123456789abcdef')
        );
        $this->assertSame('/upnp/control/hosts', \FritzBox\DebugMask::Url('/upnp/control/hosts'));
    }

    public function testXmlSessionId(): void
    {
        $Xml = '<NewCallListURL>https://192.168.178.1:49443/calllist.lua?pbid=0&amp;sid=0123456789abcdef</NewCallListURL>';
        $this->assertSame(
            '<NewCallListURL>https://192.168.178.1:49443/calllist.lua?pbid=0&amp;sid=***</NewCallListURL>',
            \FritzBox\DebugMask::Xml($Xml)
        );
    }

    public function testValuesSessionId(): void
    {
        $Masked = \FritzBox\DebugMask::Values(['NewCallListURL' => 'https://fritz.box/calllist.lua?sid=0123456789abcdef']);
        $this->assertSame('https://fritz.box/calllist.lua?sid=***', $Masked['NewCallListURL']);
        $this->assertSame('http://fritz.box/x?sid=***', \FritzBox\DebugMask::Values('http://fritz.box/x?sid=abc'));
    }

    public function testValuesScalar(): void
    {
        $this->assertSame('text', \FritzBox\DebugMask::Values('text'));
        $this->assertSame(5, \FritzBox\DebugMask::Values(5));
    }
}
