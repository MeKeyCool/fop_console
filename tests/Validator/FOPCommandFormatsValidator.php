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
 */
declare(strict_types=1);

namespace FOP\Console\Tests\Validator;

class FOPCommandFormatsValidator
{
    /**
     * @var array Validation messages
     */
    private $validationMessages = [];

    /**
     * @param string $commandDomain domain, e.g. Module
     * @param string $commandClassName php class name, e.g. ModuleHooks
     * @param string $commandName symfony command name, e.g. fop:modules:hooks
     * @param string $commandServiceName service name defined in config/services.yml. e.g. fop.console.modules.module_hooks.command
     *
     * @return bool
     */
    public function validate(
        string $commandDomain,
        string $commandClassName,
        string $commandName,
        string $commandServiceName
    ): bool {
        if (empty($commandDomain)) {
            $this->addValidationMessage(
                $commandClassName,
                "Domain can't be empty."
            );

            return false;
        }

        if (strpos($commandClassName, $commandDomain) !== 0) {
            $this->addValidationMessage(
                $commandClassName,
                "Domain $commandDomain must be included in command class name."
            );

            return false;
        }

        $commandAction = str_replace($commandDomain, '', $commandClassName);

        if (empty($commandAction)) {
            $this->addValidationMessage(
                $commandClassName,
                "Action can't be empty."
            );

            return false;
        }

        $commandDomain = ucfirst($commandDomain);
        $commandAction = ucfirst($commandAction);

        if (!$this->isCommandNameValid($commandClassName, $commandName, $commandDomain, $commandAction)) {
            return false;
        }

        if (!$this->isCommandServiceNameValid($commandClassName, $commandServiceName, $commandDomain, $commandAction)) {
            return false;
        }

        return true;
    }

    private function isCommandNameValid($commandClassName, $commandName, $commandDomain, $commandAction)
    {
        // Command name pattern = fop:command-domain:command[:-]action
        $expectedCommandNamePattern = strtolower(
            'fop:'
            . implode('-', $this->getWords($commandDomain))
            . ':'
            . implode('[:-]', $this->getWords($commandAction))
        );

        if (!preg_match('/^' . $expectedCommandNamePattern . '$/', $commandName)) {
            $this->addValidationMessage(
                $commandClassName,
                'Wrong format for command class name.' . PHP_EOL
                    . "Expected = $expectedCommandNamePattern" . PHP_EOL
                    . "Actual = $commandName"
            );

            return false;
        }

        return true;
    }

    private function isCommandServiceNameValid($commandClassName, $commandServiceName, $commandDomain, $commandAction)
    {
        // Command service name pattern = fop.console.command_domain.command[\._]action.command
        $expectedCommandServiceNamePattern = strtolower(
            'fop.console.'
            . implode('_', $this->getWords($commandDomain))
            . '.'
            . implode('[\._]', $this->getWords($commandAction))
            . '.command'
        );

        if (!preg_match('/^' . $expectedCommandServiceNamePattern . '$/', $commandServiceName)) {
            $this->addValidationMessage(
                $commandClassName,
                'Wrong format for command service name.' . PHP_EOL
                    . "Expected = $expectedCommandServiceNamePattern" . PHP_EOL
                    . "Actual = $commandServiceName"
            );

            return false;
        }

        return true;
    }

    private function getWords($subject)
    {
        return preg_split('/(?=[A-Z])/', $subject, -1, PREG_SPLIT_NO_EMPTY);
    }

    private function addValidationMessage(string $command, string $message)
    {
        $this->validationMessages[] = "[$command] => " . $message;
    }

    public function getValidationMessages()
    {
        return $this->validationMessages;
    }
}
