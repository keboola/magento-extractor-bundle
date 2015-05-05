<?php

namespace Keboola\MagentoExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Jobs\JsonRecursiveJob;
use	Keboola\Utils\Utils;
use Syrup\ComponentBundle\Exception\SyrupComponentException,
	Syrup\ComponentBundle\Exception\UserException;
use	Keboola\Code\Builder,
	Keboola\Code\Exception\UserScriptException;

class MagentoExtractorJob extends JsonRecursiveJob
{
	protected $configName;

	/**
	 * @var int
	 */
	protected $page = 1;

	/**
	 * @var array
	 */
	protected $configMetadata;

	/**
	 * @var Builder
	 */
	protected $stringBuilder;

	/**
	 * @var string
	 */
	protected $lastResponseHash;

	public function run()
	{
		$this->configName = preg_replace("/[^A-Za-z0-9\-\._]/", "_", trim($this->config["endpoint"], "/"));

		$this->params = (array) Utils::json_decode($this->config["params"]);

		if (!empty($this->params)) {
			try {
				foreach($this->params as $key => &$value) {
					if (is_object($value)) {
						$value = $this->stringBuilder->run($value, ['metadata' => $this->configMetadata]);
					}
					unset($value);
				}
			} catch(UserScriptException $e) {
				throw new UserException("User function failed: " . $e->getMessage());
			}
		}

		$request = $this->firstPage();
		while ($request !== false) { // TODO !empty sounds better, doesn't it? Perhaps it's lazy?
			$response = $this->download($request);

			$responseHash = sha1(serialize($response));
			if ($responseHash == $this->lastResponseHash) {
				break;
			} else {
				$this->lastResponseHash = $responseHash;
				$data = $this->parse($response);
				$request = $this->nextPage($response, $data);
			}
		}
	}

	/**
	 * Return a download request
	 *
	 * @return \Keboola\ExtractorBundle\Client\SoapRequest | \GuzzleHttp\Message\Request
	 */
	protected function firstPage()
	{

		$url = Utils::buildUrl(trim($this->config["endpoint"], "/"), $this->params);

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
		$limit = empty($this->params['limit']) ? 10 : $this->params['limit'];
		if (count($data) < $limit) {
			return false;
		}

		$this->page++;

		$url = Utils::buildUrl(
			trim($this->config["endpoint"], "/"),
			array_replace((array) $this->params, ['page' => $this->page])
		);

		return $this->client->createRequest("GET", $url);
	}

	/**
	 * Call the parser and handle its return value
	 *
	 * @param object $response
	 */
	protected function parse($response)
	{
		return parent::parse((array) $response);
	}

	public function setConfigMetadata(array $data)
	{
		$this->configMetadata = $data;
	}

	/**
	 * @param Builder $builder
	 */
	public function setBuilder(Builder $builder)
	{
		$this->stringBuilder = $builder;
	}
}
