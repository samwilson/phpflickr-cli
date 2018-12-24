<?php

namespace Samwilson\PhpFlickrCli\Command;

use OAuth\OAuth1\Token\StdOAuth1Token;
use Samwilson\PhpFlickr\PhpFlickr;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

abstract class CommandBase extends Command
{

    /** @var SymfonyStyle */
    protected $io;

    /**
     * Add the standard `--config` option that is common to all commands.
     */
    protected function configure()
    {
        parent::configure();
        $desc = "Path to the config file.\n"
            . "Can also be set with the FLICKRCLI_CONFIG environment variable.\n"
            . "Will default to current directory.";
        $default = dirname(__DIR__, 2) .'/config.yml';
        $this->addOption('config', 'c', InputOption::VALUE_OPTIONAL, $desc, $default);
    }

    /**
     * @param InputInterface $input
     * @return string[][]
     */
    protected function getConfig(InputInterface $input): array
    {
        $configPath = $input->getOption('config');
        $this->io->block("Using configuration file: $configPath");
        return Yaml::parseFile($configPath);
    }

    /**
     * @param InputInterface $input
     * @param string[] $config
     */
    protected function setConfig(InputInterface $input, $config)
    {
        $configPath = $input->getOption('config');
        file_put_contents($configPath, Yaml::dump($config));
        $this->io->success("Saved configuration file: $configPath");
    }

    /**
     *
     */
    protected function getFlickr(InputInterface $input)
    {
        $config = $this->getConfig($input);
        $flickr = new PhpFlickr($config['consumer_key'], $config['consumer_secret']);
        $accessToken = new StdOAuth1Token();
        $accessToken->setAccessToken($config['access_key']);
        $accessToken->setAccessTokenSecret($config['access_secret']);
        // A storage object has already been created at this point because we called testEcho above.
        $flickr->getOauthTokenStorage()->storeAccessToken('Flickr', $accessToken);
        return $flickr;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->io->title('PhpFlickr CLI');
    }
}
