<?php
namespace Samwilson\FlickrUpDown;

use OAuth\Common\Http\Uri\Uri;
use OAuth\OAuth1\Service\Flickr;

class FlickrService extends Flickr {

	public function service()
	{
		return 'Flickr';
	}

	public function requestUpload( $photoFilename )
	{
		$uploadUrl = new Uri('https://up.flickr.com/services/upload/');

		$body = [
			'photo' => file_get_contents($photoFilename),
			'title' => basename($photoFilename),
			'description' => '',
			'tags' => '"Uploaded by samwilson/flickr-updown"',
			'is_public' => 0,
			'is_friend' => 0,
			'is_family' => 0,
		];

		//$resp = $this->flickrService->request( $postUrl, 'POST', $req);

		//$token = $this->getStoredCredentials();
		$method = '';
		$authorizationHeader = array(
			'Authorization' => $this->buildAuthorizationHeaderForAPIRequest(
				$method,
				$uploadUrl,
				$this->storage->retrieveAccessToken(),
				$body
			)
		);

		return $this->httpClient->retrieveResponse($uploadUrl, $body, $authorizationHeader, $method);

	}

}