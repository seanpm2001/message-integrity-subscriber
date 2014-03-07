<?php

namespace GuzzleHttp\Tests\MessageIntegrity;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\MessageIntegrity\ResponseSubscriber;
use GuzzleHttp\Subscriber\Mock;

class ResponseSubscriberTest extends \PHPUnit_Framework_TestCase
{
    public function configProvider()
    {
        return [
            [[]],
            [['expected' => 'foo']],
            [['expected' => function() {}]],
            [['expected' => function() {}, 'hash' => 'foo']]
        ];
    }

    /**
     * @dataProvider configProvider
     * @expectedException \InvalidArgumentException
     */
    public function testValidatesConfig($config)
    {
        ResponseSubscriber::validateOptions($config);
    }

    public function testAddsFullResponse()
    {
        $sub = ResponseSubscriber::createForContentMd5();
        $md5Test = base64_encode(md5('foo', true));
        $client = new Client();
        $client->getEmitter()->attach($sub);
        $client->getEmitter()->attach(new Mock([
            new Response(200, ['Content-MD5' => $md5Test], Stream::factory('foo'))
        ]));
        $request = $client->createRequest('GET', 'http://httpbin.org');
        $client->send($request);
        $ins = array_map(function ($rec) {
            return get_class($rec[0]);
        }, $request->getEmitter()->listeners('complete'));
        $this->assertContains('GuzzleHttp\\Subscriber\\MessageIntegrity\\OnCompleteIntegritySubscriber', $ins);
    }

    public function testAddsStreaming()
    {
        $sub = ResponseSubscriber::createForContentMd5();
        $md5Test = base64_encode(md5('foo', true));
        $client = new Client();
        $client->getEmitter()->attach($sub);
        $client->getEmitter()->attach(new Mock([
            new Response(200, ['Content-MD5' => $md5Test], Stream::factory('foo'))
        ]));
        $request = $client->createRequest('GET', 'http://httpbin.org', ['stream' => true]);
        $response = $client->send($request);
        $this->assertInstanceOf('GuzzleHttp\Subscriber\MessageIntegrity\ReadIntegrityStream', $response->getBody());
        $response->getBody()->getContents();
    }
}
