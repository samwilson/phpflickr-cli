<?php
namespace Samwilson\FlickrUpDown;


use OAuth\Common\Consumer\Credentials;
use OAuth\Common\Storage\Exception\TokenNotFoundException;
use OAuth\Common\Storage\Memory;
use OAuth\Common\Token\TokenInterface;
use OAuth\OAuth1\Token\StdOAuth1Token;
use OAuth\ServiceFactory;

class UpDown {

	/** @var FlickrService $flickrService */
	protected $flickrService;

	/** @var string */
	protected $dataDir;

	public function __construct( $apiKey, $apiSecret, $dataDir ) {
		echo "Please report bugs at https://github.com/samwilson/flickr-updown\n";
		$this->dataDir = realpath( $dataDir );
		$this->apiKey = $apiKey;
		$this->apiSecret = $apiSecret;
		$this->loadFlickrService();
		$this->init();
	}

	protected function loadFlickrService( $baseApiUrl = null ) {
		$credentials = new Credentials( $this->apiKey, $this->apiSecret, 'oob' );
		$storage = new Memory();
		$accessToken = $this->getStoredCredentials();
		if ( $accessToken instanceof TokenInterface ) {
			$storage->storeAccessToken( 'Flickr', $accessToken );
		} else {
			echo "Unable to load access token\n";
		}
		$serviceFactory = new ServiceFactory();
		$serviceFactory->registerService( 'Flickr', FlickrService::class );
		$this->flickrService = $serviceFactory->createService( 'Flickr', $credentials, $storage );
	}

	protected function getCredentialsFilename() {
		return $this->dataDir . '/credentials.txt';
	}

	/**
	 * @return bool|StdOAuth1Token
	 */
	public function getStoredCredentials() {
		if ( !file_exists( $this->getCredentialsFilename() ) ) {
			return false;
		}
		$string = file_get_contents( $this->getCredentialsFilename() );
		return unserialize( $string );
	}

	public function setStoredCredentials( StdOAuth1Token $credentials ) {
		file_put_contents( $this->getCredentialsFilename(), serialize( $credentials ) );
	}

	public function authorized() {
		if ( !file_exists( $this->getCredentialsFilename() ) ) {
			return false;
		}
		$result = $this->request( 'flickr.test.login' );
		if ( !isset( $result->stat ) || !$result->stat === 'ok' ) {
			return false;
		}

		return true;
	}

	public function request( $method, $parameters = array() ) {
		$response = $this->flickrService->requestJson( $method, 'POST', $parameters );
		return json_decode( $response );
	}

	public function init() {
		echo "Checking authorisation\n";
		// See if we need to authorize.
		if ( !$this->authorized() ) {
			// Fetch the request-token.
			$requestToken = $this->flickrService->requestRequestToken();
			$url = $this->flickrService->getAuthorizationUri( [
				'oauth_token' => $requestToken->getRequestToken(),
				'perms' => 'read',
			] );
			echo "Go to this URL to authorize this application\n$url\n";
			// Flickr says, at this point:
			// "You have successfully authorized the application Flickr Latex to use your credentials.
			// You should now type this code into the application:"
			echo "Paste the 9-digit code (with or without hyphens) here: ";
			$verifier = preg_replace( '/[^0-9]/', '', fgets( fopen( 'php://stdin', 'r' ) ) );

			// Fetch the access-token, for saving to data/token.json
			$accessToken =
				$this->flickrService->requestAccessToken( $requestToken, $verifier,
					$requestToken->getAccessTokenSecret() );
			$this->setStoredCredentials( $accessToken );
			$this->loadFlickrService();
		}

		if ( !$this->authorized() ) {
			echo "Unable to authorize. :-(\n";
			exit( 1 );
		}

	}
}
