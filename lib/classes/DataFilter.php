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
	
	private function getColumnNames($pdoStatement, $type=null) {
		$colCount = $pdoStatement->columnCount();
		
		$names = array();
		
		for ($i=0; $i<$colCount; $i++) {
			$colMetadata = $pdoStatement->getColumnMeta($i);
			
			if (!$type || isset($colMetadata['native_type']) && $colMetadata['native_type'] == $type)
				array_push($names, $colMetadata['name']);
		}
		
		return $names;
	}
	
	private function prepareInsertStatement($pdo, $tableName, $colNames) {
		$query = "INSERT INTO $tableName (".implode(', ', $colNames).') VALUES (';
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
			$currentCol = $colNames[$i];
			array_push($rowValues, $row->$currentCol);
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
			
		$strCols = $this->getColumnNames($stm, 'VAR_STRING');
		$allCols = $this->getColumnNames($stm);
		
		puts("Table columns: ".implode(', ', $allCols));
		puts("Filtered columns: ".implode(', ', $strCols));
			
		$insertStm = $this->prepareInsertStatement($this->dest, $name, $allCols);
		
//		for ($i=0; $i<$count; $i++) {
		for ($i=0; $i<min($count, 10); $i++) {
			$row = $stm->fetch(PDO::FETCH_OBJ);
			
			foreach ($strCols as $colname) {
				$row->$colname = $this->filterValue($row->$colname);
			}
			
			var_dump($row);
			puts(implode(', ', $this->getRowValues($row, $allCols)));
			
			if (!$insertStm->execute($this->getRowValues($row, $allCols)))
				throw new Exception('Unable to insert row: '.json_encode($row));
		}
		
		puts("All rows inserted.");
	}
	
	private function filterValue($value) {
		// If not a url and urlencoded then decode.
		if (!preg_match('/[a-z]+:\/\//i', $value) &&
				(preg_match('/%[0-9A-F]{2}/i', $value) || preg_match('/\+/', $value) && preg_match('/^\S+$/', $value)))
			$value = iconv("iso-8859-15", "utf-8", urldecode($value));
		
		// Strip backslashes if there are any.
		while (preg_match('/\\\\/', $value)) // quad backslash because regex parser needs 2.
			$value = stripcslashes($value);
		
		return $value;
	}
}