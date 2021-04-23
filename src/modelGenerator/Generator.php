<?php

namespace Infira\Utils;

class Session
{
	protected static $SID = false;
	
	/**
	 * Is session expired
	 *
	 * @var boolean
	 */
	private static $isExpired = false;
	
	/**
	 * Is php session started withd session_start()
	 *
	 * @var bool
	 */
	public static $isStarted = false;
	
	private static $sessionName;
	private static $timeout = 86400;
	
	/**
	 * Config sessions
	 *
	 * @param string      $sessionName - name of the PHP session
	 * @param string|null $SID         start or restore seission with own provided session ID,
	 */
	public static function init(string $sessionName = 'PHPSESSID', string $SID = null)
	{
		if ($sessionName !== 'PHPSESSID')
		{
			$sessionName = "PHPSESSID_$sessionName";
		}
		self::$sessionName = $sessionName;
		if (self::$isStarted == false)
		{
			self::$isStarted = true;
			if (ini_get('session.auto_start') == 0)
			{
				if (headers_sent())
				{
					debug_print_backtrace();
				}
				self::start($SID);
			}
		}
		//debug('session_id()', session_id());
		self::setSID(session_id());
		
		
		$upTime  = self::get('_sessionUpdateTime', time());
		$between = time() - $upTime;
		if ($between > self::$timeout and $upTime > 0)
		{
			self::destroy(true);
			self::$isExpired = true;
		}
		else
		{
			self::$isExpired = false;
		}
		//debug(self::$sessionName);
		//debug($_SESSION);
		//debug('------------------------------------------------');
		self::set('_sessionUpdateTime', time());
	}
	
	/**
	 * Retrives a 32bit session id hash
	 *
	 * @return string
	 */
	public static function getSID(): string
	{
		return self::$SID;
	}
	
	/**
	 * Set a 32bit session id hash
	 *
	 * @param string $SID
	 */
	private static function setSID(string $SID)
	{
		self::$SID = $SID;
	}
	
	/**
	 * Destroy session
	 *
	 * @param bool $takeNewID - take new session ID
	 */
	public static function destroy(bool $takeNewID = true)
	{
		self::flush();
		session_unset();
		session_destroy();
		if (self::$sessionName)
		{
			setcookie(self::$sessionName, '', 1);
			session_name(self::$sessionName);
			session_set_cookie_params(self::$timeout);
		}
		self::start(); //start new session
		//take new session ID
		if ($takeNewID)
		{
			session_regenerate_id(true);
			$SID = session_id();
			self::setSID($SID);
		}
		unset($_COOKIE[session_name()]);
	}
	
	public static function close()
	{
		session_write_close();
	}
	
	/**
	 * @see https://stackoverflow.com/questions/3185779/the-session-id-is-too-long-or-contains-illegal-characters-valid-characters-are
	 * @return bool
	 */
	private static function doStart(): bool
	{
		if (Cookie::exists(self::$sessionName))
		{
			$sessid = Cookie::get(self::$sessionName);
		}
		else
		{
			return session_start();
		}
		
		if (!preg_match('/^[a-zA-Z0-9,\-]{22,40}$/', $sessid))
		{
			return false;
		}
		
		return session_start();
	}
	
	private static function start(string $SID = null)
	{
		if ($SID)
		{
			session_id($SID);
			Cookie::set(self::$sessionName, $SID);
		}
		if (self::$sessionName)
		{
			session_name(self::$sessionName);
		}
		session_set_cookie_params(self::$timeout);
		if (!self::doStart($SID))
		{
			session_id(uniqid());
			session_start();
			session_regenerate_id();
		}
	}
	
	
	/**
	 * Get value from session, if this cache is true
	 *
	 * @param string|null $name    - get value by $name, if null all session values are returned
	 * @param mixed       $default - if the var is not found this variable value is returned
	 * @return mixed
	 */
	public static function get(string $name = null, $default = false)
	{
		if ($name == null)
		{
			return $_SESSION;
		}
		if (self::exists($name))
		{
			return $_SESSION[$name];
		}
		
		return $default;
	}
	
	/**
	 * Set value to session
	 *
	 * @param string $name
	 * @param mixed  $value
	 */
	public static function set(string $name, $value)
	{
		$_SESSION[$name] = $value;
	}
	
	/**
	 * Delete value from session
	 *
	 * @param string $name
	 */
	public static function delete(string $name)
	{
		if (array_key_exists($name, $_SESSION))
		{
			unset($_SESSION[$name]);
		}
	}
	
	/**
	 * Checks is the variable exists in $_SESSION
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function exists(string $name): bool
	{
		return array_key_exists($name, $_SESSION);
	}
	
	/**
	 * Ends session and writes cache to $_SESSION
	 */
	public static function flush()
	{
		$_SESSION = [];
	}
	
	/**
	 * Checks is session expired
	 *
	 * @return bool
	 */
	public static function isExpired(): bool
	{
		return self::$isExpired;
	}
	
	/**
	 * @param int $timeout
	 */
	public static function setTimeout(int $timeout): void
	{
		self::$timeout = $timeout;
	}
	
} // Session
?>