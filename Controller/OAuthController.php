<?php

namespace Keboola\MagentoExtractorBundle\Controller;

use Keboola\ExtractorBundle\Controller\OAuth10Controller,
	Keboola\ExtractorBundle\Config\Configuration;

use	Keboola\StorageApi\Client;
use	Keboola\StorageApi\Config\Reader;
use	Keboola\Temp\Temp;
use Syrup\ComponentBundle\Exception\UserException;

class OAuthController extends OAuth10Controller
{
	/**
	 * @var string
	 */
	protected $appName = "ex-magento";

	/**
	 * See (A) at @link http://oauth.net/core/diagram.png
	 * ie: https://api.example.com/oauth/request_token
	 * @var string
	 */
	protected $requestTokenUrl = "/oauth/initiate";

	/**
	 * See (E) at @link http://oauth.net/core/diagram.png
	 * ie: https://api.example.com/oauth/access_token
	 * @var string
	 */
	protected $accessTokenUrl = "/oauth/token";
	
	
        public function preExecute(Request $request)
        {
            parent::preExecute($request);
            Request::setTrustedProxies(array($request->server->get('REMOTE_ADDR')));
        }
	
	/**
	 * Create OAuth /authenticate URL
	 * See (C) at @link http://oauth.net/core/diagram.png
	 * @param string $redirUrl Redirect URL
	 * @return string URL
	 * ie: return "https://api.example.com/oauth/authenticate?oauth_token={$oauthToken}"
	 */
	protected function getAuthenticateUrl($oauthToken)
	{
		return $this->getBaseUrlFromSession() . "/admin/oauth_authorize?oauth_token={$oauthToken}"; // this is for admin access
// 		return $this->getBaseUrlFromSession() . "/oauth/authorize?oauth_token={$oauthToken}"; // we shan't need user access
	}

	protected function getRequestTokenUrl()
	{
		return $this->getBaseUrlFromSession() . $this->requestTokenUrl;
	}

	protected function getAccessTokenUrl()
	{
		return $this->getBaseUrlFromSession() . $this->accessTokenUrl;
	}

	/**
	 * TODO might better use Config class, would be slower though!
	 * @param string $token
	 * @param string $config
	 * @return string
	 */
	protected function getBaseUrl($token, $config)
	{
		// TODO get consumer key + secret from attrs as well!
		// TODO use EX Bundle configuration
		$bucket = Reader::read("sys.c-{$this->appName}", $token);
		if (empty($bucket['items'][$config])) {
			throw new UserException("Configuration '{$config}' doesn't exist!");
		}
		if (empty($bucket['items'][$config]['api_url'])) {
			throw new UserException("Configuration '{$config}' doesn't have an 'api_url' attribute!");
		}

		return $bucket['items'][$config]['api_url'];
	}

	protected function getBaseUrlFromSession()
	{
		return $this->getBaseUrl(
			$this->sessionBag->get('token'),
			$this->sessionBag->get('config')
		);
	}

	protected function getAppParams()
	{
		$configuration = new Configuration($this->appName, new Temp($this->appName), []);
		$configuration->setStorageApi(new Client(['token' => $this->sessionBag->get('token')]));
		$data = $configuration->getConfig(
			['config' => $this->sessionBag->get('config')],
			"sys.c-{$this->appName}"
		)->getAttributes();

		$params = [];
		if (empty($data['oauth']['consumer_key'])) {
			throw new UserException('Missing configuration attribute \'oauth.consumer_key\'!');
		} else {
			$params['api-key'] = $data['oauth']['consumer_key'];
		}
		if (empty($data['oauth']['consumer_secret'])) {
			throw new UserException('Missing configuration attribute \'oauth.consumer_secret\'!');
		} else {
			$params['api-secret'] = $data['oauth']['consumer_secret'];
		}

		return $params;
	}
}
