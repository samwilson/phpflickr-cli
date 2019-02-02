<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli\Command;

use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickr\Util;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DuplicatesCommand extends CommandBase
{

    protected function configure(): void
    {
        parent::configure();
        $this->setDescription($this->msg('command-duplicates-search-desc'));
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);
        $flickr = $this->getFlickr($input);
        $this->io->block($this->msg('searching-for-duplicates'));

        // Get all photos.
        $page = 1;
        do {
            $photos = $flickr->people()->getPhotos(
                'me',
                null,
                null,
                null,
                null,
                null,
                null,
                null,
                'o_url, tags',
                500,
                $page
            );
            if (0 === $photos['pages']) {
                $this->io->warning($this->msg('no-photos-found'));
                return 0;
            }
            $this->io->block($this->msg('page-i-of-n', [$page, $photos['pages']]));
            foreach ($photos['photo'] as $photo) {
                $this->processPhoto($flickr, $photo);
            }
            $page++;
        } while ($photos['page'] !== $photos['pages']);
        return 0;
    }

    /**
     * @param PhpFlickr $flickr
     * @param string[][] $photo
     */
    protected function processPhoto(PhpFlickr $flickr, array $photo): void
    {
        $tags = explode(' ', $photo['tags']);
        // Go through the plain tags and detect the ones we're interested by string comparison rather than by
        // querying the actual photo metadata, to save ourselves an extra request.
        $foundChecksum = false;
        foreach ($tags as $tag) {
            if ('checksum:' !== substr($tag, 0, strlen('checksum:'))) {
                continue;
            }
            $foundChecksum = true;
            // If we've got a checksum tag, query for others wit hthe same one.
            $search = $flickr->photos()->search(['machine_tags' => $tag]);
            if ((int)$search['total'] < 2) {
                continue;
            }
            $url = "https://www.flickr.com/photos/tags/$tag";
            $this->io->writeln( "Duplicate found: $url" );

            $prev = null;
            foreach ($search['photo'] as $searchResult) {
                $this->io->writeln('Getting info about '.$searchResult['id']);
                $photoInfo = $flickr->photos()->getInfo($searchResult['id']);
                if (!$prev) {
                    $prev = $photoInfo;
                } else {
                    $this->displayDiff($prev, $photoInfo);
                }
            }
            $deleteNum = $this->io->choice($this->msg('ask-delete-duplicate'), [1, 2]);
            $this->io->writeln("Deleting $deleteNum");
        }
        if (!$foundChecksum) {
            $shortUrl = 'https://flic.kr/p/'.Util::base58encode($photo['id']);
            $this->io->writeln($this->msg('no-checksum', [$shortUrl]));
        }
    }

    protected function displayDiff($photo1, $photo2): void
    {
        $shortUrl1 = 'https://flic.kr/p/'.Util::base58encode($photo1['id']);
        $shortUrl2 = 'https://flic.kr/p/'.Util::base58encode($photo2['id']);
        $headers = ['Field', "1: $shortUrl1", "2: $shortUrl2"];
        $rows = [];
        if ($photo1['title'] !== $photo2['title']) {
            $rows[] = ['Title', $photo1['title'], $photo2['title']];
        }
        if ($photo1['description'] !== $photo2['description']) {
            $rows[] = ['Description', $photo1['description'], $photo2['description']];
        }
        if ($photo1['dates']['taken'] !== $photo2['dates']['taken']
            || $photo1['dates']['takengranularity'] !== $photo2['dates']['takengranularity']
            ) {
            $rows[] = [
                'Date taken',
                $photo1['dates']['taken'].' ('.$photo1['dates']['takengranularity'].')',
                $photo2['dates']['taken'].' ('.$photo2['dates']['takengranularity'].')',
            ];
        }
        $this->io->table($headers, $rows);
    }

    protected function arrayRecursiveDiff($array1, $array2): array
    {
        $aReturn = array();
        foreach ($array1 as $mKey => $mValue) {
            if (array_key_exists($mKey, $array2)) {
                if (is_array($mValue)) {
                    $aRecursiveDiff = $this->arrayRecursiveDiff($mValue, $array2[$mKey]);
                    if (count($aRecursiveDiff)) { $aReturn[$mKey] = $aRecursiveDiff; }
                } else {
                    if ($mValue != $array2[$mKey]) {
                        $aReturn[$mKey] = $mValue;
                    }
                }
            } else {
                $aReturn[$mKey] = $mValue;
            }
        }
        return $aReturn;
    }
}
