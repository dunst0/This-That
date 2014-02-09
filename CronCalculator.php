<?php
/**
 * This file contains CronCalculator class.
 */

namespace me\dunst0\utils;


/**
 * Class for calculating the next run of a cron line.
 *
 * @author Rick Barenthin <dunst0@gmail.com>
 * @author Sven George
 *
 * @version 1.0.0
 */
abstract class CronCalculator
{
    /**
     * Regular expression for a crontab line.
     */
    const REGEX_CRON_LINE = '/^(?P<minutes>(?:((?:(?:(?:\\*)|(?:\\d+-\\d+))(?:\\/\\d+)?)|\\d+)(,(?2))*))\\s+(?P<hours>(?:((?:(?:(?:\\*)|(?:\\d+-\\d+))(?:\\/\\d+)?)|\\d+)(,(?5))*))\\s+(?P<days>(?:((?:(?:(?:\\*)|(?:\\d+-\\d+))(?:\\/\\d+)?)|\\d+)(,(?8))*))\\s+(?P<months>(?:((?:(?:(?:\\*)|(?:\\d+-\\d+))(?:\\/\\d+)?)|\\d+)(,(?11))*))\\s+(?P<weekdays>(?:((?:(?:(?:\\*)|(?:\\d+-\\d+))(?:\\/\\d+)?)|\\d+)(,(?14))*))\\s+.*$/';

    /**
     * Regular expression for a single cron value.
     */
    const REGEX_CRON_VALUE = '/^(?:(?:(?:(?P<any>\\*)|(?P<fromto>(?P<from>\\d{1,})\\-(?P<to>\\d{1,})))(?:\/(?P<step>\\d{1,})){0,1})|(?P<value>\\d{1,}))$/';

    /**
     * Reference timestamp for calculation next run of cronjobs.
     *
     * @var array $cronNow
     */
    private static $cronNow = array();

    /**
     * Calculates the weekday number of given date.
     *
     * @param int $month month to use
     * @param int $day day to use
     * @param int $year year to use
     *
     * @return int weekday number of the input day
     */
    private static function dayToWeekday($month, $day, $year)
    {
        return date('N', mktime(0, 0, 0, $month, $day, $year));
    }

    /**
     * Parses the cell data of a cronline cell.
     *
     * @param string $cellData possible values string
     * @param int $from bottom limit
     * @param int $to upper limit
     *
     * @return int[] all possible values between from and to
     */
    private static function parseCronTabCell($cellData, $from, $to)
    {
        $cellList = explode(',', $cellData);
        $anzahl = count($cellList);
        $step = 1; // default step is 1

        $result = array();

        foreach ($cellList as $value) {
            if (preg_match(self::REGEX_CRON_VALUE, $value, $matches)) {

                if (!empty($matches['value'])) {
                    $result[] = (int) $matches['value'];
                } else {
                    $step = !empty($matches['step']) ? $matches['step'] : $step;
                    $start = $from;
                    $end = $to;

                    if (!empty($matches['fromto'])) {
                        $start = (int) ($matches['from'] >= $start ? $matches['from'] : $start);
                        $end = (int) ($matches['to'] <= $end ? $matches['to'] : $end);
                    }

                    for ($i = $start; $i <= $end; $i += $step) {
                        $result[] = (int) $i;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Parses the cell data of a minute cell.
     *
     * @param string $cellData cell data to parse
     *
     * @return int[] minutes that are specified by minute string
     */
    private static function parseMinutesCell($cellData)
    {
        return self::parseCronTabCell($cellData, 0, 59);
    }

    /**
     * Parses the cell data of a hour cell.
     *
     * @param string $cellData cell data to parse
     *
     * @return int[] hours that are specified by hour string
     */
    private static function parseHoursCell($cellData)
    {
        return self::parseCronTabCell($cellData, 0, 23);
    }

    /**
     * Parses the cell data of a day cell.
     *
     * @param string $cellData cell data to parse
     * @param int $month month to use for parsing
     * @param int $year year to use for parsing
     *
     * @return int[] days that are specified by day string
     */
    private static function parseDaysCell($cellData, $month, $year)
    {
        $daysInMonth = date('t', mktime(0, 0, 0, $month, 1, $year));
        return self::parseCronTabCell($cellData, 1, $daysInMonth);
    }

    /**
     * Parses the cell data of a month cell.
     *
     * @param string $cellData cell data to parse
     *
     * @return int[] months that are specified by month string
     */
    private static function parseMonthsCell($cellData)
    {
        return self::parseCronTabCell($cellData, 1, 12);
    }

    /**
     * Parses the cell data of a weekday cell.
     *
     * @param string $cellData cell data to parse
     *
     * @return int[] weekdays that are specified by weekday string
     */
    private static function parseWeekDaysCell($cellData)
    {
        return self::parseCronTabCell($cellData, 0, 7);
    }

    /**
     * Initializes class with new reference time.
     */
    public static function init()
    {
        $now = time();

        self::$cronNow = array(
                'weekday' => (int) date('N', $now),
                'day'     => (int) date('j', $now),
                'month'   => (int) date('n', $now),
                'hour'    => (int) date('G', $now),
                'minute'  => (int) date('i', $now),
                'year'    => (int) date('Y', $now)
            );
    }

    /**
     * Calculates the next run of a cronjob.
     *
     * @param string $cronjob the crontab line for the cronjob
     *
     * @return int|false the next run timestamp or false on error
     */
    public static function calculateNextRun($cronjob)
    {
        if (preg_match(self::REGEX_CRON_LINE, $cronjob, $matches)) {
            $weekdayCell = $matches['weekdays'];
            $monthCell = $matches['months'];
            $dayCell = $matches['days'];
            $hourCell = $matches['hours'];
            $minuteCell = $matches['minutes'];

            $weekdays = self::parseWeekDaysCell($weekdayCell);
            $months = self::parseMonthsCell($monthCell);
            $days = array();
            $years = array(self::$cronNow['year'], self::$cronNow['year'] + 1);
            $hours = self::parseHoursCell($hourCell);
            $minutes = self::parseMinutesCell($minuteCell);

            foreach ($years as $year) {
                foreach ($months as $month) {
                    $days = self::parseDaysCell($dayCell, $month, $year);

                    foreach ($days as $day) {
                        foreach ($weekdays as $weekday) {
                            if ($weekday == self::dayToWeekday($month, $day, $year)) {
                                if ($year == self::$cronNow['year']) {

                                    if ($month == self::$cronNow['month']) {
                                        if ($day == self::$cronNow['day']) {

                                            foreach ($hours as $hour) {
                                                if ($hour == self::$cronNow['hour']) {

                                                    foreach ($minutes as $minute) {
                                                        if ($minute > self::$cronNow['minute']) {
                                                            return mktime($hour, $minute, 0, $month, $day, $year);
                                                        }
                                                    }

                                                } else if ($hour > self::$cronNow['hour']) {
                                                    return mktime($hour, $minutes[0], 0, $month, $day, $year);
                                                }
                                            }

                                        } else if ($day > self::$cronNow['day']) {
                                            return mktime($hours[0], $minutes[0], 0, $month, $day, $year);
                                        }
                                    } else if ($month > self::$cronNow['month']) {

                                        foreach ($hours as $hour) {
                                            if ($hour == self::$cronNow['hour']) {

                                                foreach ($minutes as $minute) {
                                                    if ($minute > self::$cronNow['minute']) {
                                                        return mktime($hour, $minute, 0, $month, $day, $year);
                                                    }
                                                }

                                            } else if ($hour > self::$cronNow['hour']) {
                                                return mktime($hour, $minutes[0], 0, $month, $day, $year);
                                            }
                                        }

                                    }

                                } else {
                                    return mktime($hours[0], $minutes[0], 0, $month, $day, $year);
                                }
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

}
