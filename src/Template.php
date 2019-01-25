<?php declare(strict_types = 1);

namespace Samwilson\PhpFlickrCli;

use Samwilson\PhpFlickr\PhpFlickr;
use Samwilson\PhpFlickr\Util;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;
use Twig_Environment;
use Twig_Extension_Core;

class Template {

    /** @var string */
    protected $templateName;

    /** @var string[][] */
    protected $photos;

    /** @var PhpFlickr */
    protected $flickr;
    /**
     * The full filesystem directory into which everything will be saved. No trailing slash.
     *
     * @var string
     */
    protected $dest;

    /** @var callable */
    protected $callback;

    /** @var string */
    protected $templateDirectory;

    public function __construct(string $templateName, string $destDir, PhpFlickr $flickr)
    {
        $this->flickr = $flickr;

        // The provided template name might be a template directory, either relative to the current working directory or
        // an absolute path. Only if it's neither of these, do we assume it's one of the included templates.
        if (is_dir($templateName)) {
            $this->templateName = basename($templateName);
            $this->templateDirectory = $templateName;
        } else {
            $this->templateName = $templateName;
            $this->templateDirectory = dirname(__DIR__)."/tpl/".$this->templateName;
        }

        // Set up Twig. If the template doesn't exist, FilesystemLoader will complain for us.
        $this->twig = new Environment(new FilesystemLoader($this->templateDirectory));
        $this->dest = rtrim($destDir, '/');
        if (!is_dir($this->dest)) {
            mkdir($this->dest, 0700, true);
        }
        $this->twig->addFilter(new TwigFilter('md5', static function ($in) {
            return md5($in);
        }));
        $this->twig->addFilter(new TwigFilter('substr', static function ($string, $start, $length) {
            return substr($string, $start, $length);
        }));
        $this->twig->getExtension(Twig_Extension_Core::class)->setEscaper('tex', [$this, 'texEsc']);
        $this->twig->addFunction(new TwigFunction('flickrDate', [$this, 'flickrDate']));
    }

    /**
     * Escape a string for use in LaTeX.
     *
     * @param Twig_Environment $env The Twig environment.
     * @param string $string The string to escape.
     * @return string
     */
    public function texEsc(Twig_Environment $env, string $string): string
    {
        $in = strip_tags($string);
        $pat = array('/\\\(\s)/', '/\\\(\S)/', '/&/', '/%/', '/\$/', '/>>/', '/_/', '/\^/', '/#/', '/"(\s)/', '/"(\S)/');
        $rep = array('\textbackslash\ $1', '\textbackslash $1', '\&', '\%', '\textdollar ', '\textgreater\textgreater ', '\_', '\^', '\#', '\textquotedbl\ $1', '\textquotedbl $1');
        return preg_replace($pat, $rep, $in);
    }

    /**
     * A Twig function to format a date according to Flickr's rules about granularity.
     *
     * @param string $time
     * @param int $granularity
     * @return bool|string The formatted date, or false if it couldn't be determined.
     */
    public static function flickrDate(string $time, int $granularity): string
    {
        $granularities = array(
            '0' => 'Y M j g:i a',
            '4' => 'Y M',
            '6' => 'Y',
            '8' => '\c.~Y',
        );
        return date($granularities[$granularity], strtotime($time));
    }

    /**
     * Get a list of the names of all included templates.
     *
     * @return string[]
     */
    public static function getTemplateNames(): array
    {
        return preg_grep('/^[^.]/', scandir(dirname(__DIR__).'/tpl'));
    }

    /**
     * Set a callback function to be called when iterating over each photo.
     *
     * @param callable $callback
     */
    public function setPerPhotoCallback(callable $callback): void
    {
        $this->callback = $callback;
    }

    /**
     * Render the given photos out to the various files of this template.
     *
     * @param mixed[][] $photos
     */
    public function render(array $photos): void
    {
        $allPhotos = [];
        $ext = $this->getTemplateFileExtension('photo');
        foreach ($photos as $photo) {
            // Metadata file.
            if ($ext) {
                $photo['ext'] = $ext;
                $photoPath = $this->twig->render('path.twig', $photo);
                $photoOutput = $this->twig->render("photo.$ext.twig", $photo);
                $outputFile = trim($this->dest . '/' . ltrim($photoPath, '/ '));
                if (!is_dir(dirname($outputFile))) {
                    mkdir(dirname($outputFile), 0700, true);
                }
                file_put_contents($outputFile, $photoOutput);
            }

            // Original photo file.
            $photo['ext'] = $photo['originalformat'];
            $photoPath = trim($this->twig->render('path.twig', $photo), " \t\n\r\0\x0B/");
            $originalUrl = $this->flickr->buildPhotoURL($photo, 'original');
            $photo['shorturl'] = 'https://flic.kr/p/'.Util::base58encode($photo['id']);
            if (!file_exists("$this->dest/$photoPath")) {
                copy($originalUrl, "$this->dest/$photoPath");
            }

            $allPhotos[$photoPath] = $photo;

            call_user_func_array($this->callback, []);
        }

        // Write out all photos.
        ksort($allPhotos);
        $ext = $this->getTemplateFileExtension('photos');
        $photosOutput = $this->twig->render("photos.$ext.twig", ['photos' => $allPhotos]);
        $outputFile = "$this->dest/photos.$ext";
        file_put_contents($outputFile, $photosOutput);

    }

    /**
     * Get the target file extension for a given template name.
     *
     * @param string $templateName The base name of the template (e.g. 'photo' or 'photos').
     * @return string
     */
    protected function getTemplateFileExtension(string $templateName): string
    {
        foreach (glob("$this->templateDirectory/$templateName.*.twig") as $file) {
            preg_match('/\.(.*)\.twig/', basename($file), $matches);
            return $matches[1];
        }
        return '';
    }

}
