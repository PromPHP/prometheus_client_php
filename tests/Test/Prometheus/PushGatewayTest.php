<?php

namespace Test\Prometheus;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\MetricFamilySamples;
use Prometheus\PushGateway;

class PushGatewayTest extends TestCase
{
    /**
     * @test
     *
     * @doesNotPerformAssertions
     */
    public function validResponseShouldNotThrowException(): void
    {
        $mockedCollectorRegistry = $this->createMock(CollectorRegistry::class);
        $mockedCollectorRegistry->method('getMetricFamilySamples')->with()->willReturn([
            $this->createMock(MetricFamilySamples::class)
        ]);

        $mockHandler = new MockHandler([
            new Response(200),
            new Response(202),
        ]);
        $handler = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handler]);

        $pushGateway = new PushGateway('http://foo.bar', $client);
        $pushGateway->push($mockedCollectorRegistry, 'foo');
    }

    /**
     * @test
     *
     * @doesNotPerformAnyAssertions
     */
    public function invalidResponseShouldThrowRuntimeException(): void
    {
        $this->expectException(\RuntimeException::class);

        $mockedCollectorRegistry = $this->createMock(CollectorRegistry::class);
        $mockedCollectorRegistry->method('getMetricFamilySamples')->with()->willReturn([
            $this->createMock(MetricFamilySamples::class)
        ]);

        $mockHandler = new MockHandler([
            new Response(201),
            new Response(300),
        ]);
        $handler = HandlerStack::create($mockHandler);
        $client = new Client(['handler' => $handler]);

        $pushGateway = new PushGateway('http://foo.bar', $client);
        $pushGateway->push($mockedCollectorRegistry, 'foo');
    }

    /**
     * @test
     */
    public function clientGetsDefinedIfNotSpecified(): void
    {
        $this->expectException(\RuntimeException::class);

        $mockedCollectorRegistry = $this->createMock(CollectorRegistry::class);
        $mockedCollectorRegistry->method('getMetricFamilySamples')->with()->willReturn([
            $this->createMock(MetricFamilySamples::class)
        ]);

        $pushGateway = new PushGateway('http://foo.bar');
        $pushGateway->push($mockedCollectorRegistry, 'foo');
    }
}
