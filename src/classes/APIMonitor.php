<?php
/**
 * Created by PhpStorm.
 * User: syacko
 * Date: 3/27/18
 * Time: 12:20 PM
 */

namespace UTILITY;

/**
 * Class APIMonitor
 */
class APIMonitor
{
	/**
	 * Class Variable area
	 */

	/**
	 * @var string $creditialsFileName Location of the gmail creditials for offline access to Google API's
	 */
	protected $creditialsFileName;

	/**
	 * @var string $csFileName Location of the gmail client ID
	 */
	protected $csFileName;

	/**
	 * @var string $version This is the version of the API system
	 */
	protected $version;

	/**
	 * @var string $build This is the build of the API system
	 */
	protected $build;

	/**
	 * @var string $accessTokenIssuer This holds the URL that issues the access token
	 */
	protected $accessTokenIssuer;

	/**
	 * @var "array" $apiEnvornments This holds the platforms that are to be monitored
	 */
	protected $apiEnvornments;

	/**
	 * @var "array" $apiServices This holds the platforms that are to be monitored
	 */
	protected $apiServices;

	/**
	 * @var "array" $postFields This holds the JSON strings needed to request an access token
	 */
	protected $apiPostFields;

	/**
	 * @var "\Monolog\Logger" $myLogger The instance of the Logger
	 */
	protected $myLogger;

	/**
	 * @var string $myAlerts This contains alert messages that will be emailed out
	 */
	protected $myAlerts;

	/**
	 * Monitors EDEN API's
	 *
	 * This will call specifed API's and test the response to make sure they are working. If the API doesn't reply or the respone is different then the given response, an email will be generated.
	 *
	 */
	public function executeAPI()
	{
		$this->myLogger->debug(__METHOD__);

		foreach (array_keys($this->apiEnvornments) as $apiEnv)
		{
			foreach (array_keys($this->apiServices) as $apiServ)
			{
				$headers = $this->buildHeaders($apiEnv, $apiServ);
				$client = new \GuzzleHttp\Client(['base_uri' => 'https://' . $this->apiEnvornments[$apiEnv]['host'] . ':' . $this->apiEnvornments[$apiEnv]['port'], 'timeout' => 2.0]);
				foreach (array_keys($this->apiServices[$apiServ]) as $endPoint)
				{
					switch (strtoupper($endPoint))
					{
//	@todo determine the number of test values and have a test based on that number
//	@todo Add support for testresult - this is the result of the assert that is expected so you can test negative results
						case 'VERSION':
						case 'ISMEMBER':
						case 'CREATEMEMBER':
						case 'ACTIVATEMEMBER':
						case 'CONFIRMMEMBER':
						case 'ISMEMBERACTIVE':
						case 'ISMEMBERCONFIRMED':
						case 'DEACTIVATEMEMBER':
							$res = $this->buildClientRequest($client, $this->apiServices[$apiServ][$endPoint]['method'],  $apiServ, $endPoint, $headers, $this->apiServices[$apiServ][$endPoint]['params']);
							$apiResults = json_decode($res->getBody(), TRUE);
							if (assert((int)$apiResults['errCode'] == (int)$this->apiServices[$apiServ][$endPoint]['testvalues']['errCode']))
							{
								$this->myAlerts[] = date(DATE_ATOM) . '&nbsp;-->&nbsp;ENVIRONMENT:&nbsp;' . $apiEnv . '&nbsp;-->&nbsp;SERVICE:&nbsp;' . $apiServ . '&nbsp;-->&nbsp;ENDPOINT:&nbsp;' . $endPoint . '&nbsp;*** WORKING ***';
							} else
							{
								$this->myAlerts[] = date(DATE_ATOM) . '&nbsp;-->&nbsp;ENVIRONMENT:&nbsp;' . $apiEnv . '&nbsp;-->&nbsp;SERVICE:&nbsp;' . $apiServ . '&nbsp;-->&nbsp;ENDPOINT:&nbsp;' . $endPoint;
								$this->myAlerts[] = date(DATE_ATOM) . '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;RESULTS: ' . json_encode($apiResults);
							}
							break;
						default:
							$this->myAlerts[] = date(DATE_ATOM) . '&nbsp;-->&nbsp;ENVIRONMENT:&nbsp;' . $apiEnv . '&nbsp;-->&nbsp;SERVICE:&nbsp;' . $apiServ . '&nbsp;-->&nbsp;ENDPOINT:&nbsp;' . $endPoint . '&nbsp;*** NOT SUPPORTED - LOG AN ISSUE IN YOUTRACK ***';
					}
				}
				$client = null;
			}
			$this->generateEmail();
		}
	}

	/**
	 * Returns a client request.
	 * @return  \GuzzleHttp\Client response object
	 */
	protected function buildClientRequest($client, string $method, string $apiServ, $endPoint, $headers, array $params = NULL)
	{
		$x = $apiServ;
		$y = $method;
		$z = $headers;
		$myParams = '';
		if ($params == NULL)
		{
//  @todo Support all method types. PUT, DELETE, POST, etc
			$res = $client->request($this->apiServices[$apiServ][$endPoint]['method'], '/' . $apiServ . '/' . $endPoint, ['verify' => FALSE, 'headers' => $headers]);
		} else
		{
//	@todo Support all method types. PUT, DELETE, POST, etc
			foreach ($params as $key => $value)
			{
				$myParams .= $key . '=' . $value . '&';
			}
			$res = $client->request($this->apiServices[$apiServ][$endPoint]['method'], '/' . $apiServ . '/' . $endPoint . '?' . $myParams, ['verify' => FALSE, 'headers' => $headers]);
		}
		return $res;
	}

	/**
	 * Returns a request header.
	 * @return  array
	 */
	protected function buildHeaders(string $apiEnv, string $apiServ)
	{
		return $headers = [
			'Authorization' => $this->execCURL($apiEnv, $apiServ),
			'Accept'        => 'application/json',
			'Cache-Control' => 'no-cache',
//  @todo Read the verify value from the APIMonitor environment file for each environment listed in the API_ENVIRONMENTS setting
			'verify'        => false,
		];

	}

	/**
	 * Returns a Auth0 access token
	 * @return  string
	 */
	protected function execCURL(string $apiEnv, string $apiServ)
	{
		try
		{
			$curl = curl_init();
			curl_setopt_array($curl, array(
				CURLOPT_URL            => $this->accessTokenIssuer,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_ENCODING       => "",
				CURLOPT_MAXREDIRS      => 10,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
				CURLOPT_CUSTOMREQUEST  => "POST",
				CURLOPT_POSTFIELDS     => json_encode($this->apiPostFields[$apiEnv . '_' . $apiServ]),
				CURLOPT_HTTPHEADER     => array("content-type: application/json"),
			));
			$response = curl_exec($curl);
			$err = curl_error($curl);
			curl_close($curl);
		} catch (\Exception $e)
		{
			$this->myLogger($e->getCode());
			$this->myLogger($e->getMessage());
		}

		return json_decode($response)->access_token;
	}

	/**
	 * Returns an authorized API client.
	 * @return Google_Client the authorized client object
	 * @throws \Google_Exception
	 */
	protected function getGmailClient()
	{
		$client = new \Google_Client();
		$client->addScope("https://mail.google.com/");
		$client->addScope("https://www.googleapis.com/auth/gmail.compose");
		$client->addScope("https://www.googleapis.com/auth/gmail.modify");
		$client->addScope("https://www.googleapis.com/auth/gmail.readonly");
		if (file_exists($this->csFileName))
		{
			$client->setAuthConfig($this->csFileName);
		} else
		{
//  @todo THROW ERROR HERE - Client Id and Client Secret file with other needed settings for the Google_Client.
		}
		$client->setAccessType('offline');
		$client->setApprovalPrompt('force');

		// Load previously authorized credentials from a file.
		if (file_exists($this->creditialsFileName))
		{
			$accessToken = json_decode(file_get_contents($this->creditialsFileName), TRUE);
		} else
		{
//
//  @todo THROW ERROR HERE - ACCOUNT NEEDS TO BE REAUTHORIZED
//
//		    $authUrl = $client->createAuthUrl(); // THIS NEEDS TO BE OUTPUT SOME HOW
//	        SEE IF A AWS ALERT CAN BE CREATED TO ALERT USERS OF THE ISSUE.
//
//          Exchange authorization code for an access token.
//		    $accessToken = $client->authenticate('4/AABblIyBIy9VBVq60Ulfb2WHS8PodHtj6cN88i5Dzt3SCX7lRirmjrc');
//
//	        THE CODE FROM THE createAuthUrl needs to be put in a file and read by the line above.
//
//	        THE CODE BELOW MAY OR MAYNOT BE NEEDED
//          Store the credentials to disk.
//		    if(!file_exists(dirname($credentialsPath)))
//          {
//			    mkdir(dirname($credentialsPath), 0700, true);
//		    }
//		    file_put_contents($credentialsPath, json_encode($accessToken));
		}
		$client->setAccessToken($accessToken);

		// Refresh the token if it's expired.
		if ($client->isAccessTokenExpired())
		{
			file_put_contents($this->creditialsFileName, json_encode($client->getAccessToken($client->getRefreshToken())));
		}
		return $client;
	}

	/**
	 * Generates the APIMonitor notification emai.
	 *
	 */
	protected function generateEmail()
	{
// Get the API client and construct the service object.
		$client = $this->getGmailClient();
		$service = new \Google_Service_Gmail($client);

//	SEND EMAIL
		$strSubject = 'APIMonitor for EDEN API\'s on ' . date('M d, Y h:i:s A');
		$strRawMessage = "From: APIMonitor <APIMonitor@spotlightmart.com>\r\n";
		$strRawMessage .= "To: Scott <syacko@spotlightmart.com>\r\n";
		$strRawMessage .= 'Subject: =?utf-8?B?' . base64_encode($strSubject) . "?=\r\n";
		$strRawMessage .= "MIME-Version: 1.0\r\n";
		$strRawMessage .= "Content-Type: text/html; charset=utf-8\r\n";
		$strRawMessage .= 'Content-Transfer-Encoding: quoted-printable' . "\r\n\r\n";
		$strRawMessage .= 'APIMonitor version: ' . $this->version . '&nbsp;build: ' . $this->build . "\r\n\r\n<br/><br/>";
		foreach ($this->myAlerts as $msgLine)
		{
			$strRawMessage .= $msgLine . "\r\n\r\n<br/>";
		}

		//Users.messages->send - Requires -> Prepare the message in message/rfc822
		try
		{
			// The message needs to be encoded in Base64URL
			$mime = rtrim(strtr(base64_encode($strRawMessage), '+/', '-_'), '=');
			$msg = new \Google_Service_Gmail_Message();
			$msg->setRaw($mime);

			//The special value **me** can be used to indicate the authenticated user.
			$objSentMsg = $service->users_messages->send("me", $msg);

		} catch (Exception $e)
		{
			print($e->getMessage());
		}
	}

	/**
	 * Class Constructor without a parent
	 *
	 */
	public function __construct()
	{
		$envPath = '';
		if (array_key_exists('MAPP', getenv()))
		{
			$envPath = getenv('MAPP');
		} else if (array_key_exists('LAPP', getenv()))
		{
			$envPath = getenv('LAPP');
		} else
		{
			echo 'Missing MAPP or LAPP environment variable! System will not function without this being set.';
			throw new Exception('Missing MAPP or LAPP environment variable! System will not function without this being set.');
		};
		$dotEnv = new \Dotenv();
		$dotEnv->load($envPath . '/utilities/.env/', 'Utilities.env');
		$this->version = $_ENV['APP_VERSION'];
		$this->build = $_ENV['APP_BUILD'];
		$this->creditialsFileName = $envPath . '/utilities' . $_ENV['APP_CREDENTIALSFILE'];
		$this->csFileName = $envPath . '/utilities' . $_ENV['APP_CSFILE'];

		$this->accessTokenIssuer = $_ENV['API_ACCESSTOKEN_ISSUER'];
		$apiEnvornments = str_replace('`', '"', $_ENV['API_ENVORNMENTS']);
		$this->apiEnvornments = json_decode($apiEnvornments, TRUE);

		$apiServices = str_replace('`', '"', $_ENV['API_SERVICES']);
		$this->apiServices = json_decode($apiServices, TRUE);

		$apiPostFields = str_replace('`', '"', $_ENV['API_POSTFIELDS']);
		$this->apiPostFields = json_decode($apiPostFields, TRUE);

		$this->myLogger = new \Monolog\Logger(__DIR__ . $_ENV['LOG_PATH'] . 'APIMonitor.log');
		$this->myLogger->pushProcessor(new \Monolog\Processor\UidProcessor());
		$this->myLogger->pushHandler(new \Monolog\Handler\StreamHandler(__DIR__ . $_ENV['LOG_PATH'] . 'APIMonitor.log', $_ENV['LOG_LEVEL']));

		$this->myLogger->debug(__METHOD__);
	}
}