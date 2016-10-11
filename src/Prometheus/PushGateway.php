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
     * Uses HTTP PUT.
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     */
    public function push(CollectorRegistry $collectorRegistry, $job, $groupingKey = null)
    {
        $this->doPush($collectorRegistry, $job, $groupingKey, 'put');
    }

    /**
     * Pushes all metrics in a Collector, replacing only previously pushed metrics of the same name and job.
     * Uses HTTP POST.
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     */
    public function pushAdd(CollectorRegistry $collectorRegistry, $job, $groupingKey = null)
    {
        $this->doPush($collectorRegistry, $job, $groupingKey, 'post');
    }

    /**
     * @param CollectorRegistry $collectorRegistry
     * @param $job
     * @param $groupingKey
     * @param $method
     */
    private function doPush(CollectorRegistry $collectorRegistry, $job, $groupingKey, $method)
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
        $response = $client->request($method, $url, array(
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
