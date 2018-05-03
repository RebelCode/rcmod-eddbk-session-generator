<?php

namespace RebelCode\EddBookings\Sessions\Generator;

use ArrayIterator;
use Carbon\Carbon;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Iterator\CountIterableCapableTrait;
use Dhii\Iterator\NormalizeIteratorCapableTrait;
use Dhii\Iterator\ResolveIteratorCapableTrait;
use Iterator;
use IteratorIterator;
use RebelCode\EddBookings\Sessions\Time\PeriodFactoryInterface;
use stdClass;
use Traversable;

/**
 * A session generator rule that repeats in two ways: every week and on certain week days.
 *
 * @since [*next-version*]
 */
class WeeklyRepeatingRule extends AbstractIteratorRule
{
    /* @since [*next-version*] */
    use NormalizeIteratorCapableTrait;

    /* @since [*next-version*] */
    use CountIterableCapableTrait;

    /* @since [*next-version*] */
    use ResolveIteratorCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /**
     * The iterator for the days of the week to repeat for.
     *
     * The iterator must be finite, and yield day-of-the-week names, such as "monday", "tuesday", etc...
     * Casing does not matter.
     *
     * @since [*next-version*]
     *
     * @var Iterator
     */
    protected $daysOfTheWeek;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param PeriodFactoryInterface|null $periodFactory The period factory instance, if any.
     * @param int|null                    $start         The start timestamp fo the rule.
     * @param int|null                    $end           The end timestamp fo the rule.
     * @param int|null                    $repeatFreq    How frequently the rule repeats.
     * @param int|null                    $repeatEnd     The timestamp when the repetition ends.
     * @param array|stdClass|Traversable  $daysOfTheWeek The names of the days of the week to repeat for.
     * @param array|stdClass|Traversable  $excludeDates  The list of dates to exclude.
     */
    public function __construct(
        $periodFactory,
        $start,
        $end,
        $repeatFreq = null,
        $repeatEnd = null,
        $daysOfTheWeek = [],
        $excludeDates = []
    ) {
        $this->_initRule($periodFactory, $start, $end, $repeatFreq, $repeatEnd, $excludeDates);
        $this->_setDaysOfTheWeek($daysOfTheWeek);
    }

    /**
     * Retrieves an iterator for the days of the week for which this rule repeats.
     *
     * @since [*next-version*]
     *
     * @return Iterator An iterator that yields the names of the days of the week.
     */
    protected function _getDaysOfTheWeek()
    {
        return $this->daysOfTheWeek;
    }

    /**
     * Sets the days of the week to repeat for.
     *
     * @since [*next-version*]
     *
     * @param array|stdClass|Traversable $daysOfTheWeek The names of the days of the week to repeat for.
     */
    protected function _setDaysOfTheWeek($daysOfTheWeek)
    {
        if ($this->_countIterable($daysOfTheWeek) === 0) {
            $daysOfTheWeek = [
                Carbon::createFromTimestampUTC($this->_getStart())->format('l'),
            ];
        }

        $this->daysOfTheWeek = $this->_normalizeIterator($daysOfTheWeek);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function rewind()
    {
        parent::rewind();

        $this->daysOfTheWeek->rewind();
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _getNextOccurrence($timestamp)
    {
        $current = Carbon::createFromTimestampUTC($timestamp);

        // If reached end of days-of-the-week iterator
        if (!$this->daysOfTheWeek->valid()) {
            // Rewind it for future calls
            $this->daysOfTheWeek->rewind();

            // Advance to next week
            $current->addWeeks($this->_getRepeatFreq());
        }

        // Retrieve the next day of the week from the iterator
        $dotw = $this->daysOfTheWeek->current();

        // Advance the days-of-the-week iterator
        $this->daysOfTheWeek->next();

        // Calculate timestamp for the day-of-the-week
        $time   = $current->toTimeString();
        $modStr = sprintf('this week %1$s %2$s', $dotw, $time);
        $result = $current->modify($modStr)->getTimestamp();

        // If the day-of-the-week is this rule's period or in the past, calculate the next occurrence
        if ($result === $this->getStart() || $result < $this->getStart()) {
            return $this->_getNextOccurrence($timestamp);
        }

        // Otherwise, return it
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _createArrayIterator(array $array)
    {
        return new ArrayIterator($array);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _createTraversableIterator(Traversable $traversable)
    {
        return new IteratorIterator($traversable);
    }
}
