<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

class DownloadUserCommand extends DownloadCommandBase {

	protected function configure(): void
	{
		parent::configure();
		$this->setDescription($this->msg('command-download-user-desc'));

		$this->addOption('userid', null, InputOption::VALUE_OPTIONAL, $this->msg('option-userid-desc'), 'me');
		$this->addOption('min-upload-date', null, InputOption::VALUE_OPTIONAL, $this->msg('option-min-upload-date-desc'));
		$this->addOption('max-upload-date', null, InputOption::VALUE_OPTIONAL, $this->msg('option-min-date-taken-desc'));
		$this->addOption('min-date-taken', null, InputOption::VALUE_OPTIONAL, $this->msg('option-min-date-taken-desc'));
		$this->addOption('max-date-taken', null, InputOption::VALUE_OPTIONAL, $this->msg('option-max-date-taken-desc'));
		$this->addOption('privacy', null, InputOption::VALUE_OPTIONAL, $this->msg('option-privacy-desc'));

		$this->addOption('album', null, InputOption::VALUE_OPTIONAL, $this->msg('option-album-desc'));
	}

	/**
	 * @param PhpFlickr $flickr
	 * @param InputInterface $input
	 * @param int $page
	 * @return string[]
	 */
	protected function getPhotos(PhpFlickr $flickr, InputInterface $input, int $page): array
	{
		$minUploadDate = $input->getOption('min-upload-date')
			? strtotime($input->getOption('min-upload-date'))
			: null;
		$maxUploadDate = $input->getOption('max-upload-date')
			? strtotime($input->getOption('max-upload-date'))
			: null;
		$minDateTaken = $input->getOption('min-date-taken')
			? date('Y-m-d H:i:s', strtotime($input->getOption('min-date-taken')))
			: null;
		$maxDateTaken = $input->getOption('max-date-taken')
			? date('Y-m-d H:i:s', strtotime($input->getOption('max-date-taken')))
			: null;
		$privacy = $input->getOption('privacy');
		$perPage = 500;

		return $flickr->people()->getPhotos(
			$input->getOption('userid'),
			null,
			$minUploadDate,
			$maxUploadDate,
			$minDateTaken,
			$maxDateTaken,
			null,
			$privacy,
			null,
			$perPage,
			$page
		);
	}

}