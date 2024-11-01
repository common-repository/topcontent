<?php
// Stop direct call
if (preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) die('Not allowed to call this page directly.');

class TOPCONT_API
{

	protected $api_key;
	protected $api_url;

	public function __construct()
	{
		$this->api_key = get_option('topcont-api-key', '');
		$this->api_url = get_option('topcont-api-url', 'https://api.topcontent.com/v2/');
	}

	/**
	 * Balance request
	 *
	 * @return object ( credits : float, status_code : integer )
	 **/
	public function getBalance()
	{
		return $this->getMethod('balance');
	}

	/**
	 * Request a payment link
	 *
	 * @param string/false $return_url : URL to return.
	 * @return object ( href : string, status_code : integer )
	 **/
	public function getPaymentLink($return_url = false)
	{
		return $this->getMethod('balance', $return_url ? array('return_url' => $return_url) : false, true);
	}

	/**
	 * Order an item or group of content items
	 *
	 * @params array $options : Array of options; View: https://topcontent.com/developers/api-documentation/#operation/submit-content-order
	 * @return object ( status_code : integer, others : View: https://topcontent.com/developers/api-documentation/#operation/submit-content-order )
	 **/
	public function contentOrders($options)
	{
		return $this->getMethod('content/orders', $options, true);
	}

	/**
	 * cURL request
	 *
	 * @param string $method : Last part of the request URL
	 * @param array/false $options : Array of options
	 * @param boolean $post : POST or GET request
	 * @return object
	 **/
	private function getMethod($method, $options = false, $post = false)
	{
		$url = $this->api_url . $method;
		$args = array(
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_key,
				'Accept' => 'application/json',
				'Content-Type' => 'application/json',
			)
		);
		if (false !== $options) {
			$args['body'] = json_encode($options, JSON_UNESCAPED_UNICODE);
		}
		if (false !== $post) {
			$response = wp_remote_post($url, $args);
		} else {
			$response = wp_remote_get($url, $args);
		}
		if (!is_wp_error($response)) {
			$result = json_decode(wp_remote_retrieve_body($response));
		} else {
			$result = new stdClass();
			$result->error = $response->get_error_message();
		}
		if ($result === 'Unauthorized.') {
			$result = new stdClass();
			$result->status_code = 401;
		} else {
			if (!is_object($result)) {
				$result = new stdClass();
			}
			$result->status_code = (int) wp_remote_retrieve_response_code($response);
		}
		return $result;
	}
}
