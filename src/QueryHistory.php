<?php

namespace Infira\Poesis;

class QueryHistory
{
	private static $entities = [];
	
	
	/**
	 * Add sql query to runned queries
	 *
	 * @param string $query - a sql query
	 * @param float  $time  - runtime
	 */
	public static function add(string $query, float $time)
	{
		if (strpos($query, '<') and strpos($query, '>'))
		{
			$query = strip_tags($query);
		}
		$backTrace  = debug_backtrace();
		$traceFiles = [];
		$nr         = 1;
		$cwd        = getcwd();
		foreach ($backTrace as $trace)
		{
			if (isset($trace['file']))
			{
				$traceFiles[] = $nr . ') ' . str_replace($cwd, '', $trace['file']) . '(' . $trace['line'] . ')<br />';
				$nr++;
			}
		}
		$traceFiles       = join(" ", $traceFiles);
		self::$entities[] = ['trace' => $traceFiles, 'query' => $query, 'time' => $time];
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
			$html         .= '	<th>Count</th>';
			$html         .= '	<th>Trace</th>';
			$html         .= '	<th>Query</th>';
			$html         .= '	<th>Time</th>';
			$html         .= '</tr>';
			$words        = ['SELECT', 'UPDATE', 'DELETE', 'FROM', 'WHERE', 'column', 'Unknown', 'field', 'list', 'SET', 'INSERT', 'INTO', 'UNION'];
			$totalRunTime = 0;
			
			$formatPrec = 6;
			$queryes    = [];
			foreach (self::$entities as $key => $row)
			{
				self::$entities[$key]['count'] = 0;
				self::$entities[$key]['trace'] = str_replace('->', '-> ', $row['trace']);
			}
			foreach (self::$entities as $row)
			{
				$hash = md5($row['query']);
				if (!isset($queryes[$hash]))
				{
					$row['count']   = 1;
					$queryes[$hash] = $row;
				}
				else
				{
					$queryes[$hash]['count']++;
					$queryes[$hash]['time']  += $row['time'];
					$queryes[$hash]['trace'] .= "<br><br><br>" . $row['trace'];
				}
			}
			$key = 0;
			foreach ($queryes as $row)
			{
				$queryRunTime = $row['time'];
				
				$totalRunTime += $queryRunTime;
				$query        = $row['query'];
				foreach ($words as $val)
				{
					$replaceWord = '<strong style="color:#FF0000;">' . $val . '</strong>';
					$query       = str_replace($val, $replaceWord, $query);
				}
				$query        = str_replace('`,`', '`, `', $query);
				$row['trace'] = str_replace('->', '-> ', $row['trace']);
				
				$html .= '<tr style="background-color:#FFFFFF;">';
				$html .= '<td style="text-align:left;padding:3px;">' . ($key + 1) . '</td>';
				$html .= '<td style="text-align:left;padding:3px;">' . $row['count'] . '</td>';
				$html .= '<td style="text-align:left;padding:3px;color:black;font-weight:normal;" ondblclick="this.firstChild.style.display=\'\'" ><div style="display:none;">' . $row['trace'] . '</div></td>';
				$html .= '<td style="text-align:left;padding:3px;">' . $query . '</td>';
				$html .= '<td style="text-align:left;padding:3px;">' . number_format($queryRunTime, $formatPrec) . '</td>';
				$html .= '</tr>';
			}
			$html .= '<tr  style="background-color:#FFFFFF">
				<td colspan="4" style="text-align:right">
					<strong>Total:</strong></td>
					<td style="text-align:right"><strong>' . number_format($totalRunTime, $formatPrec) . '</strong>
				</td>
				</tr>';
			$html .= '</table>';
		}
		
		return $html;
	}
}