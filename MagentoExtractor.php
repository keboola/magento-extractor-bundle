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

	public function run(Config $config)
	{
		$client = new Client(
 			[
				"base_url" => $config->getAttributes()['api_url'] . '/api/rest/', // from CFG."/api/rest"
				"defaults" => [
					"auth" => "oauth",
					"headers" => [
						'Content-Type' => 'application/json',
						"Accept" => "*/*",
						'User-Agent' => 'Keboola Connection Magento Extractor'
					]
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
}
