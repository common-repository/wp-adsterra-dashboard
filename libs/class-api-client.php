<?php

/*
 * https://docs.adsterratools.com/public/v3/publishers-api/
 */

class WPAdsterraDashboardAPIClient {

    const BASE_HOST = 'https://api3.adsterratools.com/publisher';

    private $domain = '';
    private $token = '';

    public function __construct($token, $domain = null) {
        $this->token = $token;
        $this->domain = $domain;
    }

    private function doGet($endpoint, array $payload = []) {

        $args = array(
            'body' => $payload,
            'timeout' => 10,
            'redirection' => 3,
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-Key' => $this->token
            ]
        );

        $ret = wp_remote_get(self::BASE_HOST . $endpoint, $args);

        if (is_wp_error($ret)) {
            return false;
            //$ret = $ret['body'];
        }

        return json_decode(wp_remote_retrieve_body($ret), true);
    }

    public function getDomains() {
        $retDomains = $this->doGet('/domains.json', []);

        $ret = [];
        if (empty($retDomains['items'])) {
            return $retDomains;
        }

        foreach ($retDomains['items'] as $domain) {
            $ret[] = [
                "id" => $domain['id'],
                "title" => $domain['title'],
            ];
        }

        return $ret;
    }

    public function getPlacementsByDomainID($domain_id) {

        $ret = [];

        if (empty($domain_id)) {
            return $ret;
        }

        $retPlacements = $this->doGet('/domain/' . $domain_id . '/placements.json', []);

        if (empty($retPlacements['items'])) {
            return $retPlacements;
        }

        foreach ($retPlacements['items'] as $placement) {

            $title = $placement['title'];

            if ($title != $placement['alias']) {
                $title = $placement['alias'];
            }
            $ret[] = [
                "id" => $placement['id'],
                "title" => $title
            ];
        }

        return $ret;
    }

    public function getStatsByPlacementID($domain_id, $placement_id, $parameters = []) {
		
		$queryString = http_build_query([
			'domain' => $domain_id,
			//'placement' => $placement_id,
			'start_date' => $parameters['start_date'],
			'finish_date' => $parameters['finish_date'],
		]);
		
        return $this->doGet('/stats.json?' . $queryString);
    }
}
