<?php

namespace TS\Web\JsonClient\Middleware;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\SerializerInterface;
use TS\Web\JsonClient\Exception\UnexpectedResponseException;
use TS\Web\JsonClient\Fixtures\Payload;

class ResponseDeserializerTest extends TestCase
{
    private $serializer;
    private $request;
    private $response;
    private $context;

    protected function setUp(): void
    {
        $this->serializer = $this->createMock(SerializerInterface::class);
        $this->request = $this->createMock(RequestInterface::class);
        $this->context = ['foo' => 'bar'];
    }

    public function testSuccessfulDeserialization()
    {
        $payload = new Payload('str', 123);
        $json = '{"str":"str","int":123}';
        $this->response = new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($json));

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($json, Payload::class, 'json', $this->context)
            ->willReturn($payload);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $deserializer = new ResponseDeserializer($this->serializer, $this->context, $this->request, $this->response);
        $result = $deserializer->deserializeBody(Payload::class);
        $this->assertSame($payload, $result);
    }

    public function testContentTypeMismatchThrowsException()
    {
        $json = '{"str":"str","int":123}';
        $this->response = new Response(200, ['Content-Type' => 'text/html'], Utils::streamFor($json));

        $deserializer = new ResponseDeserializer($this->serializer, $this->context, $this->request, $this->response);
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Expected response content type to be application/json, got text/html instead.');
        $deserializer->deserializeBody(Payload::class);
    }

    public function testSerializerExceptionThrowsUnexpectedResponseException()
    {
        $json = '{"str":"str","int":123}';
        $this->response = new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($json));

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->willThrowException(new \Exception('serializer error'));

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $deserializer = new ResponseDeserializer($this->serializer, $this->context, $this->request, $this->response);
        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionMessage('Failed to deserialize response body to type TS\Web\JsonClient\Fixtures\Payload: serializer error');
        $deserializer->deserializeBody(Payload::class);
    }

    public function testContextMerging()
    {
        $payload = new Payload('str', 123);
        $json = '{"str":"str","int":123}';
        $this->response = new Response(200, ['Content-Type' => 'application/json'], Utils::streamFor($json));

        $customContext = ['foo' => 'baz', 'bar' => 'baz'];
        $expectedContext = ['foo' => 'baz', 'bar' => 'baz'];

        $this->serializer->expects($this->once())
            ->method('deserialize')
            ->with($json, Payload::class, 'json', $expectedContext)
            ->willReturn($payload);

        $this->request->expects($this->once())
            ->method('getBody')
            ->willReturn(Utils::streamFor(''));

        $deserializer = new ResponseDeserializer($this->serializer, $this->context, $this->request, $this->response);
        $result = $deserializer->deserializeBody(Payload::class, $customContext);
        $this->assertSame($payload, $result);
    }
}
