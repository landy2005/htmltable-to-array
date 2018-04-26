<?php
/**
 * Library for parse HTML table into array at the given URL.
 *
 * Copyright (c) 2018, Mike Stupalov <mike@stupalov.com>
 *
 * @author    Mike Stupalov <mike@stupalov.com>
 * @copyright 2018 Mike Stupalov <mike@stupalov.com>
 * @license   MIT
 * @version   0.3
 *
 */

class HTMLTable2Array {

   /**
     * @var config[]
     */
    protected $config = [
        //'firstColIsRowName' 	=> TRUE, 	// Boolean indicating whether the first column in the table should be parsed as the title for all values in the row.
        //'firstRowIsData' 		=> FALSE,	// Boolean indicating whether the first row contains data (not headers).
										// Choosing TRUE treats the first row as data regardless of <th> tags. DO NOT choose this if there are headers in the first row that you want to override.
        'tableID' 			=> '',		// String to contain the ID of the table. Allows user to specify a particular table. Default behavior simply grabs the first table encountered on the page.
        'tableAll' 			=> FALSE,	// Collect all tables from page. If FALSE follect only first table or by tableID
        'headers' 			=> NULL,	// Array of header names.
										// Format: array(colNum1 => header1, colNum2 => header2).
        'headerIDs' 		=> TRUE,	// Use header id attribute instead element value.
        'ignoreHidden' 		=> FALSE,	// Boolean indicating whether rows tagged with style="display: none;" should appear in output.
										// Setting TRUE will suppress hidden rows.
        'ignoreColumns' 	=> NULL,	// Array of column indexes to ignore. Named columns use caseSensitive compare!
										// Format: array(0 => firstColToIgnore, 1 => secondColToIgnore) OR array(firstIndex, secondIndex).
        'onlyColumns' 		=> FALSE,	// Array of column indexes to include; all others are ignored. Named columns use caseSensitive compare!
										// Format: array(0 => firstColToInclude, 1 => secondColToInclude) OR array(firstIndex, secondIndex).
        'format'   			=> 'array',	// Which output format to use. Possible: array, json, serialize, yaml
        'print'   			=> FALSE,	// Boolean indicating whether the program should echo to stdout or simply return the output to the caller.
        'auth'     			=> FALSE,	// Use http auth, TRUE will set basic http auth.
        'username' 			=> '',		// Username for basic http auth
        'password' 			=> '',		// Password for basic http auth
        'method'            => 'get',	// Method for pass params to url (get/post)
        'useragent'			=> '',		// Set custom useragent. If empty, used default curl user agent.
        'silent' 			=> FALSE,	// Silent output
        'verbose' 			=> FALSE,	// Verbose CURL output
    ];

    function __construct($args = []) {
        //$this->config = array_merge($this->config, $args);
		foreach ($this->config as $arg => $value)
		{
			$this->{$arg} = isset($args[$arg]) ? $args[$arg] : $value;
		}
		// Make json output to clean
		if ($this->printJSON) {
			$this->silent = TRUE;
		}
        // Reset tableID for tableAll
        if ($this->tableAll) {
            $this->tableID = '';
        }
		$this->method = strtolower($this->method);
		//var_dump($args);
		//var_dump($this->config);
    }

	public function tableToArray($url, $params = [], $testingTable = NULL) {

		$ignoring = FALSE;
		$excluding = FALSE;

		if (NULL != $this->onlyColumns) {
			if (!is_array($this->onlyColumns)) {
				$this->echo_t('onlyColumns must be an array. Did not ignore any columns.');
				$this->onlyColumns = NULL;
			} else {
				for ($i = 0; $i < count($this->onlyColumns); $i++) {
					if (is_int($this->onlyColumns[$i])) {
						$excluding = TRUE;
						$this->ignoreColumns = NULL;
						break;
					}
				}
			}
		}
		else if (NULL != $this->ignoreColumns) {
			if (!is_array($this->ignoreColumns)) {
				$this->echo_t('ignoreColumns must be an array. Did not ignore any columns.');
				$this->ignoreColumns = NULL;
			} else {
				for ($i = 0; $i < count($this->ignoreColumns); $i++) {
					if (is_int($this->ignoreColumns[$i])) {
						$ignoring = TRUE;
						break;
					}
				}
			}
		}

		if (NULL != $this->headers) {
			if (!is_array($this->headers)) {
				$this->echo_t('headers must be an array. Will not change any headers.');
				$this->headers = NULL;
			}
		}

        // Fetch HTML Content
        $html = $this->fetchContent($url, $params, $testingTable);

        // Init vars
        $all_tables = [];

        // Load HTML as DOM
        if (function_exists('mb_convert_encoding')) {
            $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'); // Fix UTF-8 strings
        }

        // Initialise DOM and XPath
        $dom = new DOMDocument();
        libxml_use_internal_errors(TRUE); // Disable XML warnings
        $dom->loadHTML($html);
        //var_dump($dom);
        $xpath = new DOMXpath($dom);

        // Get all tables from HTML
        $tables = $dom->getElementsByTagName('table');

        foreach ($tables as $table) {

            // Process tables
            if (strlen($this->tableID))
            {
                // Find table with specified ID
                if ($table->attributes->getNamedItem('id')->nodeValue != $this->tableID)
                {
                    continue;
                }
            }

            if (!is_object($table)) {
                die("ERROR: Table not founded.");
            }
            //print_r($table);
            //print_r($table->attributes->getNamedItem('id')->nodeValue);
            //print_r($table->childNodes);

            // Init vars
            $table_array = [];

            /* Begin table footer parse */
            $tfoots = $table->getElementsByTagName('tfoot');
            $this->removeNodes($tfoots); // Remove tfoot for correctly set elements
            /* End table footer parse */

            /* Begin table header parse */
            $theads = $table->getElementsByTagName('thead');
            if ($theads->length > 0) {

                foreach ($theads->item(0)->getElementsByTagName('tr') as $row => $tr) {
                    //var_dump($row); var_dump($tr);
                    $key = 0;
                    foreach ($tr->childNodes as $node) {
                        if ($node->tagName == 'td') {
                            // Iterate key and continue
                            $key++;
                            continue;
                        }
                        elseif ($node->tagName == 'th') {
                            $table_header[$row][$key] = $this->getElementKey($node);
                            $key++;
                        }
                    }
                }

                $this->removeNodes($theads); // Remove tfoot for correctly set elements

                //echo $dom->saveHTML();
                // Fetch rows (after remove tfoot and thead)
                $rows = $table->getElementsByTagName('tr');

            } else {
                // Fetch rows
                $rows = $table->getElementsByTagName('tr');
                foreach ($rows as $row => $tr) {
                    //var_dump($row); var_dump($tr);
                    $key = 0;
                    foreach ($tr->childNodes as $node) {
                        if ($node->tagName == 'td') {
                            // Iterate key and continue
                            $key++;
                            continue;
                        }
                        elseif ($node->tagName == 'th') {
                            $table_header[$row][$key] = $this->getElementKey($node);
                            $key++;
                        }
                    }
                    if (count($table_header)) {
                        // Remove first row
                        $this->removeNodes($rows, 0); // Remove tfoot for correctly set elements
                    }
                    break; // Stop on first row
                }
            }
            //print_r($table_header);
            //print_r(count($table_header));
            /* End table header parse */

            /* Begin table body parse */
            //print_r($rows);
            foreach($rows as $row => $tr) {
                if ($this->ignoreHidden && preg_match('/display:\ *none/i', $tr->attributes->getNamedItem('style')->nodeValue)) {
                    // Skip hidden rows
                    continue;
                }

                $i = 0;
                $arr = [];
                foreach ($tr->childNodes as $node) {
                    if ($node->tagName == 'th') {
                        $table_header[$row][$i] = $this->getElementKey($node);
                        $key++;
                    }
                    else if ($node->tagName == 'td') {

                        // Set custom headers
                        if (isset($this->headers[$i])) {
                            $key = $this->headers[$i];
                        } else {
                            $key = isset($table_header[0][$i]) ? $table_header[0][$i] : $i;
                        }

                        // Ignore or Exclude columns
                        if ($ignoring && (in_array($key, $this->ignoreColumns, TRUE) || in_array($i, $this->ignoreColumns, TRUE))) {
                            $i++;
                            continue;
                        }
                        else if ($excluding && (!in_array($key, $this->onlyColumns, TRUE) && !in_array($i, $this->onlyColumns, TRUE))) {
                            $i++;
                            continue;
                        }

                        $arr[$key] = trim($node->textContent);
                        $i++;
                    }
                }

                $table_array[] = $arr;

            }
            /* End table body parse */

            //print_r($table_array);
            if ($this->tableAll) {
                $all_tables[] = $table_array;
            } else {
                $all_tables = $table_array;
                break;
            }
        }
        unset($table_array); // Clean

		if ($this->print) {
            switch (strtolower($this->format)) {
                case 'json':
                    echo(json_encode($all_tables));
                    break;
                case 'serialize':
                    echo(serialize($all_tables));
                    break;
                case 'yaml':
                    echo(yaml_emit($all_tables));
                    break;
                default:
                    // For array use well formatted print
                    print_r($all_tables);
            }
		} else {
            switch (strtolower($this->format)) {
                case 'json':
                    $output = json_encode($all_tables);
                    break;
                case 'serialize':
                    $output = serialize($all_tables);
                    break;
                case 'yaml':
                    $output = yaml_emit($all_tables);
                    break;
                default:
                    // For array use well formatted print
                    $output = $all_tables;
            }
			return $output;
		}

	}

    private function fetchContent($url, $params = [], $testingTable = NULL) {

		if (NULL == $testingTable) {

			// URL GET request params
			if (count($params))
			{
				$query = http_build_query($params);
				switch ($this->method)
				{
					case 'post':
						break;
					default:
						$url .= '?' . $query;
				}
			} else {
				$query = '';
			}

			// Get html using curl
			$c = curl_init($url);
			curl_setopt($c, CURLOPT_TIMEOUT, 60);
			curl_setopt($c, CURLOPT_RETURNTRANSFER, TRUE);
			// Set HTTP auth
			if ($this->auth)
			{
				curl_setopt($c, CURLOPT_USERPWD, $this->username . ':' . $this->password);
			}
			// Useragent
			if (strlen($this->useragent)) {
				curl_setopt($c, CURLOPT_USERAGENT, $this->useragent);
			}
			// Set POST params if exist
			if ($this->method == 'post' && $query)
			{
				curl_setopt($c, CURLOPT_POST, 1);
				curl_setopt($c, CURLOPT_POSTFIELDS, $query);
			}
			// Verbose output
			if ($this->verbose) {
				curl_setopt($c, CURLOPT_VERBOSE, $this->verbose);
				$verbose = fopen('php://temp', 'w+');
				curl_setopt($c, CURLOPT_STDERR, $verbose);
			}

			$html = curl_exec($c);
			if (curl_error($c))
			{
				die(curl_error($c));
			}

			// Check return status
			$status = curl_getinfo($c, CURLINFO_HTTP_CODE);
			if (200 <= $status && 300 > $status) {
				$this->echo_t('Got the html from '.$url);
			} else {
				die('Failed to get html from '.$url);
			}
			// Verbose output
			if ($this->verbose) {
				$version = curl_version();
				$get_info = curl_getinfo($c);
				$get_info['version'] = $version['version'];

				rewind($verbose);
				foreach (explode("\n", str_replace("\r", '', stream_get_contents($verbose))) as $line) {
					list($key, $value) = explode(': ', $line);
					if (strlen($value)) {
						$key = strtolower(trim($key, "< "));
						$get_info[$key] = $value;
					}
				}
				fclose($verbose);
				//var_dump($get_info);

				$metrics = <<<EOD
URL.......: {$get_info['url']}
Code......: {$get_info['http_code']} ({$get_info['redirect_count']} redirect(s) in {$get_info['redirect_time']} secs)
Content...: {$get_info['content_type']} Size: {$get_info['download_content_length']} (Own: {$get_info['size_download']}) Filetime: {$get_info['filetime']}
Time......: {$get_info['total_time']} Start @ {$get_info['starttransfer_time']} (DNS: {$get_info['namelookup_time']} Connect: {$get_info['connect_time']} Request: {$get_info['pretransfer_time']})
Speed.....: Down: {$get_info['speed_download']} (avg.) Up: {$get_info['speed_upload']} (avg.)
User-Agent: {$get_info['user-agent']}
Server....: {$get_info['server']}
Curl......: v{$get_info['version']}
EOD;
				echo($metrics);

			}
			curl_close($c);
		} else {
			$html = $testingTable;
		}

        return $html;
    }

    private function getElementKey(DOMElement $node) {
        if ($this->headerIDs && $node->hasAttribute('id')) {
            $key = $node->attributes->getNamedItem('id')->nodeValue;
        } else {
            $key = $node->textContent;
        }
        return trim($key);
    }

    /**
     * Remove node elements
     * 
     */
    private function removeNodes(DOMNodeList $nodes, $key = FALSE) {
        // https://stackoverflow.com/a/34037291/9506633
        if ($nodes->length > 0) {
            // tfoot exist, remove it!
            if (is_numeric($key)) {
                // Remove specific node
                $node = $nodes->item($key);
                $node->parentNode->removeChild($node);
            } else {
                // Remove all nodes
                for ($i = $nodes->length; --$i >= 0; ) {
                    $node = $nodes->item($i);
                    $node->parentNode->removeChild($node);
                }
            }
        }
    }

	private function echo_t($text)
	{
		 // Skip output on silent
		if ($this->silent) {
			return;
		}
		if (strpos(php_sapi_name(), 'cli') === FALSE) {
			$text .= '<br />';
		}
		$text .= PHP_EOL;

		echo($text);
	}
}

// EOF
