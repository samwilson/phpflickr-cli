<?php

namespace Samwilson\FlickrUpDown;

use Symfony\Component\Yaml\Yaml;

class Down extends UpDown {

	public function downloadAll() {
		echo "Downloading all photos\n";
		$notInSet = $this->request( 'flickr.photos.getNotInSet', [] );
		foreach ( $notInSet->photos->photo as $photo ) {
			$this->downloadOne( $photo->id );
		}
	}

	public function downloadOne( $photoId ) {
		$photoInfo = $this->request( 'flickr.photos.getInfo', array( 'photo_id' => $photoId ) );
		echo "  " . $photoInfo->photo->id . " -- " . $photoInfo->photo->title->_content;
		$photoDatum = array(
			'id' => $photoId,
			'user_id' => $photoInfo->photo->owner->nsid,
			'granularity' => $photoInfo->photo->dates->takengranularity,
			'date_taken' => $photoInfo->photo->dates->taken,
			'title' => $photoInfo->photo->title->_content,
			'description' => $photoInfo->photo->description->_content,
			'visibility' => (array)$photoInfo->photo->visibility,
		);
		if ( isset( $photoInfo->photo->location ) ) {
			$photoDatum['location'] = (array)$photoInfo->photo->location;
		}
		foreach ( $photoInfo->photo->tags->tag as $tag ) {
			$photoDatum['tags'][] = (array)$tag;
		}

		$username = $photoInfo->photo->owner->username;
		$localDir = $this->dataDir . "/$username/$photoId";
		if ( !is_dir( $localDir ) ) {
			mkdir( $localDir, 0755, true );
		}

		// Download files.
		$farm = $photoInfo->photo->farm;
		$server = $photoInfo->photo->server;

		// 1. Original.
		if ( isset( $photoInfo->photo->originalsecret ) ) {
			$origScrt = $photoInfo->photo->originalsecret;
			$origFmt = $photoInfo->photo->originalformat;
			$origUrl =
				'https://farm' . $farm . '.staticflickr.com/' . $server . '/' . $photoId . '_' .
				$origScrt . '_o.' . $origFmt;
			if ( !file_exists( $localDir . '/original.' . $origFmt ) ) {
				echo " -- original ";
				$filename = $localDir . '/original.' . $origFmt;
				file_put_contents( $filename, file_get_contents( $origUrl ) );
			}
		}

		// 2. Medium. https://farm{farm-id}.staticflickr.com/{server-id}/{id}_{secret}_[mstzb].jpg
		$scrt = $photoInfo->photo->secret;
		$medUrl =
			'https://farm' . $farm . '.staticflickr.com/' . $server . '/' . $photoId . '_' . $scrt .
			'_c.jpg';
		if ( !file_exists( $localDir . '/medium.jpg' ) ) {
			echo " -- medium ";
			file_put_contents( $localDir . '/medium.jpg', file_get_contents( $medUrl ) );
		}

		// 3. Metadata.
		echo " -- metadata";
		$metadata = Yaml::dump( $photoDatum );
		file_put_contents( $localDir . '/metadata.yml', $metadata );
		echo " -- done.\n";
	}
}