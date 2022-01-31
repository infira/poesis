<?php

namespace Infira\Poesis\support;

/**
 * This class handles date strings
 */
class Date
{
	/**
	 * convert to timestamp
	 *
	 * @param string|int $time
	 * @param string|int $now - use base time or string, defaults to now ($now is converted to time)
	 * @return int - converted timestamp
	 */
	public static function toTime(string $time, $now = null): int
	{
		if (preg_match('/\D/i', $time)) {
			$now  = ($now === null) ? time() : self::toTime($now);
			$time = strtotime($time, $now);
		}
		else {
			$time = intval($time);
		}
		
		return $time;
	}
	
	/**
	 * Constructs DateTime objet
	 *
	 * @param string             $datetime
	 * @param \DateTimeZone|null $timezone
	 * @throws \Exception
	 * @return DateTime
	 */
	public static function of(string $datetime = 'now', ?\DateTimeZone $timezone = null): DateTime
	{
		$ts = null;
		if (is_numeric($datetime)) {
			$ts       = intval($datetime);
			$datetime = 'now';
		}
		$tm = new DateTime($datetime, $timezone);
		if ($ts !== null) {
			$tm->setTimestamp($ts);
		}
		
		return $tm;
	}
}