<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DownloadAlbumCommand extends DownloadCommandBase {

	protected function configure(): void
	{
		parent::configure();
		$this->setDescription($this->msg('command-download-album-desc'));
		$this->addOption('album', 'a', InputOption::VALUE_REQUIRED, $this->msg('option-album-desc'));
		$this->addOption('privacy', null, InputOption::VALUE_OPTIONAL, $this->msg('option-privacy-desc'));
	}

	/**
	 * @param PhpFlickr $flickr
	 * @param InputInterface $input
	 * @param int $page
	 * @return string[]
	 */
	protected function getPhotos(PhpFlickr $flickr, InputInterface $input, int $page): array
	{
		$albumVal = $input->getOption('album');
		$albumId = (int)$albumVal;
		if (!$albumId) {
			$this->io->error($this->msg('invalid-album-id', [$albumVal]));
		}
		return $flickr->photosets()->getPhotos(
			$albumId,
			null,
			null,
			500,
			$page,
			$input->getOption('privacy')
		);
	}

}
