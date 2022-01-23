<?php

namespace Infira\Poesis\support;


class Utils
{
	/**
	 * convert variable to array
	 *
	 * @param string|object|array|numeric $var
	 * @param string                      $caseStringExplodeDelim - if the $var type is string then string is exploded to this param delimiter
	 * @return array
	 */
	public static function toArray($var, string $caseStringExplodeDelim = ","): array
	{
		if (is_object($var)) {
			return get_object_vars($var);
		}
		if (is_string($var) or is_numeric($var)) {
			$ex = explode($caseStringExplodeDelim, "$var");
			$r  = [];
			if (is_array($ex)) {
				foreach ($ex as $v) {
					$v = trim($v);
					if ($v != "") {
						$r[] = $v;
					}
				}
			}
			
			return $r;
		}
		if (is_array($var)) {
			return $var;
		}
		
		return [];
	}
}