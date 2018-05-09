<?php

namespace RebelCode\EddBookings\Sessions\Module;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Dhii\Config\ConfigFactoryInterface;
use Dhii\Data\Container\ContainerFactoryInterface;
use Dhii\Event\EventFactoryInterface;
use Dhii\Exception\InternalException;
use Dhii\Factory\GenericCallbackFactory;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Util\String\StringableInterface as Stringable;
use Psr\Container\ContainerInterface;
use Psr\EventManager\EventManagerInterface;
use RebelCode\EddBookings\Sessions\Generator\DailyRepeatingRule;
use RebelCode\EddBookings\Sessions\Generator\YearlyRepeatingRule;
use RebelCode\Modular\Module\AbstractBaseModule;

class EddBkSessionGeneratorModule extends AbstractBaseModule
{
    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param string|Stringable         $key                  The module key.
     * @param string[]|Stringable[]     $dependencies         The module dependencies.
     * @param ConfigFactoryInterface    $configFactory        The config factory.
     * @param ContainerFactoryInterface $containerFactory     The container factory.
     * @param ContainerFactoryInterface $compContainerFactory The composite container factory.
     * @param EventManagerInterface     $eventManager         The event manager.
     * @param EventFactoryInterface     $eventFactory         The event factory.
     */
    public function __construct(
        $key,
        $dependencies,
        $configFactory,
        $containerFactory,
        $compContainerFactory,
        $eventManager,
        $eventFactory
    ) {
        $this->_initModule($key, $dependencies, $configFactory, $containerFactory, $compContainerFactory);
        $this->_initModuleEvents($eventManager, $eventFactory);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     *
     * @throws InternalException If failed to load and read the config file.
     */
    public function setup()
    {
        return $this->_setupContainer(
            $this->_loadPhpConfigFile(RCMOD_EDDBK_SESSION_GENERATOR_CONFIG_FILE),
            [
                'eddbk_generate_sessions_handler' => function (ContainerInterface $c) {
                    return new GenerateSessionsHandler(
                        $c->get('session_rules_select_rm'),
                        $c->get('sessions_insert_rm'),
                        $c->get('sessions_delete_rm'),
                        $c->get('eddbk_session_rule_factory'),
                        $c->get('sql_expression_builder')
                    );
                },
                'eddbk_session_rule_factory'      => function (ContainerInterface $c) {
                    return new GenericCallbackFactory(function ($config) use ($c) {
                        $start             = (int) $this->_containerGet($config, 'start');
                        $end               = (int) $this->_containerGet($config, 'end');
                        $repeat            = (bool) $this->_containerGet($config, 'repeat');
                        $repeatUnit        = $this->_containerGet($config, 'repeat_unit');
                        $repeatUnit        = strtolower($repeatUnit);
                        $repeatPeriod      = (int) $this->_containerGet($config, 'repeat_period');
                        $repeatUntil       = $this->_containerGet($config, 'repeat_until');
                        $repeatUntilPeriod = $this->_containerGet($config, 'repeat_until_period');
                        $repeatUntilPeriod = sprintf('+%1$d %2$s', $repeatUntilPeriod, $repeatUnit);
                        $repeatUntilDate   = $this->_containerGet($config, 'repeat_until_date');
                        $excludeDates      = $this->_containerGet($config, 'exclude_dates');

                        $repeatEnd = ($repeatUntil === 'period')
                            ? Carbon::createFromTimestampUTC($start)->modify($repeatUntilPeriod)
                            : $repeatUntilDate;

                        if (!$repeat) {
                            return new DailyRepeatingRule(
                                $c->get('eddbk_period_factory'),
                                $start,
                                $end,
                                1,
                                $end,
                                $excludeDates
                            );
                        }

                        switch ($repeatUnit) {
                            case 'years':
                                return new YearlyRepeatingRule(
                                    $c->get('eddbk_period_factory'),
                                    $start,
                                    $end,
                                    $repeatPeriod,
                                    $repeatEnd,
                                    $excludeDates
                                );
                        }
                    });
                },
            ]
        );
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function run(ContainerInterface $c = null)
    {
        $this->_attach('eddbk_generate_sessions', $c->get('eddbk_generate_sessions_handler'));
    }
}
