<?php

namespace Infira\Poesis;
class DbQueryHistory
{
	private static $entities = [];
	
	
	/**
	 * Add sql query to runned queries
	 *
	 * @param string $query - a sql query
	 * @param float  $time  - runtime
	 */
	public static function add($query, $time)
	{
		if (strpos($query, '<') and strpos($query, '>'))
		{
			$query = strip_tags($query);
		}
		$backTrace = debug_backtrace();
		$phpFile   = [];
		for ($i = 0; $i <= 10000; $i++)
		{
			if (isset($backTrace[$i]['file']))
			{
				$phpFile[] = baseName($backTrace[$i]['file']) . '(' . $backTrace[$i]['line'] . ')<br />';
			}
		}
		$phpFile          = join(" ", $phpFile);
		self::$entities[] = ['phpFile' => $phpFile, 'query' => $query, 'time' => $time];
	}
	
	
	/**
	 * Get a runned sql query while douing PHP request
	 *
	 * @return string - as html table
	 */
	public static function getHTMLTable(): string
	{
		$html = '';
		if (!defined('T_BEGIN'))
		{
			define('T_BEGIN', '<span style="color:#FF0000;font-weight:bold;">');
			define('T_END', '</span>');
		}
		if (count(self::$entities) > 0)
		{
			$html         .= '<span style="color:red;font-weight:bold;">SQL history</span>';
			$html         .= '<table class="historyTable" cellspacing="1" cellpadding="1" style="background-color:#000000;">';
			$html         .= '
					<colgroup>
						<col width="20" />
						<col width="150" />
						<col width="" />
						<col width="50" />
					</colgroup>
					';
			$html         .= '<tr  style="background-color:#FFFFFF">';
			$html         .= '	<th>Nr.</th>';
			$html         .= '	<th>Trace</th>';
			$html         .= '	<th>Query</th>';
			$html         .= '	<th>Time</th>';
			$html         .= '</tr>';
			$words        = ['SELECT', 'UPDATE', 'DELETE', 'FROM', 'WHERE', 'column', 'Unknown', 'field', 'list', 'SET', 'INSERT', 'INTO', 'UNION'];
			$totalRunTime = 0;
			
			$formatPrec = 6;
			foreach (self::$entities as $key => $row)
			{
				$queryRunTime = $row['time'];
				
				$totalRunTime += $queryRunTime;
				$query        = $row['query'];
				foreach ($words as $val)
				{
					$replaceWord = '<strong style="color:#FF0000;">' . $val . '</strong>';
					$query       = str_replace($val, $replaceWord, $query);
				}
				$query          = str_replace('`,`', '`, `', $query);
				$row['phpFile'] = str_replace('->', '-> ', $row['phpFile']);
				
				$html .= '<tr style="background-color:#FFFFFF;">';
				$html .= '<td style="text-align:left;padding:3px;">' . ($key + 1) . '</td>';
				$html .= '<td style="text-align:left;padding:3px;color:black;font-weight:normal;" ondblclick="this.firstChild.style.display=\'\'" ><div style="display:none;">' . $row['phpFile'] . '</div></td>';
				$html .= '<td style="text-align:left;padding:3px;">' . $query . '</td>';
				$html .= '<td style="text-align:left;padding:3px;">' . number_format($queryRunTime, $formatPrec) . '</td>';
				$html .= '</tr>';
			}
			$html .= '<tr  style="background-color:#FFFFFF">
				<td colspan="3" style="text-align:right">
					<strong>Total:</strong></td>
					<td style="text-align:right"><strong>' . number_format($totalRunTime, $formatPrec) . '</strong>
				</td>
				</tr>';
			$html .= '</table>';
		}
		
		return $html;
	}
	
}

?>