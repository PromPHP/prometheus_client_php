<?php


namespace Prometheus;


use GuzzleHttp\Client;

class PushGateway
{
    private $address;

    /**
     * PushGateway constructor.
     * @param $address string host:port of the push gateway
     */
    public function __construct($address)
    {
        $this->address = $address;
    }

    /**
     * Pushes all metrics in a Collector, replacing all those with the same job.
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     */
    public function push(CollectorRegistry $collectorRegistry, $job, $groupingKey = null)
    {
        $url = "http://" . $this->address . "/metrics/job/" . $job;
        if (!empty($groupingKey)) {
            foreach ($groupingKey as $label => $value) {
                $url .= "/" . $label . "/" . $value;
            }
        }
        $renderer = new RenderTextFormat();
        $textData = $renderer->render($collectorRegistry->getMetricFamilySamples());
        $client = new Client();
        $response = $client->post($url, array(
            'headers' => array(
                'Content-Type' => RenderTextFormat::MIME_TYPE
            ),
            'body' => $textData,
            'connect_timeout' => 10,
            'timeout' => 20,
        ));
        $statusCode = $response->getStatusCode();
        if ($statusCode != 202) {
            $msg = "Unexpected status code " . $statusCode . " received from pushgateway " . $this->address . ": " . $response->getBody();
            throw new \RuntimeException($msg);
        }
    }

}
