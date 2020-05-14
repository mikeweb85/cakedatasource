<?php

namespace MikeWeb\CakeSources\Command;

use MikeWeb\CakeSources\I18n\Currency;
use MikeWeb\CakeSources\Model\Table\CurrenciesTable;
use MikeWeb\CakeSources\Model\Table\ExchangeRatesTable;
use Cake\Console\Arguments;
use Cake\Console\Command;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\I18n\Number;
use Exception;


class CurrencySyncCommand extends Command {

    /**
     * @var CurrenciesTable
     */
    protected $Currencies;

    /**
     * @var ExchangeRatesTable
     */
    protected $ExchangeRates;

    /**
     * Initialize
     */
    public function initialize(): void {
        $this->loadModel('Currencies');
        $this->loadModel('ExchangeRates');
    }

    /**
     * @param ConsoleOptionParser $parser
     * @return ConsoleOptionParser
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser {
        $parser->addOptions([
            'no-alt-provider'   => ['help'=>'Allow alternate provider to ensure a description is alway populated.', 'boolean'=>true, 'default'=>false],
            'reset'             => ['help'=>'Reset all previously stored currency data', 'boolean'=>true, 'default'=>false],
            'dry-run'           => ['help'=>'Test output only', 'short'=>'n', 'boolean'=>true, 'default'=>false],
        ]);

        $providers = Currency::getProviders();
        $parser->addArgument('provider', ['help'=>'The exchange rate provider API to use.', 'required'=>false, 'choices'=>$providers]);

        return $parser;
    }

    /**
     * @param Arguments $args The command arguments.
     * @param ConsoleIo $io The console io
     * @return int
     * @throws Exception
     */
    public function execute(Arguments $args, ConsoleIo $io): int {
        $noOp = $args->getOption('dry-run');
        $verbose = $args->getOption('verbose');
        $quiet = $args->getOption('quiet');
        $altProviders = !( $args->getOption('no-alt-provider') );

        if ( $noOp || $verbose ) {
            $quiet = false;
        }

        $provider = $args->getArgument('provider') ?: 'default';

        if ( $args->getOption('reset')) {
            $confirm = ( $io->askChoice('This will erase all previously saved currency information. Continue?', ['Y', 'n'], 'n') === 'Y' );

            if ( $confirm === true ) {
                if ( $verbose ) {
                    $io->info( sprintf('Truncating tables [%s] and [%s].', $this->ExchangeRates->getTable(), $this->Currencies->getTable()) );
                }

                if ( !$noOp ) {
                    try {
                        $this->Currencies->truncate();

                    } catch (Exception $e) {
                        $io->error($e->getMessage());

                        return static::CODE_ERROR;
                    }
                }

                if ( !$quiet ) {
                    $io->info( sprintf('Truncating tables [%s] and [%s] completed.', $this->ExchangeRates->getTable(), $this->Currencies->getTable()) );
                }

            } else {
                if ( !$quiet ) {
                    $io->info('Reset option was not confirmed, stopping.');
                }

                return static::CODE_SUCCESS;
            }
        }

        $query = $this->Currencies->find()->enableAutoFields(false)->enableHydration(false);
        $result = $query->select(['count'=>$query->func()->count('id')], true)->firstOrFail();
        $totalCurrencies = (int)$result['count'];

        if ( $totalCurrencies === 0 ) {
            $this->Currencies->importCurrencies($provider, $altProviders);

            return static::CODE_SUCCESS;
        }

        $query = $this->Currencies->find()->where(['symbol !='=>Number::defaultCurrency(), 'enabled'=>true])->enableAutoFields(false)->enableHydration(false);
        $result = $query->select(['count'=>$query->func()->count('id')], true)->firstOrFail();
        $currencies = (int)$result['count'];

        if ( $currencies === 0 ) {
            if ( !$quiet ) {
                $io->warning( 'No additional currencies enabled for processing.');
            }

            return static::CODE_SUCCESS;
        }

        $result = $this->Currencies->updateExchangeRates($provider);

        if ( $result === false ) {
            if ( !$quiet ) {
                $io->error('The was an error processing the exchange rates. Check your logs to see the error.');
            }

            return static::CODE_ERROR;
        }

        if ( $verbose ) {
            $io->info( sprintf('Successfully updated %d records.', $result) );
        }

        return static::CODE_SUCCESS;
    }
}
