<?php
/**
 * Created by PhpStorm.
 * User: syacko
 * Date: 3/27/18
 * Time: 9:23 AM
 *
 **/

require __DIR__ . '/vendor/autoload.php';

$dotEnv = new \Dotenv();
$dotEnv->load(__DIR__ . '/../', 'APIMonitor.env');

$apiPlatforms = explode(',',$_ENV['APP_TARGET_PLATFORMS']);

$accessTokenIssuer = $_ENV['APP_ACCESSTOKEN_ISSUER'];
$alg = $_ENV['APP_ALGORITHMS'];

$postFields['DEMO_EDENINFO'] = str_replace('`', '"', $_ENV['DEMO_EDENINFO']);
$postFields['LOCAL_EDENINFO'] = str_replace('`', '"', $_ENV['LOCAL_EDENINFO']);
$postFields['DEMO_MEMBERS'] = str_replace('`', '"', $_ENV['DEMO_MEMBERS']);
$postFields['LOCAL_MEMBERS'] = str_replace('`', '"', $_ENV['LOCAL_MEMBERS']);

$curl = curl_init();

curl_setopt_array($curl, array(
	CURLOPT_URL => $accessTokenIssuer,
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "POST",
	CURLOPT_POSTFIELDS => $postFields['LOCAL_EDENINFO'],
	CURLOPT_HTTPHEADER => array(
		"content-type: application/json"
	),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
	echo "cURL Error #:" . $err;
} else {
	$token = json_decode($response)->access_token;
}

$headers = [
	'Authorization' => $token,
	'Accept'        => 'application/json',
	'Cache-Control' => 'no-cache',
	'verify'        => false,
];

$apiParams['0'] = explode(',', $_ENV['CURL_HOSTPARAMS_1']);

var_dump($apiParams);

$client = new \GuzzleHttp\Client(['base_uri' => 'https://' . $apiParams['0'][0] . ':' . $apiParams['0'][1], 'timeout' => 2.0]);
$res = $client->request('GET', '/edeninfo/version', ['verify' => false, 'headers' => $headers]);
$apiResults = json_decode($res->getBody());
var_dump($apiResults);