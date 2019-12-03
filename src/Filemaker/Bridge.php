<?php

namespace Filemaker;

use Polyfony\Profiler as Profiler;
use Polyfony\Logger as Logger;
use Polyfony\Keys as Keys;
use Polyfony\Config as Config;
use Polyfony\Exception as Exception;

use \Curl\Curl;
/*
 * Config.ini
 *
 * [bridge]
 * url = ""
 * dns = ""
 * timeout = "10"
 * retry = "3"
 * 
 */

class Bridge {


	public static function execute($mixed) :array {
	
		// start profiling
		$id_marker = Profiler::setMarker('BridgeQuery-'.substr(sha1(microtime()),0,6));
		
		// create the curl request
		$curl_request = new Curl;

		// preconfigure the curl request
		$curl_request->setTimeout(Config::get('bridge', 'timeout'));
		$curl_request->setRetry(Config::get('bridge', 'retry'));
	
		// serialize the query string or object
		$query = base64_encode(gzcompress(serialize($mixed), 9));

		// send the request to the bridge
		$curl_request->post(
			Config::get('bridge', 'url'),
			[
				'dsn'	=>Config::get('bridge', 'dsn'),
				'query'	=>$query,
				'key'	=>Keys::generate([
					'dsn'	=> Config::get('bridge', 'dsn'),
					'query'	=> $query
				])
			]
		);

		// stop profiling (we don't include decoding of the response)
		Profiler::releaseMarker($id_marker);

		// handle the response
		return self::parseResponse($curl_request);
	
		
		
	}

	public static function parseResponse(
		Curl $curl_request
	) :array {

		// if the request encountered an error on Curl's side
		if($curl_request->error) {
			// log details
			Logger::warning('Details of curl error', [
				$curl_request->getErrorMessage(),
				$curl_request->getErrorCode(),
				$curl_request->getCurlErrorMessage(),
				$curl_request->getCurlErrorCode(),
			]);
			// stop execution here
			Throw new Exception(
				$curl_request->getHttpErrorMessage(), 	// or ->getErrorMessage() or ->getCurlErrorMessage()
				$curl_request->getHttpStatusCode() 		// or ->getErrorCode() or ->getCurlErrorCode()
			);
		}
		// if the type of the response is not an array
		elseif(!is_object($curl_request->response)) {
			// log details
			Logger::warning('Curl returned', $curl_request->response);
			// stop execution here
			Throw new Exception(
				'We expected an array but curl got something else (Please look at the logs)',
				500
			);
		}
		// if the keys don't match
		elseif(!Keys::compare(
			// we compare the key we received
			$curl_request->response->key,
			// with the key generated on the fly from the received query result
			[
				'dsn'		=> $curl_request->response->dsn,
				'result'	=> $curl_request->response->result
			]
		)) {
			// log details
			Logger::warning('Here are the differing keys', [
				'expected'=>Keys::generate([
					'dsn'		=> $curl_request->response->dsn,
					'result'	=> $curl_request->response->result
				]),
				'received'=>$curl_request->response->key
			]);
			// stop execution here
			Throw new Exception(
				'Curl returned data that we cannot trust (Please look at the logs)',
				500
			);
		}
		// everything is right, curl is happy, the result is an array and keys are valid (data integrity checked)
		else {
			return unserialize( 
				gzuncompress( 
					base64_decode( 
						$curl_request->response->result 
					)
				)
			);
		}

	}
	
}

?>