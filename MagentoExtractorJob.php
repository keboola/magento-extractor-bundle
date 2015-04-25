<?php

namespace Keboola\MagentoExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonJob;
use	Keboola\Utils\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException;

class MagentoExtractorJob extends JsonJob
{
	protected $configName;

	/**
	 * Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{
		$params = Utils::json_decode($this->config["params"], true);
		$url = Utils::buildUrl(trim($this->config["endpoint"], "/"), $params);

		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		return $this->client->createRequest("GET", $url);
	}

	/**
	 * Return a download request OR false if no next page exists
	 *
	 * @param $response
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request | false
	 */
	protected function nextPage($response, $data)
	{
// 		if (empty($response->pagination->next_url)) {
			return false;
// 		}

// 		return $this->client->createRequest("GET", $response->pagination->next_url);
	}

	/**
	 * Call the parser and handle its return value
	 *
	 * @param object $response
	 */
	protected function parse($response)
	{
// ini_set('xdebug.var_display_max_depth', -1);
// ini_set('xdebug.var_display_max_children', -1);
// ini_set('xdebug.var_display_max_data', -1);
// var_dump(json_encode($response, JSON_PRETTY_PRINT));
// die();

		/**
		 * Edit according to the parser used
		 */
		parent::parse((array) $response);
	}
}
