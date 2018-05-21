<?php

namespace RebelCode\EddBookings\Sessions\Module;

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
        $excludeDates = explode(',', $excludeDates);

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

        $repeatUnit        = $this->_containerGet($config, 'repeat_unit');
        $repeatUnit        = strtolower($repeatUnit);
        $repeatPeriod      = (int) $this->_containerGet($config, 'repeat_period');
        $repeatUntil       = $this->_containerGet($config, 'repeat_until');
        $repeatUntilPeriod = $this->_containerGet($config, 'repeat_until_period');
        $repeatUntilPeriod = sprintf('+%1$d %2$s', $repeatUntilPeriod, $repeatUnit);
        $repeatUntilDate   = $this->_containerGet($config, 'repeat_until_date');
        // Calculate the end of repetition
        $repeatEnd = ($repeatUntil === 'period')
            ? Carbon::createFromTimestampUTC($start)->modify($repeatUntilPeriod)->getTimestamp()
            : $repeatUntilDate;

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
                $daysOfTheWeek = $this->_containerGet($config, 'repeat_weekly_on');
                $daysOfTheWeek = explode(',', $daysOfTheWeek);

                return new WeeklyRepeatingRule(
                    $this->periodFactory,
                    $start,
                    $end,
                    $repeatPeriod,
                    $repeatEnd,
                    $daysOfTheWeek,
                    $excludeDates
                );

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
