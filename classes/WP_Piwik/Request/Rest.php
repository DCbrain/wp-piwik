<?php

	namespace WP_Piwik\Request;

	class Rest extends \WP_Piwik\Request {
			
		protected function request($id) {
			$count = 0;
			$url = self::$settings->getGlobalOption('piwik_mode') == 'http'?
				self::$settings->getGlobalOption('piwik_url'):
				'https://'.self::$settings->getGlobalOption('piwik_user').'.piwik.pro/';
			$params = 'module=API&method=API.getBulkRequest&format=php';
			foreach (self::$requests as $requestID => $config) {
				if (!isset(self::$results[$requestID])) {
					$params .= '&urls['.$count.']='.urlencode($this->buildURL($config));
					$map[$count] = $requestID;
					$count++;
				}
			}
			$results = ((function_exists('curl_init') && ini_get('allow_url_fopen') && self::$settings->getGlobalOption('http_connection') == 'curl') || (function_exists('curl_init') && !ini_get('allow_url_fopen')))?$this->curl($id, $url, $params):$this->fopen($id, $url, $params);
			if (is_array($results))
				foreach ($results as $num => $result)
					self::$results[$map[$num]] = $result;
		}
			
		private function curl($id, $url, $params) {
			if (self::$settings->getGlobalOption('http_method')=='post') {
				$c = curl_init($url);
				curl_setopt($c, CURLOPT_POST, 1);
				curl_setopt($c, CURLOPT_POSTFIELDS, $params.'&token_auth='.self::$settings->getGlobalOption('piwik_token'));
			} else $c = curl_init($url.'?'.$params.'&token_auth='.self::$settings->getGlobalOption('piwik_token'));
			curl_setopt($c, CURLOPT_SSL_VERIFYPEER, !self::$settings->getGlobalOption('disable_ssl_verify'));
			curl_setopt($c, CURLOPT_USERAGENT, self::$settings->getGlobalOption('piwik_useragent')=='php'?ini_get('user_agent'):self::$settings->getGlobalOption('piwik_useragent_string'));
			curl_setopt($c, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($c, CURLOPT_HEADER, $GLOBALS ['wp-piwik_debug'] );
			curl_setopt($c, CURLOPT_TIMEOUT, self::$settings->getGlobalOption('connection_timeout'));
			$httpProxyClass = new \WP_HTTP_Proxy();
			if ($httpProxyClass->is_enabled() && $httpProxyClass->send_through_proxy($strURL)) {
				curl_setopt($c, CURLOPT_PROXY, $httpProxyClass->host());
				curl_setopt($c, CURLOPT_PROXYPORT, $httpProxyClass->port());
				if ($httpProxyClass->use_authentication())
					curl_setopt($c, CURLOPT_PROXYUSERPWD, $httpProxyClass->username().':'.$httpProxyClass->password());
			}
			$result = curl_exec($c);
			if ($GLOBALS ['wp-piwik_debug']) {
				$header_size = curl_getinfo($c, CURLINFO_HEADER_SIZE);
				$header = substr($result, 0, $header_size);
				$body = substr($result, $header_size);
				$result = $this->unserialize($body);
				self::$debug[$id] = array ( $header, $url.'?'.$params.'&token_auth=...' );
			} else $result = $this->unserialize($result);
			curl_close($c);
			return $result;
		}

		private function fopen($id, $url, $params) {
			$context = stream_context_create(array('http'=>array('timeout' => self::$settings->getGlobalOption('connection_timeout'))));
			if (self::$settings->getGlobalOption('http_method')=='post') {
				$fullUrl = $url;
				$context['http']['method'] = 'POST';
				$context['http']['content'] = $params.'&token_auth='.self::$settings->getGlobalOption('piwik_token');
			} else $fullUrl = $url.'?'.$params.'&token_auth='.self::$settings->getGlobalOption('piwik_token');	
			$result = $this->unserialize(@file_get_contents($fullUrl, false, $context));
			if ($GLOBALS ['wp-piwik_debug'])
				self::$debug[$id] = array ( get_headers($fullUrl, 1), $url.'?'.$params.'&token_auth=...' );
			return $result;
		}
	}