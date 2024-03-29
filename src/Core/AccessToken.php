<?php

/*
 * This file is part of the overtrue/wechat.
 *
 * (c) overtrue <i@overtrue.me>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AccessToken.php.
 *
 * @author    overtrue <i@overtrue.me>
 * @copyright 2015 overtrue <i@overtrue.me>
 *
 * @link      https://github.com/overtrue
 * @link      http://overtrue.me
 */
namespace EasyWeChat\Core;

use Doctrine\Common\Cache\Cache;
use Doctrine\Common\Cache\FilesystemCache;
use EasyWeChat\Core\Exceptions\HttpException;

/**
 * Class AccessToken.
 */
class AccessToken
{
	/**
	 * App ID.
	 *
	 * @var string
	 */
	protected $appId;

	/**
	 * App secret.
	 *
	 * @var string
	 */
	protected $secret;

	/**
	 * Cache.
	 *
	 * @var Cache
	 */
	protected $cache;

	/**
	 * Cache Key.
	 *
	 * @var cacheKey
	 */
	protected $cacheKey;

	/**
	 * Http instance.
	 *
	 * @var Http
	 */
	protected $http;

	/**
	 * Query name.
	 *
	 * @var string
	 */
	protected $queryName = 'access_token';

	/**
	 * Cache key prefix.
	 *
	 * @var string
	 */
	protected $prefix = 'easywechat.common.access_token:';

	// API
	const API_TOKEN_GET = 'https://api.weixin.qq.com/cgi-bin/token';

	//************************* NEW ***************************//
	const API_COMMPENT_TOKEN_GET = 'https://api.weixin.qq.com/cgi-bin/component/api_authorizer_token';
	protected $is_component = false;
	protected $component_access_token = false;

	/**
	 * Constructor.
	 *
	 * @param string $appId
	 * @param string $secret
	 * @param \Doctrine\Common\Cache\Cache $cache
	 * @param array $component_info
	 */
	public function __construct($appId, $secret, Cache $cache = null, $component_info = [])
	{
		$this->appId = $appId;
		$this->secret = $secret;
		$this->cache = $cache;
		if (!empty($component_info) && isset($component_info['component_access_token']) && isset($component_info['auth_info'])) {
			$this->setIsComponent($component_info['component_access_token'], $component_info['auth_info']);
		}
	}

	/**
	 * Get token from WeChat API.
	 *
	 * @param bool $forceRefresh
	 *
	 * @return string
	 */
	public function getToken($forceRefresh = false)
	{
		if ($this->access_token) {
			return $this->access_token;
		} else {
			$cacheKey = $this->getCacheKey();
			$cached = $this->getCache()->fetch($cacheKey);

			if ($forceRefresh || empty($cached)) {
				$token = $this->getTokenFromServer();

				// XXX: T_T... 7200 - 1500
				$this->getCache()->save($cacheKey, $token['access_token'], $token['expires_in'] - 1500);

				return $token['access_token'];
			}

			return $cached;
		}
	}

	/**
	 * Return the app id.
	 *
	 * @return string
	 */
	public function getAppId()
	{
		return $this->appId;
	}

	/**
	 * Return the secret.
	 *
	 * @return string
	 */
	public function getSecret()
	{
		return $this->secret;
	}

	/**
	 * Set cache instance.
	 *
	 * @param \Doctrine\Common\Cache\Cache $cache
	 *
	 * @return AccessToken
	 */
	public function setCache(Cache $cache)
	{
		$this->cache = $cache;

		return $this;
	}

	/**
	 * Return the cache manager.
	 *
	 * @return \Doctrine\Common\Cache\Cache
	 */
	public function getCache()
	{
		return $this->cache ?: $this->cache = new FilesystemCache(sys_get_temp_dir());
	}

	/**
	 * Set the query name.
	 *
	 * @param string $queryName
	 *
	 * @return $this
	 */
	public function setQueryName($queryName)
	{
		$this->queryName = $queryName;

		return $this;
	}

	/**
	 * Return the query name.
	 *
	 * @return string
	 */
	public function getQueryName()
	{
		return $this->queryName;
	}

	/**
	 * Return the API request queries.
	 *
	 * @return array
	 */
	public function getQueryFields()
	{
		return [$this->queryName => $this->getToken()];
	}

	/**
	 * Get the access token from WeChat server.
	 *
	 * @throws \EasyWeChat\Core\Exceptions\HttpException
	 *
	 * @return string
	 */
	public function getTokenFromServer()
	{
		$http = $this->getHttp();

		if ($this->is_component && $this->component_access_token) {
			$params = [
				'component_appid' => $this->appId,
				'authorizer_appid' => $this->auth_appid,
				'authorizer_refresh_token' => $this->access_refresh_token
			];
		} else {
			$params = [
				'appid' => $this->appId,
				'secret' => $this->secret,
				'grant_type' => 'client_credential',
			];
		}

		$token = $this->is_component ? $http->parseJSON($http->post(self::API_COMMPENT_TOKEN_GET . '?component_access_token=' . $this->component_token, json_encode($params))) : $http->parseJSON($http->get(self::API_TOKEN_GET, $params));

		if (empty($token['access_token'])) {
			throw new HttpException('Request AccessToken fail. response: ' . json_encode($token, JSON_UNESCAPED_UNICODE));
		}

		return $token;
	}

	/**
	 * Return the http instance.
	 *
	 * @return \EasyWeChat\Core\Http
	 */
	public function getHttp()
	{
		return $this->http ?: $this->http = new Http();
	}

	/**
	 * Set the http instance.
	 *
	 * @param \EasyWeChat\Core\Http $http
	 *
	 * @return $this
	 */
	public function setHttp(Http $http)
	{
		$this->http = $http;

		return $this;
	}

	/**
	 * Set the access token prefix.
	 *
	 * @param string $prefix
	 *
	 * @return $this
	 */
	public function setPrefix($prefix)
	{
		$this->prefix = $prefix;

		return $this;
	}

	/**
	 * Set access token cache key.
	 *
	 * @param string $cacheKey
	 *
	 * @return $this
	 */
	public function setCacheKey($cacheKey)
	{
		$this->cacheKey = $cacheKey;

		return $this;
	}

	/**
	 * Get access token cache key.
	 *
	 * @return string $this->cacheKey
	 */
	public function getCacheKey()
	{
		if (is_null($this->cacheKey)) {
			return $this->is_component ? $this->prefix . $this->auth_appid : $this->prefix . $this->appId;
		}

		return $this->cacheKey;
	}


	//*********************************************** new ***********************************************//


	public function setIsComponent($component_access_token, $auth_info = false)
	{
		if (!$component_access_token) return false;

		$this->is_component = true;
		$this->component_access_token = $component_access_token;
		$this->setComponentAutherInfo($auth_info);
		return $this;
	}

	public function setComponentAutherInfo($auth_info)
	{
		if (is_object($auth_info) && $auth_info->authorizer_appid) {
			$this->auth_appid = $auth_info->authorizer_appid;
			$this->access_token = $auth_info->authorizer_access_token;
			$this->access_refresh_token = $auth_info->authorizer_refresh_token;
			$this->expires_in = $auth_info->expires_time;
			return $this;
		} else {
			return false;
		}

	}


}
