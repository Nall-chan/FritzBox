<?php

declare(strict_types=1);

use FritzBox\CallLog;
use FritzBox\EventParser;
use FritzBox\Phonebook;
use PHPUnit\Framework\TestCase;

include_once __DIR__ . '/../libs/FritzBoxParser.php';

/**
 * Tests der Parser in libs/FritzBoxParser.php.
 * Referenzdaten: Beispiele aus „TR-064 Support – X_AVM-DE_OnTel“ (AVM, Version 41, Abschnitte
 * Phonebook Content und Call List Content) und Ereignisformat aus der UPnP Device Architecture 1.1 (4.3.2).
 * Die Präfix-Fälle der Anrufliste (`Numbertype` = `sip`, `SIP: ` vor der eigenen Nummer) entsprechen
 * einer echten Anrufliste der FRITZ!Box 6690 (Rufnummern hier erfunden).
 */
class ParserTest extends TestCase
{
    private const Phonebook = <<<'XML'
        <?xml version="1.0"?>
        <phonebooks>
           <phonebook owner="0" name="Telefonbuch">
               <contact>
                  <category/>
                  <person>
                      <realName>Alle (Rundruf)</realName>
                  </person>
                  <telephony>
                      <services>
                      </services>
                      <number type="intern" quickdial="" vanity="" prio="0">**9</number>
                  </telephony>
                  <uniqueid>1232</uniqueid>
               </contact>
               <contact>
                  <category/>
                  <person>
                      <realName>SRE</realName>
                        <imageURL>/download.lua?path=/var/media/ftp/USB/FRITZ/fonpix/123.jpg</imageURL>
                  </person>
                  <telephony>
                      <services>
                      </services>
                      <number type="work" quickdial="1" vanity="" prio="0" >030399760</number>
                  </telephony>
                  <uniqueid>1234</uniqueid>
               </contact>
               <contact>
                  <category>0</category>
                  <person>
                      <realName>Türsprechanlage Test</realName>
                  </person>
                  <uniqueid>1236</uniqueid>
                  <telephony>
                      <services>
                      </services>
                      <number type="intern" vanity="" prio="1" >**620</number>
                      <doorphone>
                          <videoURL>http://192.168.178.30/live%20test.png</videoURL>
                          <openkey>*20#</openkey>
                      </doorphone>
                  </telephony>
               </contact>
           </phonebook>
        </phonebooks>
        XML;

    private const CallList = <<<'XML'
        <?xml version="1.0"?>
        <root>
           <timestamp>123456</timestamp>
           <Call>
               <Id>123</Id>
               <Type>3</Type>
               <Called>0123456789</Called>
               <Caller>SIP: 98765</Caller>
               <CallerNumber>98765</CallerNumber>
               <Name>Max Mustermann</Name>
               <Numbertype/>
               <Device>Mobilteil 1</Device>
               <Port>10</Port>
               <Date>23.09.11 08:13</Date>
               <Duration>0:01</Duration>
               <Count/>
               <Path/>
           </Call>
           <Call>
               <Id>122</Id>
               <Type>1</Type>
               <Caller>012456789</Caller>
               <Called>SIP: 56789</Called>
               <CalledNumber>98765</CalledNumber>
               <Name>Max Mustermann</Name>
               <Numbertype/>
               <Device>Anrufbeantworter 1</Device>
               <Port>40</Port>
               <Date>22.09.11 14:19</Date>
               <Duration>0:01</Duration>
               <Count/>
               <Path>/download.lua?path=/var/media/ftp/USB/FRITZ/voicebox/rec/rec.0.000</Path>
           </Call>
        </root>
        XML;

    private const CallListSip = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <root>
           <Call><Id>3</Id><Type>3</Type><Caller>SIP: 1234567</Caller><Called>030111222</Called><Name></Name><Numbertype>sip</Numbertype><Device>Küche</Device><Port>12</Port><Date>15.11.26 10:00</Date><Duration>0:05</Duration><Path/></Call>
           <Call><Id>2</Id><Type>1</Type><Caller>030111222</Caller><Called>SIP: 1234567</Called><Name></Name><Numbertype>sip</Numbertype><Device>Küche</Device><Port>12</Port><Date>14.11.26 09:00</Date><Duration>0:02</Duration><Path/></Call>
           <Call><Id>1</Id><Type>2</Type><Caller></Caller><Called>SIP: 1234567</Called><Name></Name><Numbertype>sip</Numbertype><Device></Device><Port>-1</Port><Date>13.11.26 08:00</Date><Duration>0:00</Duration><Path/></Call>
        </root>
        XML;

    public function testEventUPnPExample(): void
    {
        $Xml = '<?xml version="1.0"?>
            <e:propertyset xmlns:e="urn:schemas-upnp-org:event-1-0">
                <e:property><variableName>new value</variableName></e:property>
                <e:property><X_AVM-DE_TotalAssociations>3</X_AVM-DE_TotalAssociations></e:property>
            </e:propertyset>';
        $this->assertSame(['variableName' => 'new value', 'X_AVM_DE_TotalAssociations' => '3'], EventParser::Parse($Xml));
    }

    public function testEventOtherPrefixUsesNamespaceUri(): void
    {
        $Xml = '<ev:propertyset xmlns:ev="urn:schemas-upnp-org:event-1-0"><ev:property><Status>Up</Status></ev:property></ev:propertyset>';
        $this->assertSame(['Status' => 'Up'], EventParser::Parse($Xml));
    }

    public function testEventEmptyValue(): void
    {
        $Xml = '<e:propertyset xmlns:e="urn:schemas-upnp-org:event-1-0"><e:property><ExternalIPAddress/></e:property></e:propertyset>';
        $this->assertSame(['ExternalIPAddress' => ''], EventParser::Parse($Xml));
    }

    public function testEventInvalidXmlThrows(): void
    {
        $this->expectException(\Exception::class);
        EventParser::Parse('no xml');
    }

    public function testSearchNumbersNational(): void
    {
        $this->assertSame(['030399760', '+4930399760', '399760'], Phonebook::SearchNumbers('030399760', '030', '+49'));
    }

    public function testSearchNumbersLocal(): void
    {
        $this->assertSame(['399760', '030399760', '+4930399760'], Phonebook::SearchNumbers('399760', '030', '+49'));
    }

    public function testSearchNumbersInternationalAndEmpty(): void
    {
        $this->assertSame(['+4930399760'], Phonebook::SearchNumbers('+4930399760', '030', '+49'));
        $this->assertSame([''], Phonebook::SearchNumbers('', '030', '+49'));
        $this->assertSame(['399760'], Phonebook::SearchNumbers('399760'));
    }

    public function testFindNameDirect(): void
    {
        $this->assertSame('SRE', Phonebook::FindName(self::Phonebook, ['030399760'], 50));
        $this->assertSame('Alle (Rundruf)', Phonebook::FindName(self::Phonebook, ['**9'], 50));
    }

    public function testFindNameWithAreaCode(): void
    {
        $this->assertSame('SRE', Phonebook::FindName(self::Phonebook, Phonebook::SearchNumbers('399760', '030', '+49'), 50));
    }

    public function testFindNameNotFound(): void
    {
        $this->assertFalse(Phonebook::FindName(self::Phonebook, ['0987654321', "0'1"], 50));
    }

    public function testFindNameTruncatesCharactersNotBytes(): void
    {
        $this->assertSame('Tür', Phonebook::FindName(self::Phonebook, ['**620'], 3));
        $this->assertSame('Türsprechanlage Test', Phonebook::TruncateName('Türsprechanlage Test', 20));
    }

    public function testFindNameInvalidXmlThrows(): void
    {
        $this->expectException(\Exception::class);
        Phonebook::FindName('<phonebooks>', ['**9'], 50);
    }

    public function testCallListAVMExample(): void
    {
        $Calls = CallLog::Parse(self::CallList);
        $this->assertCount(2, $Calls);
        // Ohne Numbertype bleibt das Präfix stehen.
        $this->assertSame([
            'Type'     => CallLog::TypeOutgoing,
            'Port'     => 10,
            'Name'     => 'Max Mustermann',
            'Caller'   => 'SIP: 98765',
            'Called'   => '0123456789',
            'Number'   => '0123456789',
            'Device'   => 'Mobilteil 1',
            'Date'     => '23.09.11 08:13',
            'Duration' => '0:01',
            'Path'     => ''
        ], $Calls[0]);
        $this->assertSame(CallLog::TypeIncoming, $Calls[1]['Type']);
        $this->assertSame('012456789', $Calls[1]['Number']);
        $this->assertSame('SIP: 56789', $Calls[1]['Called']);
        $this->assertSame(40, $Calls[1]['Port']);
        $this->assertSame('/download.lua?path=/var/media/ftp/USB/FRITZ/voicebox/rec/rec.0.000', $Calls[1]['Path']);
    }

    public function testCallListRemovesNumberTypePrefix(): void
    {
        $Calls = CallLog::Parse(self::CallListSip);
        $this->assertSame('1234567', $Calls[0]['Caller']);
        $this->assertSame('030111222', $Calls[0]['Number']);
        $this->assertSame('1234567', $Calls[1]['Called']);
        $this->assertSame('030111222', $Calls[1]['Number']);
        $this->assertSame(CallLog::TypeMissed, $Calls[2]['Type']);
        $this->assertSame('', $Calls[2]['Number']);
        $this->assertSame(-1, $Calls[2]['Port']);
        $this->assertSame('Küche', $Calls[0]['Device']);
    }

    public function testCallListEmpty(): void
    {
        $this->assertSame([], CallLog::Parse('<root><timestamp>1</timestamp></root>'));
    }
}
