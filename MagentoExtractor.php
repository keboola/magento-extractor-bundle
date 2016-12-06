<?php

namespace Keboola\MagentoExtractorBundle;

use Keboola\ExtractorBundle\Extractor\Extractors\JsonExtractor as Extractor,
	Keboola\ExtractorBundle\Config\Config;
use Syrup\ComponentBundle\Exception\SyrupComponentException;
use GuzzleHttp\Client as Client,
	GuzzleHttp\Subscriber\Oauth\Oauth1;
use Keboola\MagentoExtractorBundle\MagentoExtractorJob;
use	Keboola\Code\Builder;

class MagentoExtractor extends Extractor
{
	protected $name = "magento";

	private function generateRandomString($length = 10) {
		$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$charactersLength = strlen($characters);
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}

	public function run(Config $config)
	{
		$signatureMethod = $config->getAttributes()['signature_method'];

		$clientConfig = [
			"base_url" => $config->getAttributes()['api_url'] . '/api/rest/', // from CFG."/api/rest"
			"defaults" => [
				"headers" => [
					'Content-Type' => 'application/json',
					"Accept" => "*/*",
					'User-Agent' => 'Keboola Connection Magento Extractor'
				]
			]
		];

		// Vokurka: Constructing my own signed OAuth1 request, because implementation in Guzzle is just wrong.
		if ($signatureMethod == 'PLAINTEXT')
		{
			$clientConfig['defaults']['headers']['Authorization'] = 'OAuth oauth_consumer_key="'.$OAuth['consumer_key'].'",oauth_token="'.$OAuth['oauth_token'].'",oauth_signature_method="PLAINTEXT",oauth_timestamp="'.time().'",oauth_nonce="'.$this->generateRandomString().'",oauth_signature="'.$OAuth['consumer_secret'].'%26'.$OAuth['oauth_token_secret'].'"';
		}
		else
		{
			$clientConfig['defaults']['auth'] = "oauth";
		}
		
		$client = new Client($clientConfig);
		$client->getEmitter()->attach($this->getBackoff());

		$OAuth = $config->getAttributes()['oauth'];

		// Vokurka: in case we are not using plaintext signature, we do everything normally
		if ($signatureMethod != 'PLAINTEXT'){
			$client->getEmitter()->attach(new Oauth1([
				'consumer_key' => $OAuth['consumer_key'],
				'consumer_secret' => $OAuth['consumer_secret'],
				'request_method' => Oauth1::REQUEST_METHOD_HEADER,
				'token' => $OAuth['oauth_token'],
				'token_secret' => $OAuth['oauth_token_secret'],
				'signature_method' => $this->configSignatureToOAuth($config->getAttributes()['signature_method'])
			]));
		}

		$parser = $this->getParser($config);
		$builder = new Builder();

		foreach($config->getJobs() as $jobConfig) {

			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] =
				empty($this->metadata['jobs.lastStart.' . $jobConfig->getJobId()])
					? 0
					: $this->metadata['jobs.lastStart.' . $jobConfig->getJobId()];
			$this->metadata['jobs.start.' . $jobConfig->getJobId()] = time();

			// Otherwise it must be created like Above example, OR within the job itself
			$job = new MagentoExtractorJob($jobConfig, $client, $parser);
			$job->setConfigMetadata($this->metadata);
			$job->setBuilder($builder);
			$job->run();

			$this->metadata['jobs.lastStart.' . $jobConfig->getJobId()] = $this->metadata['jobs.start.' . $jobConfig->getJobId()];
		}

		$this->updateParserMetadata($parser);
		return $parser->getCsvFiles();
	}

	private function configSignatureToOAuth($configSignature)
	{
		switch($configSignature)
		{
			case 'HMAC-SHA1':
				return Oauth1::SIGNATURE_METHOD_HMAC;

			case 'PLAINTEXT':
				return Oauth1::SIGNATURE_METHOD_PLAINTEXT;

			case 'RSA-SHA1':
				return Oauth1::SIGNATURE_METHOD_RSA;
		}

		return Oauth1::SIGNATURE_METHOD_HMAC;
	}
}
