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
		
		foreach ($this->conf->tables as $name => $tableConf)
			$this->processTable($name, $tableConf);
	}
	
	private function truncateTable($pdo, $tablename) {
		return $pdo->execute("TRUNCATE TABLE $tablename");
	}
	
	private function getColumnNames($pdoStatement) {
		$colCount = $pdoStatement->columnCount();
		
		$names = array();
		
		for ($i=0; $i<$colCount; $i++) {
			$colMetadata = $pdoStatement->getColumnMeta($i);
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
	
	private function processTable($name, $conf) {
		puts("-----");
		puts("Processing table: $name");
		
		$stm = $this->source->execute("select * from $name");
		
		$count = $stm->rowCount();
		puts("Row count: $count");
		
		if($this->truncateTable($this->dest, $name))
			puts("Truncated destination table.");
		else
			puts("An error occured truncating destination table.");
		
		$colNames = $this->getColumnNames($stm);
		$insertStm = $this->prepareInsertStatement($this->dest, $name, $colNames);
		
		for ($i=0; $i<$count; $i++) {
//		for ($i=0; $i<10; $i++) {
			$row = $stm->fetchObject();
			
			foreach ($conf as $colname => $filters)
				$row = $this->filterColumn($row, $colname, explode(',', $filters));
			
			if (!$insertStm->execute($this->getRowValues($row, $colNames)))
				throw new Exception('Unable to insert row: '.json_encode($row));
		}
		
		puts("All rows inserted.");
	}
	
	private function filterColumn($row, $colname, $filters) {
		if (!$filters)
			return $row;
		
		$value = $row->$colname;
		
		switch (array_shift($filters)) {
			case 'stripcslashes':
				// check if there is actualy a backslash in the string.
				if (preg_match('/\\\\/', $value)) // quad backslash because regex parser needs 2.
					$value = stripcslashes($value);
				break;
			
			case 'urldecode':
				// check if string is actually urlencoded since data might not be consistent.
				if (preg_match('/%[0-9A-F]{2}/i', $value) || preg_match('/\+/', $value) && preg_match('/^\S+$/', $value))
					$value = iconv("iso-8859-15", "utf-8", urldecode($row->$colname));
				break;
		}
		
		$row->$colname = $value;
		
		if (preg_match('/\/n/', $value))
			puts(json_encode($row));
		
		return $this->filterColumn($row, $colname, $filters);
	}
}