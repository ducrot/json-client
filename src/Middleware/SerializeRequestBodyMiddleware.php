<?php
/**
 * Created by PhpStorm.
 * User: ts
 * Date: 11.05.18
 * Time: 15:11
 */

namespace TS\Web\JsonClient\Middleware;


use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\Serializer\SerializerInterface;


class SerializeRequestBodyMiddleware
{

    private $serializer;
    private $nextHandler;


    public function __construct(callable $nextHandler, SerializerInterface $serializer)
    {
        $this->serializer = $serializer;
        $this->nextHandler = $nextHandler;
    }

    public function __invoke(RequestInterface $request, array $options)
    {
        $handler = $this->nextHandler;

        if (!isset($options['data'])) {
            return $handler($request, $options);
        }

        $context = $options['data_context'] ?? [];

        $json = $this->serializer
            ->serialize($options['data'], 'json', $context);

        $body = Utils::streamFor($json);

        $request = $request
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Content-Length', strlen($json))
            ->withBody($body);

        unset($options['data']);

        return $handler($request, $options);
    }


}
