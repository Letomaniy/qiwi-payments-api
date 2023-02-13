<?php

class QiwiPayment
{  
	const GET = 'GET';
	const POST = 'POST';
	const PUT = 'PUT';
	const BILLS_URI = 'https://api.qiwi.com/partner/bill/v1/bills/';
	const CLIENT_NAME = 	'php_sdk';
	const CLIENT_VERSION= 	'0.2.2';
	const CREATE_URI= 		'https://oplata.qiwi.com/create?publicKey=';
	protected $secretKey,$publicKey; 
	protected $internalCurl;
	function __construct(array $params/*, array $options=[]*/){
		$params = array_replace_recursive(
            [
                'secretKey' => null, 
                'publicKey' => null
            ],
            $params
        ); 
        $this->secretKey = $params['secretKey'];
		$this->publicKey = $params['publicKey']; 

		$this->internalCurl = curl_init();
        //curl_setopt_array(
        //    $this->internalCurl,
        //    ($options + [
        //        CURLOPT_USERAGENT => self::CLIENT_NAME.'-'.self::CLIENT_VERSION,
        //    ])
        //);
    } 
	function guid(){
		if (function_exists('com_create_guid') === true)
			return trim(com_create_guid(), '{}');
		
		$data = openssl_random_pseudo_bytes(16);
		$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
		$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
		return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
	}
	
	 /**
     * Creating checkout link.
     *
     * @param array $params The parameters:
     *                      + billId     {string|number} - The bill identifier;
     *                      + publicKey  {string}        - The publicKey;
     *                      + amount     {string|number} - The amount;
     *                      + successUrl {string}        - The success url.
     *
     * @return string Return result
     */
	function CreateBillForm(array $params)/*returned link to payment form*/	{  
		$params = array_replace_recursive(
            [
                'billId'       => null, 
                'amount'       => null,
                'successUrl'   => null,
				'lifetime' => null,
                'customFields' => [],
            ],
            $params
        ); 
		//&customFields[paySourcesFilter]=qw,card&lifetime=2020-12-01T0509
		$link = self::CREATE_URI . $this->publicKey;
		 
		if(!empty($params['billId']))
		{
			$link = $link . '&billId='.$params['billId'];
		}else
		{
			$link = $link . '&billId='. $this->guid();
		}  
		if(!empty($params['amount']))
		{
			$link = $link.'&amount='.$params['amount'];
		}  
		if(!empty($params['successUrl']))
		{
			$link = $link.'&successUrl='.$params['successUrl'];
		}  
		if(!empty($params['lifetime']))
		{
			$link = $link.'&lifetime='.$params['lifetime'];
		}
		if(!empty($params['customFields']))
		{
			$link = $link.'&customFields='.$params['customFields'];
		}
		return $link;
	} 


    /**
     * Creating bill.
     * https://developer.qiwi.com/ru/p2p-payments/#create
     * @param string|number $billId The bill identifier.
     * @param array         $params The parameters:
     *                              + amount             {string|number} The amount;
     *                              + currency           {string}        The currency;
     *                              + comment            {string}        The bill comment;
     *                              + expirationDateTime {string}        The bill expiration datetime (ISOstring);
     *                              + phone              {string}        The phone;
     *                              + email              {string}        The email;
     *                              + account            {string}        The account;
     *                              + successUrl         {string}        The success url;
     *                              + customFields       {array}         The bill custom fields.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
	function CreateBill($billId, array $params)/*created bill*/{
		$params = array_replace_recursive(
            [
                'amount'             => null,
                'currency'           => null,
                'comment'            => null,
                'expirationDateTime' => null,
                'phone'              => null,
                'email'              => null,
                'account'            => null,
                'successUrl'         => null,
                'customFields'       => [
                    'apiClient'        => self::CLIENT_NAME,
                    'apiClientVersion' => self::CLIENT_VERSION,
                ],
            ],
            $params
        ); 
		$bill = $this->requestBuilder($billId, self::PUT,
			array_filter(
                [
                    'amount'             => array_filter(
                        [
                            'currency' => (string) $params['currency'],
                            'value'    => $this->normalizeAmount($params['amount']),
                        ]
                    ),
                    'comment'            => (string) $params['comment'],
                    'expirationDateTime' => (string) $params['expirationDateTime'],
                    'customer'           => array_filter(
                        [
                            'phone'   => (string) $params['phone'],
                            'email'   => (string) $params['email'],
                            'account' => (string) $params['account'],
                        ]
                    ),
                    'customFields'       => array_filter($params['customFields']),
                ]
            ));
		if (false === empty($bill['payUrl']) && false === empty($params['successUrl'])) {
            $bill['payUrl'] = $this->getPayUrl($bill, $params['successUrl']);
        }
		return $bill;
	}

	
	/**
     * Getting bill info.
     *
     * @param string|number $billId The bill identifier.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function getBillInfo($billId)    {
        return $this->requestBuilder($billId);

    }//end getBillInfo()
	
	 /**
     * Cancelling unpaid bill.
     *
     * @param string|number $billId The bill identifier.
     *
     * @return array Return result.
     *
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    public function cancelBill($billId)    {
        return $this->requestBuilder($billId.'/reject', self::POST);

    }//end cancelBill()
	
	
	 
	
	 protected function buildUrl(array $parsedUrl)
    {
        if (true === isset($parsedUrl['scheme'])) {
            $scheme = $parsedUrl['scheme'].'://';
        } else {
            $scheme = '';
        }

        if (true === isset($parsedUrl['host'])) {
            $host = $parsedUrl['host'];
        } else {
            $host = '';
        }

        if (true === isset($parsedUrl['port'])) {
            $port = ':'.$parsedUrl['port'];
        } else {
            $port = '';
        }

        if (true === isset($parsedUrl['user'])) {
            $user = (string) $parsedUrl['user'];
        } else {
            $user = '';
        }

        if (true === isset($parsedUrl['pass'])) {
            $pass = ':'.$parsedUrl['pass'];
        } else {
            $pass = '';
        }

        if (false === empty($user) || false === empty($pass)) {
            $host = '@'.$host;
        }

        if (true === isset($parsedUrl['path'])) {
            $path = (string) $parsedUrl['path'];
        } else {
            $path = '';
        }

        if (true === isset($parsedUrl['query'])) {
            $query = '?'.$parsedUrl['query'];
        } else {
            $query = '';
        }

        if (true === isset($parsedUrl['fragment'])) {
            $fragment = '#'.$parsedUrl['fragment'];
        } else {
            $fragment = '';
        }

        return $scheme.$user.$pass.$host.$port.$path.$query.$fragment;

    }//end buildUrl()
	
	/**
     * Get pay URL witch success URL param.
     *
     * @param array  $bill       The bill data:
     *                           + payUrl {string} Payment URL.
     * @param string $successUrl The success URL.
     *
     * @return string
     */
    public function getPayUrl(array $bill, $successUrl)    {
        // Preset required fields.
        $bill = array_replace(
            ['payUrl' => null],
            $bill
        );

        $payUrl = parse_url((string) $bill['payUrl']);
        if (true === array_key_exists('query', $payUrl)) {
            parse_str($payUrl['query'], $query);
            $query['successUrl'] = $successUrl;
        } else {
            $query = ['successUrl' => $successUrl];
        }

        $payUrl['query'] = http_build_query($query, '', '&', PHP_QUERY_RFC3986);

        return $this->buildUrl($payUrl);

    }//end getPayUrl()
	
	
	public function normalizeAmount($amount=0)    {
        return number_format(round(floatval($amount), 2, PHP_ROUND_HALF_DOWN), 2, '.', '');

    }
	/**
     * Build request.
     *
     * @param string $uri    The url.
     * @param string $method The method.
     * @param array  $body   The body.
     *
     * @return bool|array Return response.
     *
     * @throws Exception Throw on unsupported $method use.
     * @throws BillPaymentsException Throw on API return invalid response.
     */
    protected function requestBuilder($uri, $method=self::GET, array $body=[])    {
        $curl    = curl_copy_handle($this->internalCurl); 
        $url     = (string)self::BILLS_URI . $uri;
		 
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer '.$this->secretKey,
        ];
        if (true !== empty($body) && self::GET !== $method) {
            $body    = json_encode($body, JSON_UNESCAPED_UNICODE);
            $headers = array_merge(
                $headers,
                [
                    'Content-Type: application/json;charset=UTF-8',
                    'Content-Length: '.strlen($body),
                ]
            );
        }

        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL            => $url,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_CUSTOMREQUEST  => $method,
                CURLOPT_POSTFIELDS     => $body,
                CURLOPT_RETURNTRANSFER => 1,
            ]
        );
        $response = curl_exec($curl);

        if (false === $response) {
            //throw new BillPaymentsException($curl, curl_error($curl), curl_getinfo($curl, CURLINFO_RESPONSE_CODE));
        }

        if (false === empty($response)) {
            $json = json_decode($response, true);
            if (null === $json) {
                //throw new BillPaymentsException($curl, json_last_error_msg(), json_last_error());
            }

            if (true === isset($json['errorCode'])) {
                if (true === isset($json['description'])) {
                    //throw new BillPaymentsException($curl, $json['description']);
                }

                //throw new BillPaymentsException($curl, $json['errorCode']);
            }

            return $json;
        }

        return true;

    }//end requestBuilder()
} 
?>