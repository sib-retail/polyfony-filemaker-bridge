<?php

namespace Filemaker;

/*
 * Config.ini
 *
 * [bridge]
 * url = ""
 * dns = ""
 * timeout = "10"
 * retry = "3"
 * 
 * 
 * 
 */

class Bridge {


	public static function execute($mixed) {
	
		// start profiling
		\Polyfony\Profiler::setMarker('remote_start');
		
		// prepate a new http request
		$request = new \Polyfony\HttpRequest( 
			// to the proper destination
			\Polyfony\Config::get('bridge', 'url'),
			'POST',
			\Polyfony\Config::get('bridge', 'timeout'),
			\Polyfony\Config::get('bridge', 'retry') 
		);
	
		// serialize the query string or object
		$query = base64_encode(gzcompress(serialize($mixed), 9));

		// generate a key for this remote request
		$key = \Polyfony\Keys::generate(array(
			'dsn'		=> \Polyfony\Config::get('bridge', 'dsn'),
			'query'		=> $query
		));
	
		// pass the request
		$request->data('query', $query);
		// pass the dns
		$request->data('dsn', \Polyfony\Config::get('bridge', 'dsn'));
		// pass the key securing the whole
		$request->data('key', $key);
		
		// try to execute
		try {
			
			// send the actual request
			$success = $request->send();
			
			// if it succeeded
			if($success) {
				
				// check for matching keys
				$body = $request->getBody();

				// check if the body is an array
				if(is_array($body)) {
						
					// check if the key matches
					if(\Polyfony\Keys::compare(
						// the given key
						$body['key'],
						// what data is should reflect
						array(
							'dsn'		=> $body['dsn'],
							'result'	=> $body['result']
						)
					)) {
						// we can trust the result
						return unserialize( gzuncompress( base64_decode( $body['result'] ) ) );
					}
					else { Throw new \Polyfony\Exception('Curl returned data that we cannot trust', 500); }
				}
				else { Throw new \Polyfony\Exception('Curl returned data in wrong format', 500); }	
			}
			// curl failed 
			else {
				// fatal exception
				Throw new \Polyfony\Exception(
					'Curl failed to execute the remote request'. !$request->getBody() ?: ' : ' . $request->getBody(true), 
					500
				);
			}
			
		}
		// catch any exception
		catch (\Polyfony\Exception $e) {
		
			// throw a fatal exception 
			Throw $e;
			
		}
	
		// stop profiling
		\Polyfony\Profiler::setMarker('remote_end');
		
	}
	
}

?>