<?php
namespace Samwilson\FlickrUpDown;

class Up extends UpDown {


	public function upload( $source ) {

		// Check file.
		if (!file_exists($source) && !is_dir($source)) {
			echo "$source is not a file or directory\n";
			exit(1);
		}

		// Check status?
		//$uploadStatus = $this->request('flickr.people.getUploadStatus');

		// Do upload.
		$realSource = realpath($source);
		echo "Uploading $realSource\n";
		$this->flickrService->requestUpload($realSource);

	}

}
