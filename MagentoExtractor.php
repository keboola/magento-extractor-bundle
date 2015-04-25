<?php

namespace Keboola\MagentoExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor,
	Keboola\ExtractorBundle\Config\Config;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use GuzzleHttp\Client as Client,
	GuzzleHttp\Subscriber\Oauth\Oauth1;
use Keboola\MagentoExtractorBundle\MagentoExtractorJob;

class MagentoExtractor extends Extractor
{
	protected $name = "magento";

	public function run(Config $config) {

// $this->testOAuth($config->getAttributes()['api_url'] . '/api/rest/', $config->getAttributes()['oauth']);
		$client = new Client(
 			[
				"base_url" => $config->getAttributes()['api_url'] . '/api/rest/', // from CFG."/api/rest"
				"defaults" => [
					"auth" => "oauth",
					"headers" => ['Content-Type' => 'application/json',"Accept" => "*/*"]
				]
			]
		);
		$client->getEmitter()->attach($this->getBackoff());

		$OAuth = $config->getAttributes()['oauth'];
		$client->getEmitter()->attach(new Oauth1([
			'consumer_key' => $OAuth['consumer_key'],
			'consumer_secret' => $OAuth['consumer_secret'],
			'request_method' => Oauth1::REQUEST_METHOD_HEADER,
			'token' => $OAuth['oauth_token'],
			'token_secret' => $OAuth['oauth_token_secret']
		]));

		$parser = $this->getParser($config);

		foreach($config->getJobs() as $jobConfig) {
			// Otherwise it must be created like Above example, OR within the job itself
			$job = new MagentoExtractorJob($jobConfig, $client, $parser);
			$job->run();
		}

		// ONLY available in the Json/Wsdl parsers -
		// otherwise just pass an array of CsvFile OR Common/Table files to upload
		return $parser->getCsvFiles();
	}

protected function testOAuth($url, array $oauth)
{

// $url = 'http://advintage.staging.nextdigital.com/api/rest/';
	$o = new \OAuth($oauth['consumer_key'], $oauth['consumer_secret']);
	$o->enableDebug();
	$o->setToken($oauth['oauth_token'], $oauth['oauth_token_secret']);
	try {
		$o->fetch($url . 'products', [], 'GET', ['Content-Type' => 'application/json',"Accept" => "*/*"]);

	} catch(\Exception $e) {
		var_dump($e);
		die();

	}

	$productsList = json_decode($o->getLastResponse());
	print_r($productsList);
	die();

}

}
