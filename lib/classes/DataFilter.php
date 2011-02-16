<?php

//require_once __DIR__.'/../common.php';

class DataFilter {
	private $conf;
	
	private $source;
	private $dest;
	
	public function __construct($conf) {
		$this->conf = $conf;
		
		$this->source = new EzPDO($this->conf->source);
		$this->dest = new EzPDO($this->conf->destination);
	}
	
	private function getDbAddress($dbConf) {
		return $dbConf->database.'@'.$dbConf->host;
	}
	
	public function processDatabase() {
		puts("=========================================");
		puts("Processing DB: ".$this->getDbAddress($this->conf->source).' -> '.$this->getDbAddress($this->conf->destination));
		
		$tables = $this->listTables();
		
		foreach ($tables as $name)
			$this->processTable($name);
	}
	
	private function listTables() {
		$result = $this->source->getList('show tables');
		$tables = array();
		
		for ($i=0; $i<count($result); $i++)
			array_push($tables, $result[$i][0]);
		
		return $tables;
	}
	
	private function truncateTable($pdo, $tablename) {
		return $pdo->execute("TRUNCATE TABLE $tablename");
	}
	
	private function getColumnNames($pdoStatement) {
		$colCount = $pdoStatement->columnCount();
		$types = array_slice(func_get_args(), 1);
		
		$names = array();
		
		for ($i=0; $i<$colCount; $i++) {
			$colMetadata = $pdoStatement->getColumnMeta($i);
			
//			puts("##### ".json_encode($colMetadata));
			
			if (!$types || isset($colMetadata['native_type']) && in_array($colMetadata['native_type'], $types))
				array_push($names, $colMetadata['name']);
		}
		
		return $names;
	}
	
	private function prepareInsertStatement($pdo, $tableName, $colNames) {
		$query = "INSERT INTO $tableName (`".implode("`, `", $colNames)."`) VALUES (";
		for ($i=0; $i<count($colNames); $i++) {
			if ($i>0)
				$query.=', ';
			
			$query.='?';
		}
		
		$query.=')';
		
		return $pdo->prepare($query);
	}
	
	private function getRowValues($row, $colNames) {
		$rowValues = array();
		for ($i=0; $i<count($colNames); $i++) {
			array_push($rowValues, $row[$colNames[$i]]);
		}
		return $rowValues;
	}
	
	private function processTable($name) {
		puts("-----");
		puts("Processing table: $name");
		
		$stm = $this->source->execute("select * from $name");
		
		$count = $stm->rowCount();
		puts("Row count: $count");
		
		if($this->truncateTable($this->dest, $name))
			puts("Truncated destination table.");
		else
			puts("An error occured truncating destination table.");
			
		$strCols = $this->getColumnNames($stm, 'VAR_STRING', 'BLOB', 'TEXT');
		$allCols = $this->getColumnNames($stm);
		
		puts("Table columns: ".implode(', ', $allCols));
		puts("Filtered columns: ".implode(', ', $strCols));
			
		$insertStm = $this->prepareInsertStatement($this->dest, $name, $allCols);
		
		for ($i=0; $i<$count; $i++) {
//		for ($i=0; $i<min($count, 10); $i++) {

			echo '- ';
			$row = $stm->fetch(PDO::FETCH_BOTH);
			
			echo $row[0];
			echo ' | ';
			
			foreach ($strCols as $colname) {
				$row[$colname] = $this->filterValue($row[$colname]);
				echo substr($row[$colname], 0, 10);
				echo ' | ';
			}
			
//			puts(implode(', ', $this->getRowValues($row, $allCols)));
			
			if (!$insertStm->execute($this->getRowValues($row, $allCols)))
				throw new Exception('Unable to insert row: '.json_encode($row));
			
			puts (' ;');
		}
		
		puts("All rows inserted.");
	}
	
	private function filterValue($value) {
		// If not a url and urlencoded then decode.
		if (!preg_match('/^[a-z]+:\/\//i', $value) && preg_match('/^\S+$/', $value) &&
				(preg_match('/%[0-9A-F]{2}/i', $value) || preg_match('/\+/', $value))) {
					$value = urldecode($value);
					
					$currentEncoding = mb_detect_encoding($value, 'UTF-8, ISO-8859-1', true);
					
//					puts("@@@@@ $currentEncoding -- $value >>>>> ".iconv($currentEncoding, "utf-8", $value));
					
					if ($currentEncoding != 'UTF-8')
						$value = iconv($currentEncoding, "utf-8", $value);
				}
		
		// Strip backslashes if there are any.
		while (preg_match('/\\\\/', $value)) { // quad backslash because regex parser needs 2.
			$strippedValue = stripcslashes($value);
			
			// must test if changed anything to avoid infinite loop.
			if ($strippedValue == $value)
				break;
			
			$value = $strippedValue;
		}
		
		return $value;
	}
}