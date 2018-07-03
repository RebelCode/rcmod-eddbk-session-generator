<?php

namespace RebelCode\EddBookings\Sessions\Module;

use AppendIterator;
use Carbon\Carbon;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Factory\Exception\CreateCouldNotMakeExceptionCapableTrait;
use Dhii\Factory\FactoryInterface;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use RebelCode\EddBookings\Sessions\Generator\DailyRepeatingRule;
use RebelCode\EddBookings\Sessions\Generator\MonthlyRepeatingRule;
use RebelCode\EddBookings\Sessions\Generator\WeeklyRepeatingRule;
use RebelCode\EddBookings\Sessions\Generator\YearlyRepeatingRule;
use RebelCode\EddBookings\Sessions\Time\PeriodFactoryAwareTrait;
use RebelCode\EddBookings\Sessions\Time\PeriodFactoryInterface;

/**
 * Factory implementation that creates session generation rules based on their repetition unit.
 *
 * @since [*next-version*]
 */
class SessionRuleFactory implements FactoryInterface
{
    /* @since [*next-version*] */
    use PeriodFactoryAwareTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateCouldNotMakeExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param PeriodFactoryInterface $periodFactory The factory for creating periods.
     */
    public function __construct(PeriodFactoryInterface $periodFactory)
    {
        $this->_setPeriodFactory($periodFactory);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function make($config = null)
    {
        $start  = (int) $this->_containerGet($config, 'start');
        $end    = (int) $this->_containerGet($config, 'end');
        $allDay = (bool) $this->_containerGet($config, 'all_day');

        if ($allDay) {
            $start = Carbon::createFromTimestampUTC($start)->setTime(0, 0, 0)->getTimestamp();
            $end   = Carbon::createFromTimestampUTC($end)->setTime(0, 0, 0)->addDay(1)->getTimestamp();
        }

        $repeat       = (bool) $this->_containerGet($config, 'repeat');
        $excludeDates = $this->_containerGet($config, 'exclude_dates');
        $excludeDates = array_filter(explode(',', $excludeDates));

        if (!$repeat) {
            return new DailyRepeatingRule(
                $this->periodFactory,
                $start,
                $end,
                0,
                $end,
                $excludeDates
            );
        }

        $repeatUnit           = $this->_containerGet($config, 'repeat_unit');
        $repeatUnit           = strtolower($repeatUnit);
        $repeatPeriod         = (int) $this->_containerGet($config, 'repeat_period');
        $repeatUntil          = $this->_containerGet($config, 'repeat_until');
        $repeatUntilPeriod    = (int) $this->_containerGet($config, 'repeat_until_period');
        $repeatUntilPeriod    = ($repeatUntilPeriod < 1) ? 1 : $repeatUntilPeriod;
        $repeatUntilPeriodStr = sprintf('+%1$d %2$s', $repeatUntilPeriod - 1, $repeatUnit);
        $repeatUntilDate      = $this->_containerGet($config, 'repeat_until_date');
        // Calculate the end of repetition datetime
        $repeatEndDt = ($repeatUntil === 'period')
            ? Carbon::createFromTimestampUTC($start)->modify($repeatUntilPeriodStr)
            : Carbon::createFromTimestampUTC($repeatUntilDate)->addDay(1);
        $repeatEnd = $repeatEndDt->getTimestamp();

        switch ($repeatUnit) {
            case 'days':
                return new DailyRepeatingRule(
                    $this->periodFactory,
                    $start,
                    $end,
                    $repeatPeriod,
                    $repeatEnd,
                    $excludeDates
                );

            case 'weeks':
                $dotwNames = $this->_containerGet($config, 'repeat_weekly_on');
                $dotwNames = array_filter(explode(',', $dotwNames));
                $startTime = Carbon::createFromTimestampUTC($start)->toTimeString();
                $endTime   = Carbon::createFromTimestampUTC($end)->toTimeString();
                $rules     = new AppendIterator();

                foreach ($dotwNames as $_dotwName) {
                    $_dotwStartStr = sprintf('%1$s %2$s', $_dotwName, $startTime);
                    $_dotwEndStr   = sprintf('%1$s %2$s', $_dotwName, $endTime);
                    $_dotwStart    = strtotime($_dotwStartStr, $start);
                    $_dotwEnd      = strtotime($_dotwEndStr, $end);

                    $rules->append(
                        new WeeklyRepeatingRule(
                            $this->periodFactory,
                            $_dotwStart,
                            $_dotwEnd,
                            $repeatPeriod,
                            $repeatEnd,
                            $excludeDates
                        )
                    );
                }

                return $rules;

            case 'months':
                $monthRepeatMode = $this->_containerGet($config, 'repeat_monthly_on');

                return new MonthlyRepeatingRule(
                    $this->periodFactory,
                    $start,
                    $end,
                    $repeatPeriod,
                    $repeatEnd,
                    $monthRepeatMode,
                    $excludeDates
                );

            case 'years':
                return new YearlyRepeatingRule(
                    $this->periodFactory,
                    $start,
                    $end,
                    $repeatPeriod,
                    $repeatEnd,
                    $excludeDates
                );

            default:
                throw $this->_createCouldNotMakeException(
                    $this->__('The rule config has an invalid repeat unit: "%s"', [$repeatUnit]),
                    null,
                    null,
                    $this,
                    $config
                );
        }
    }
}
