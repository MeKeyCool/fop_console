<?php
/**
 * Copyright (c) Since 2020 Friends of Presta
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file docs/licenses/LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to infos@friendsofpresta.org so we can send you a copy immediately.
 *
 * @author    Friends of Presta <infos@friendsofpresta.org>
 * @copyright since 2020 Friends of Presta
 * @license   https://opensource.org/licenses/AFL-3.0  Academic Free License ("AFL") v. 3.0
 *
 */

declare(strict_types=1);

namespace FOP\Console\Commands\Configuration;

use DbQuery;
use FOP\Console\Command;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

final class ConfigurationExport extends Command
{
    private const PS_CONFIGURATIONS_FILE = 'ps_configurations.json';

    protected function configure(): void
    {
        $this->setName('fop:configuration:export')
            ->setDescription('Export configuration values (from ps_configuration table).')
            ->setHelp(
                'Dump configuration(s) to a json file.'
                . PHP_EOL . 'Exported file can later be used to import values, using configuration:import command.'
                . PHP_EOL . '<keys> are configuration names, "PS_LANG_DEFAULT" for example, multiple keys can be provided.'
                . PHP_EOL . '<keys> can also be mysql like values : use "PSGDPR_%" to export all configuration starting with "PSGDPR_" for example.'
                . PHP_EOL . PHP_EOL . 'This command is not multishop, neither multilang.'
                . PHP_EOL . PHP_EOL . 'Examples :'
                . PHP_EOL . 'dump one value : <info>./bin/console fop:configuration:export PS_COUNTRY_DEFAULT</info>'
                . PHP_EOL . 'dump multiples values : <info>./bin/console fop:configuration:export PS_COMBINATION_FEATURE_ACTIVE PS_CUSTOMIZATION_FEATURE_ACTIVE PS_FEATURE_FEATURE_ACTIVE
</info>'
                . PHP_EOL . 'dump multiples values using mysql "like" syntax : <info>./bin/console fop:configuration:export --file configuration_blocksocial.json BLOCKSOCIAL_%</info>'
            )
            ->addOption('file', null, InputOption::VALUE_REQUIRED, 'file to dump to', self::PS_CONFIGURATIONS_FILE)
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'overwrite existing file')
            ->addArgument('keys', InputArgument::IS_ARRAY | InputArgument::REQUIRED, 'configuration values to export');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $configuration_keys = (array) $input->getArgument('keys');
        $output_file = $input->getOption('file');
        $force_mode = $input->getOption('force');
        $fs = new Filesystem();

        if ($fs->exists($output_file)
            && !$force_mode
            && !$this->io->confirm(sprintf('Overwrite %s ? ', $output_file), false)
        ) {
            $this->io->comment($this->getName() . ' command aborted, ' . self::PS_CONFIGURATIONS_FILE . ' not touched.');

            return 0;
        }

        /** @var \PrestaShop\PrestaShop\Adapter\Configuration $configuration_service */
        $configuration_service = $this->getContainer()->get('prestashop.adapter.legacy.configuration');

        $to_export = [];
        foreach ($configuration_keys as $key) {
            // keys to query with a 'like' syntaxe from db
            if (false !== strpos($key, '%')) {
                $to_export = array_merge($to_export, $this->queryConfigurationsLike($key));
                continue;
            }

            if (!$configuration_service->has($key)) {
                $this->io->warning(sprintf("Configuration key not found '%s' : ignored.", $key));
                continue;
            }

            $to_export[$key] = $configuration_service->get($key);
        }

        $json_export = json_encode($to_export, JSON_PRETTY_PRINT);
        if (false === $json_export) {
            throw new RuntimeException('Failed to json encode configuration');
        }

        $fs = new Filesystem();
        $fs->dumpFile($output_file, $json_export);

        $this->io->success("configuration(s) dumped to file '{$output_file}'");

        return 1;
    }

    /**
     * @param string $key_like_term
     *
     * @return array<string, string> [name => value, ...]
     *
     * @throws \PrestaShopDatabaseException
     * @throws \PrestaShopException
     */
    private function queryConfigurationsLike(string $key_like_term): array
    {
        $query = new DbQuery(); // @todo sup Core
        $query->select('name, value')
        ->from('configuration')
        ->where(sprintf('name LIKE "%s"', $key_like_term));

//        $db = $this->getContainer()->get('prestashop.adapter.legacy_db'); // not on ps 1.7.5
        $db = \Db::getInstance();
        $r = $db->executeS($query);
        if (false === $r) {
            dump($query->build(), $db->getMsgError());
            throw new \Exception('sql query error : see dump above.');
        }

        return array_combine(array_column($r, 'name'), array_column($r, 'value'));
    }
}
